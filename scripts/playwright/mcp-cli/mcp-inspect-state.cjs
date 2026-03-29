#!/usr/bin/env node

// Optional CLI wrapper over the running Playwright MCP server for inspecting shared profile state.

const path = require('node:path');
const { createClient } = require('./mcp-client.cjs');

function extractText(result) {
  return (result.content || [])
    .filter((item) => item.type === 'text')
    .map((item) => item.text)
    .join('\n');
}

async function main() {
  const forumUrl = process.env.FORUM_URL || 'http://127.0.0.1:8080';
  const outputDir = path.resolve(process.env.PLAYWRIGHT_MCP_OUTPUT_DIR || '.devenv/state/playwright-mcp-output');
  const client = await createClient();

  try {
    const tools = await client.listTools();
    const toolNames = (tools.tools || []).map((tool) => tool.name).sort();
    console.log(JSON.stringify({ toolNames }, null, 2));

    if (!toolNames.includes('browser_run_code')) {
      throw new Error('browser_run_code tool is not available');
    }

    const result = await client.callTool('browser_run_code', {
      code: `async (page) => {
        const FORUM_URL = ${JSON.stringify(forumUrl)};
        const OUTPUT_DIR = ${JSON.stringify(outputDir)};
        const wait = (ms) => page.waitForTimeout(ms);
        const popupScreenshots = [];

        function appUrl(pathname) {
          if (pathname.startsWith('http://') || pathname.startsWith('https://')) {
            return pathname;
          }

          if (pathname.startsWith('/')) {
            return FORUM_URL.replace(/\\/$/, '') + pathname;
          }

          return FORUM_URL.replace(/\\/$/, '') + '/' + pathname;
        }

        async function goto(pathname) {
          await page.goto(appUrl(pathname), { waitUntil: 'load' });
          await page.locator('body').waitFor({ state: 'visible', timeout: 15000 });
          await wait(700);
        }

        async function currentUsername() {
          return await page.evaluate(() => window.flarum?.core?.app?.session?.user?.username?.() || null);
        }

        async function login(username, password = 'password') {
          await goto('/');

          const loggedIn = await page.locator('.SessionDropdown').first().isVisible().catch(() => false);
          if (loggedIn && (await currentUsername()) === username) {
            return;
          }

          const loginButton = page.locator('.item-logIn .Button, header .Button:has-text("Log In")').first();
          if (await loginButton.isVisible().catch(() => false)) {
            await loginButton.click();
            await wait(500);
          }

          const identification = page.locator('.LogInModal input[name="identification"], .Modal input[name="identification"]').first();
          await identification.waitFor({ state: 'visible', timeout: 10000 });
          await identification.fill(username);
          await page.locator('.LogInModal input[type="password"], .Modal input[type="password"]').first().fill(password);
          await page.locator('.LogInModal .Button--primary, .Modal .Button--primary').first().click();
          await page.waitForLoadState('networkidle').catch(() => {});
          await wait(1200);
        }

        async function metaMaskPages() {
          return page.context().pages().filter((candidate) => candidate.url().startsWith('chrome-extension://'));
        }

        async function getMetaMaskExtensionId() {
          const parseExtensionId = (value) => {
            const match = String(value || '').match(/^chrome-extension:\\/\\/([^/]+)/);
            return match ? match[1] : null;
          };

          const extensionPage = (await metaMaskPages())[0];
          if (extensionPage) {
            return parseExtensionId(extensionPage.url());
          }

          const worker = page.context().serviceWorkers().find((candidate) => candidate.url().startsWith('chrome-extension://'));
          if (worker) {
            return parseExtensionId(worker.url());
          }

          return null;
        }

        async function navigateExistingMetaMaskPageToHome() {
          const extensionId = await getMetaMaskExtensionId();
          const existing = (await metaMaskPages())[0];
          if (!extensionId || !existing) {
            return null;
          }

          await existing.evaluate((targetExtensionId) => {
            window.location.href = 'chrome-extension://' + targetExtensionId + '/home.html';
          }, extensionId).catch(() => {});
          await existing.waitForLoadState('domcontentloaded').catch(() => {});
          await wait(1200);
          return existing;
        }

        async function inspectMetaMaskPage(candidate, origin) {
          await candidate.waitForLoadState('domcontentloaded').catch(() => {});
          await wait(800);

          const bodyText = await candidate.locator('body').innerText().catch(() => '');
          const screenshotPath = OUTPUT_DIR.replace(/\\/$/, '') + '/metamask-popup-' + popupScreenshots.length + '.png';
          await candidate.screenshot({ path: screenshotPath }).catch(() => {});
          popupScreenshots.push(screenshotPath);

          return {
            origin,
            url: candidate.url(),
            title: await candidate.title().catch(() => null),
            onboarding: /Get started|Import an existing wallet|Import wallet|I have an existing wallet|Create a new wallet|Secret Recovery Phrase/i.test(bodyText),
            locked: /unlock|enter your password/i.test(bodyText),
            accountReady: /Account\\s*1|Assets|Activity|Tokens|NFTs|Portfolio/i.test(bodyText),
            bodySnippet: bodyText.slice(0, 1200),
            screenshotPath,
          };
        }

        async function inspectPopupPages() {
          const popups = [];
          const candidates = await metaMaskPages();

          for (let index = 0; index < candidates.length; index += 1) {
            const candidate = candidates[index];
            popups.push(await inspectMetaMaskPage(candidate, 'existing-' + index));
          }

          return popups;
        }

        await login('admin');
        await goto('/u/admin/collectibles');

        const providerState = await page.evaluate(async () => {
          const provider = window.ethereum;
          let unlocked = null;
          let selectedAddress = null;
          let ethereumChainId = null;
          let walletAccounts = null;

          try {
            unlocked = await provider?._metamask?.isUnlocked?.();
          } catch (error) {
            unlocked = 'error:' + String(error?.message || error);
          }

          try {
            selectedAddress = provider?.selectedAddress || null;
          } catch (error) {
            selectedAddress = 'error:' + String(error?.message || error);
          }

          try {
            ethereumChainId = await provider?.request?.({ method: 'eth_chainId' });
          } catch (error) {
            ethereumChainId = 'error:' + String(error?.message || error);
          }

          try {
            walletAccounts = await provider?.request?.({ method: 'eth_accounts' });
          } catch (error) {
            walletAccounts = 'error:' + String(error?.message || error);
          }

          return {
            href: window.location.href,
            title: document.title,
            hasEthereum: Boolean(provider),
            isMetaMask: Boolean(provider?.isMetaMask),
            unlocked,
            selectedAddress,
            ethereumChainId,
            walletAccounts,
          };
        });

        const walletAddress = page.locator('.WalletConnector-address').first();
        const connectButton = page.locator('.WalletConnector .Button--primary').first();
        const alreadyBound = await walletAddress.isVisible().catch(() => false);

        let popupPagesBefore = await inspectPopupPages();
        if (!alreadyBound && (await connectButton.isVisible().catch(() => false))) {
          await connectButton.click();
          await wait(2500);
        }

        const popupPagesAfter = await inspectPopupPages();
        const navigatedMetaMaskPage = await navigateExistingMetaMaskPageToHome();
        const freshMetaMaskPageState = navigatedMetaMaskPage
          ? await inspectMetaMaskPage(navigatedMetaMaskPage, 'navigated-home')
          : null;

        return {
          app: providerState,
          extensionId: await getMetaMaskExtensionId(),
          pages: page.context().pages().map((candidate) => candidate.url()),
          workers: page.context().serviceWorkers().map((worker) => worker.url()),
          alreadyBound,
          popupPagesBefore,
          popupPagesAfter,
          freshMetaMaskPageState,
          popupScreenshots,
        };
      }`,
    });

    console.log(extractText(result));
  } finally {
    await client.close();
  }
}

main().catch((error) => {
  console.error('[mcp-inspect-state] ' + String(error?.message || error));
  process.exit(1);
});
