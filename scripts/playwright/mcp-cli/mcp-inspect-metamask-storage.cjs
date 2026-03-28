#!/usr/bin/env node

// Optional CLI wrapper over the running Playwright MCP server for inspecting MetaMask extension storage.

const { createClient } = require('./mcp-client.cjs');

function extractText(result) {
  return (result.content || [])
    .filter((item) => item.type === 'text')
    .map((item) => item.text)
    .join('\n');
}

async function main() {
  const forumUrl = process.env.FORUM_URL || 'http://127.0.0.1:8080';
  const client = await createClient();

  try {
    const result = await client.callTool('browser_run_code', {
      code: `async (page) => {
        const FORUM_URL = ${JSON.stringify(forumUrl)};
        const wait = (ms) => page.waitForTimeout(ms);

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

        await login('admin');
        await goto('/u/admin/collectibles');

        let candidate = (await metaMaskPages())[0];
        if (!candidate) {
          const connectButton = page.locator('.WalletConnector .Button--primary').first();
          if (await connectButton.isVisible().catch(() => false)) {
            await connectButton.click();
            await wait(2500);
          }
          candidate = (await metaMaskPages())[0];
        }

        if (!candidate) {
          throw new Error('No MetaMask extension page is currently open.');
        }

        await candidate.waitForLoadState('domcontentloaded').catch(() => {});
        await wait(500);

        const info = await candidate.evaluate(async () => {
          const storageLocal = await new Promise((resolve) => {
            try {
              chrome.storage.local.get(null, (items) => resolve(items || {}));
            } catch (error) {
              resolve({ __error: String(error?.message || error) });
            }
          });

          const storageSession = await new Promise((resolve) => {
            try {
              if (!chrome.storage?.session?.get) {
                resolve({ __unsupported: true });
                return;
              }

              chrome.storage.session.get(null, (items) => resolve(items || {}));
            } catch (error) {
              resolve({ __error: String(error?.message || error) });
            }
          });

          const localStorageItems = {};
          for (let index = 0; index < localStorage.length; index += 1) {
            const key = localStorage.key(index);
            localStorageItems[key] = localStorage.getItem(key);
          }

          const summary = {
            href: window.location.href,
            title: document.title,
            bodySnippet: document.body?.innerText?.slice(0, 1200) || '',
            localStorageKeys: Object.keys(localStorageItems).sort(),
            chromeStorageLocalKeys: Object.keys(storageLocal).sort(),
            chromeStorageSessionKeys: Object.keys(storageSession).sort(),
            localStorageItems,
            storageLocal,
            storageSession,
          };

          return summary;
        });

        return info;
      }`,
    });

    console.log(extractText(result));
  } finally {
    await client.close();
  }
}

main().catch((error) => {
  console.error('[mcp-inspect-metamask-storage] ' + String(error?.message || error));
  process.exit(1);
});
