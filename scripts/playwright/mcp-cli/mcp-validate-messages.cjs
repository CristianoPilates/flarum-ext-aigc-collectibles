#!/usr/bin/env node

// Optional CLI wrapper over the running Playwright MCP server for validating the real PM flow on flarum-messages.

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
  const messageText = process.env.MCP_MESSAGES_TEXT || `MCP PM validation ${new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19)}`;
  const client = await createClient();

  try {
    const tools = await client.listTools();
    const toolNames = new Set((tools.tools || []).map((tool) => tool.name));

    if (!toolNames.has('browser_run_code')) {
      throw new Error('browser_run_code tool is not available');
    }

    const result = await client.callTool('browser_run_code', {
      code: `async (page) => {
        const BASE_URL = ${JSON.stringify(forumUrl)};
        const OUTPUT_DIR = ${JSON.stringify(outputDir)};
        const MESSAGE_TEXT = ${JSON.stringify(messageText)};
        const wait = (ms) => page.waitForTimeout(ms);

        function appUrl(pathname) {
          if (pathname.startsWith('http://') || pathname.startsWith('https://')) {
            return pathname;
          }

          if (pathname.startsWith('/')) {
            return BASE_URL.replace(/\\/$/, '') + pathname;
          }

          return BASE_URL.replace(/\\/$/, '') + '/' + pathname;
        }

        async function goto(pathname) {
          await page.goto(appUrl(pathname), { waitUntil: 'domcontentloaded' });
          await page.locator('body').waitFor({ state: 'visible', timeout: 15000 });
          await page.waitForLoadState('networkidle').catch(() => {});
          await wait(800);
        }

        async function currentUsername() {
          return await page.evaluate(() => window.flarum?.core?.app?.session?.user?.username?.() || null);
        }

        async function logout() {
          await page.evaluate(async () => {
            const csrfToken = window.flarum?.core?.app?.session?.csrfToken;
            if (csrfToken) {
              await fetch('/logout', {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrfToken },
              });
            }
          }).catch(() => {});

          await goto('/');
        }

        async function login(username, password = 'password') {
          await goto('/');

          const loggedIn = await page.locator('.SessionDropdown').first().isVisible().catch(() => false);
          if (loggedIn && (await currentUsername()) === username) {
            return;
          }

          if (loggedIn) {
            await logout();
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

        async function sendMessageAsBuyer() {
          await login('buyer');
          await goto('/messages');

          await page.locator('.MessagesPage, .MessagesPage-nav').first().waitFor({ state: 'visible', timeout: 15000 });

          const newMessageButton = page.locator('.MessagesPage-newMessage').first();
          await newMessageButton.waitFor({ state: 'visible', timeout: 10000 });
          await newMessageButton.click();

          const composer = page.locator('.Composer').first();
          await composer.waitFor({ state: 'visible', timeout: 10000 });

          const recipientsButton = composer.locator('button').filter({ hasText: 'Recipients' }).first();
          await recipientsButton.waitFor({ state: 'visible', timeout: 10000 });
          await recipientsButton.click();

          const selectionModal = page.locator('.UserSelectionModal').first();
          await selectionModal.waitFor({ state: 'visible', timeout: 10000 });

          const searchInput = selectionModal.locator('.UserSelectionModal-form-input input.FormControl').first();
          await searchInput.fill('seller');
          await wait(900);

          const sellerItem = selectionModal.locator('.UserSelectionModal-listItem').filter({ hasText: 'seller' }).first();
          await sellerItem.waitFor({ state: 'visible', timeout: 10000 });
          await sellerItem.click();

          const selectButton = selectionModal.locator('.UserSelectionModal-form-submit .Button--primary').first();
          await selectButton.waitFor({ state: 'visible', timeout: 10000 });
          await selectButton.click();

          const editor = composer.locator('.TextEditor-editor').first();
          await editor.fill(MESSAGE_TEXT);

          const sendButton = composer.locator('.Composer-footer .Button--primary').first();
          await sendButton.click();

          await page.waitForLoadState('networkidle').catch(() => {});
          await wait(1500);

          const buyerDialogUrl = page.url();
          const buyerScreenshotPath = OUTPUT_DIR.replace(/\\/$/, '') + '/messages-buyer-dialog.png';
          await page.screenshot({ path: buyerScreenshotPath, fullPage: true });

          return {
            buyerDialogUrl,
            buyerScreenshotPath,
          };
        }

        async function verifyMessageAsSeller() {
          await login('seller');
          await goto('/messages');

          await page.locator('.MessagesPage, .MessagesPage-nav').first().waitFor({ state: 'visible', timeout: 15000 });
          await page.locator('body').getByText(MESSAGE_TEXT).first().waitFor({ state: 'visible', timeout: 15000 });

          const sellerScreenshotPath = OUTPUT_DIR.replace(/\\/$/, '') + '/messages-seller-dialog.png';
          await page.screenshot({ path: sellerScreenshotPath, fullPage: true });

          return {
            sellerScreenshotPath,
            sellerDialogUrl: page.url(),
            bodyText: await page.locator('body').innerText(),
          };
        }

        await page.setViewportSize({ width: 1440, height: 1280 });

        const buyer = await sendMessageAsBuyer();
        const seller = await verifyMessageAsSeller();

        return {
          messageText: MESSAGE_TEXT,
          buyer,
          seller,
        };
      }`,
    });

    console.log(extractText(result));
  } finally {
    await client.close();
  }
}

main().catch((error) => {
  console.error('[mcp-validate-messages] ' + String(error?.message || error));
  process.exit(1);
});
