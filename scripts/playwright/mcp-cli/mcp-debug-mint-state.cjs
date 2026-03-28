#!/usr/bin/env node

// Optional CLI wrapper over the running Playwright MCP server for debugging collectible mint state.

const { createClient } = require('./mcp-client.cjs');

function extractText(result) {
  return (result.content || [])
    .filter((item) => item.type === 'text')
    .map((item) => item.text)
    .join('\n');
}

async function main() {
  const forumUrl = process.env.FORUM_URL || 'http://127.0.0.1:8080';
  const targetCollectibleId = process.env.COLLECTIBLE_ID || null;
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
        const TARGET_ID = ${JSON.stringify(targetCollectibleId)};
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

        await login('admin');
        await goto('/u/admin/collectibles');

        const apiPayload = await page.evaluate(async () => {
          const response = await fetch('/api/collectibles?filter[user]=1&page[limit]=10&sort=-createdAt', {
            headers: { 'Content-Type': 'application/json' },
          });
          const payload = await response.json();
          return (payload.data || []).map((item) => ({
            id: String(item.id),
            name: item.attributes?.name || null,
            status: item.attributes?.status || null,
            tokenId: item.attributes?.tokenId || null,
            canMint: item.attributes?.canMint ?? null,
            metadataCid: item.attributes?.metadataCid || null,
            ipfsCid: item.attributes?.ipfsCid || null,
          }));
        });

        const galleryCards = await page.locator('.CollectibleCard').evaluateAll((nodes) =>
          nodes.map((node) => ({
            text: node.textContent?.replace(/\\s+/g, ' ').trim() || '',
            className: node.className,
          }))
        ).catch(() => []);

        const storeRecords = await page.evaluate(() => {
          const models = window.flarum?.core?.app?.store?.all?.('collectibles') || [];
          return models.slice(0, 20).map((model) => ({
            id: String(model.id()),
            name: model.name?.(),
            status: model.status?.(),
            tokenId: model.tokenId?.(),
            canMint: model.canMint?.(),
            metadataCid: model.metadataCid?.(),
            ipfsCid: model.ipfsCid?.(),
          }));
        });

        let modalState = null;
        if (TARGET_ID) {
          const targetName = 'Collectible #' + TARGET_ID;
          const targetCard = page.locator('.CollectibleCard').filter({ hasText: targetName }).first();

          if (await targetCard.isVisible().catch(() => false)) {
            await targetCard.click();
            const modal = page.locator('.CollectibleDetailModal').first();
            await modal.waitFor({ state: 'visible', timeout: 10000 }).catch(() => {});
            modalState = {
              text: await modal.innerText().catch(() => null),
              hasMintButton: await modal.locator('.Button').filter({ hasText: 'Mint as NFT' }).first().isVisible().catch(() => false),
              hasShowcaseButton: await modal.locator('.Button--primary').first().isVisible().catch(() => false),
            };
          }
        }

        return {
          targetId: TARGET_ID,
          apiPayload,
          galleryCards,
          storeRecords,
          modalState,
        };
      }`,
    });

    console.log(extractText(result));
  } finally {
    await client.close();
  }
}

main().catch((error) => {
  console.error('[mcp-debug-mint-state] ' + String(error?.message || error));
  process.exit(1);
});
