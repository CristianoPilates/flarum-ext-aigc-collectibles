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
  const collectibleId = process.env.PROOF_COLLECTIBLE_ID || null;
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
          await page.goto(appUrl(pathname), { waitUntil: 'domcontentloaded' });
          await page.locator('body').waitFor({ state: 'visible', timeout: 15000 });
          await page.waitForLoadState('networkidle').catch(() => {});
          await wait(900);
        }

        async function currentUsername() {
          return await page.evaluate(() => window.flarum?.core?.app?.session?.user?.username?.() || null);
        }

        async function login(username, password = 'password') {
          await goto('/');

          const response = await page.evaluate(async (credentials) => {
            const request = await fetch('/api/token', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ ...credentials, remember: true }),
            });

            return await request.json();
          }, { identification: username, password });

          if (!response?.token) {
            throw new Error('Login failed for ' + username);
          }

          await page.context().addCookies([
            {
              name: 'flarum_remember',
              value: response.token,
              domain: '127.0.0.1',
              path: '/',
            },
          ]);

          await goto('/');
        }

        await page.setViewportSize({ width: 1440, height: 1280 });
        await login('admin');

        const target = await page.evaluate(async (preferredCollectibleId) => {
          const app = window.flarum?.core?.app;
          if (!app?.forum || !app?.session?.user || !app?.request) {
            throw new Error('Flarum app is not ready');
          }

          const apiUrl = app.forum.attribute('apiUrl');
          const userId = app.session.user.id();
          const params = new URLSearchParams({
            'filter[user]': String(userId),
            'page[limit]': '50',
            include: 'owner',
            sort: '-createdAt',
          });

          const payload = await app.request({
            method: 'GET',
            url: apiUrl + '/collectibles?' + params.toString(),
          });

          const items = payload?.data || [];
          const selected =
            (preferredCollectibleId
              ? items.find((item) => String(item.id) === String(preferredCollectibleId))
              : null) ||
            items.find((item) => item.attributes?.status === 'completed' && (item.attributes?.metadataCid || item.attributes?.tokenId)) ||
            null;

          if (!selected) {
            return null;
          }

          return {
            id: String(selected.id),
            name: selected.attributes?.name || ('Collectible #' + selected.id),
          };
        }, TARGET_COLLECTIBLE_ID);

        if (!target?.id) {
          throw new Error('No completed collectible with proof data was available for admin.');
        }

        await goto('/u/admin/collectibles');

        const gallery = page.locator('.CollectibleGallery').first();
        await gallery.waitFor({ state: 'visible', timeout: 15000 });

        const targetCard = page.locator('.CollectibleCard').filter({ hasText: target.name }).first();
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
          collectibleId: target.id,
          collectibleName: target.name,
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
