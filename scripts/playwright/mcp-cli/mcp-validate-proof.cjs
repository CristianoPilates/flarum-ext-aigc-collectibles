#!/usr/bin/env node

// Optional CLI wrapper over the running Playwright MCP server for validating the four-layer collectible proof modal.

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
  const collectibleId = process.env.PROOF_COLLECTIBLE_ID || '43';
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
        const TARGET_COLLECTIBLE_ID = ${JSON.stringify(collectibleId)};
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
          await page.goto(appUrl(pathname), { waitUntil: 'load' });
          await page.locator('body').waitFor({ state: 'visible', timeout: 15000 });
          await page.waitForLoadState('networkidle').catch(() => {});
          await wait(900);
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

        await page.setViewportSize({ width: 1440, height: 1280 });
        await login('admin');
        await goto('/u/admin/collectibles');

        const targetCard = page.locator('.CollectibleCard').filter({ hasText: 'Collectible #' + TARGET_COLLECTIBLE_ID }).first();
        await targetCard.waitFor({ state: 'visible', timeout: 15000 });
        await targetCard.click();

        const detailModal = page.locator('.CollectibleDetailModal').first();
        await detailModal.waitFor({ state: 'visible', timeout: 10000 });

        const proofButton = detailModal.locator('.Button').filter({ hasText: /View Proof|查看证明/ }).first();
        await proofButton.waitFor({ state: 'visible', timeout: 10000 });
        await proofButton.click();

        const proofModal = page.locator('.CollectibleProofModal').first();
        await proofModal.waitFor({ state: 'visible', timeout: 10000 });
        await wait(1200);

        const screenshotPath = OUTPUT_DIR.replace(/\\/$/, '') + '/collectible-proof-modal.png';
        await proofModal.screenshot({ path: screenshotPath });

        const state = await page.evaluate(() => {
          const sections = Array.from(document.querySelectorAll('.CollectibleProofModal-sectionTitle')).map((node) =>
            node.textContent?.replace(/\\s+/g, ' ').trim() || ''
          );

          const rows = Array.from(document.querySelectorAll('.CollectibleProofModal-row')).map((node) =>
            node.textContent?.replace(/\\s+/g, ' ').trim() || ''
          );

          return {
            sectionTitles: sections,
            rowCount: rows.length,
            rows: rows.slice(0, 20),
          };
        });

        const proofText = await proofModal.innerText();

        return {
          collectibleId: TARGET_COLLECTIBLE_ID,
          screenshotPath,
          state,
          proofText,
        };
      }`,
    });

    console.log(extractText(result));
  } finally {
    await client.close();
  }
}

main().catch((error) => {
  console.error('[mcp-validate-proof] ' + String(error?.message || error));
  process.exit(1);
});
