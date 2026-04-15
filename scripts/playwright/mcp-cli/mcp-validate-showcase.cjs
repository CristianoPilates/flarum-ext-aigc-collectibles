#!/usr/bin/env node

// Optional CLI wrapper over the running Playwright MCP server for validating the right-side showcase on real discussion/reply pages.

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
  const desiredCollectibleId = process.env.SHOWCASE_COLLECTIBLE_ID || null;
  const discussionTitle =
    process.env.SHOWCASE_DISCUSSION_TITLE || `Showcase validation ${new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19)}`;
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
        const DESIRED_COLLECTIBLE_ID = ${JSON.stringify(desiredCollectibleId)};
        const DISCUSSION_TITLE = ${JSON.stringify(discussionTitle)};
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

        const setup = await page.evaluate(async ({ discussionTitle, desiredCollectibleId }) => {
          const app = window.flarum?.core?.app;
          if (!app?.forum || !app?.session?.user || !app?.request || !app?.store) {
            throw new Error('Flarum app is not ready');
          }

          const apiUrl = app.forum.attribute('apiUrl');
          const actor = app.session.user;
          const userId = actor.id();
          const collectibleParams = new URLSearchParams({
            'filter[user]': String(userId),
            'page[limit]': '50',
            sort: '-createdAt',
          });

          const payload = await app.request({
            method: 'GET',
            url: apiUrl + '/collectibles?' + collectibleParams.toString(),
          });

          const collectibles = (payload.data || []).map((item) => ({
            id: String(item.id),
            name: item.attributes?.name || null,
            status: item.attributes?.status || null,
            ipfsCid: item.attributes?.ipfsCid || null,
            tokenId: item.attributes?.tokenId || null,
            rarity: item.attributes?.rarity || null,
          }));

          const selected =
            collectibles.find((item) => String(item.id) === String(desiredCollectibleId)) ||
            collectibles.find((item) => item.status === 'completed' && item.ipfsCid);

          if (!selected) {
            throw new Error('No completed collectible with IPFS image found for showcase validation');
          }

          await app.request({
            method: 'PATCH',
            url: apiUrl + '/collectibles/' + selected.id,
            body: {
              data: {
                type: 'collectibles',
                id: String(selected.id),
                attributes: {
                  isShowcase: true,
                },
              },
            },
          });

          actor.pushAttributes({
            showcaseCollectibleId: Number(selected.id),
            showcaseCollectibleName: selected.name,
            showcaseCollectibleCid: selected.ipfsCid,
            showcaseCollectibleRarity: selected.rarity,
            showcaseCollectibleTokenId: selected.tokenId,
          });

          const discussion = await app.store.createRecord('discussions').save({
            title: discussionTitle,
            content: 'Starter post for validating the right-side collectible showcase.',
          });

          const reply = await app.store.createRecord('posts').save({
            content: 'Reply post for validating the right-side collectible showcase.',
            relationships: { discussion },
          });

          return {
            collectible: selected,
            discussionId: String(discussion.id()),
            discussionTitle: discussion.title(),
            discussionUrl: app.route.discussion(discussion, reply.number()),
            replyNumber: reply.number(),
          };
        }, { discussionTitle: DISCUSSION_TITLE, desiredCollectibleId: DESIRED_COLLECTIBLE_ID });

        await goto(setup.discussionUrl);
        await page.locator('.PostStream').first().waitFor({ state: 'visible', timeout: 15000 });
        await page.locator('.CommentPost').first().waitFor({ state: 'visible', timeout: 15000 });
        await wait(1800);

        const discussionScreenshotPath = OUTPUT_DIR.replace(/\\/$/, '') + '/showcase-discussion.png';
        const modalScreenshotPath = OUTPUT_DIR.replace(/\\/$/, '') + '/showcase-modal.png';

        await page.screenshot({ path: discussionScreenshotPath, fullPage: true });

        const showcaseState = await page.evaluate(() => {
          const panels = Array.from(document.querySelectorAll('.PostCollectibleShowcase'));
          const wrappers = Array.from(document.querySelectorAll('.CollectibleShowcasePost'));
          const posts = Array.from(document.querySelectorAll('.CommentPost'));

          return {
            showcasePanelCount: panels.length,
            showcaseWrapperCount: wrappers.length,
            commentPostCount: posts.length,
            showcaseCards: panels.slice(0, 4).map((panel) => ({
              text: panel.textContent?.replace(/\\s+/g, ' ').trim() || '',
              width: Math.round(panel.getBoundingClientRect().width),
            })),
            wrapperLayout: wrappers.slice(0, 4).map((wrapper) => {
              const style = window.getComputedStyle(wrapper);
              return {
                gridTemplateColumns: style.gridTemplateColumns,
                gap: style.gap,
              };
            }),
          };
        });

        const firstShowcase = page.locator('.PostCollectibleShowcase').first();
        const showcaseVisible = await firstShowcase.isVisible().catch(() => false);

        let modalText = null;
        if (showcaseVisible) {
          await firstShowcase.click();
          const modal = page.locator('.CollectibleDetailModal').first();
          await modal.waitFor({ state: 'visible', timeout: 10000 });
          await wait(600);
          modalText = await modal.innerText().catch(() => null);
          await modal.screenshot({ path: modalScreenshotPath });
        }

        return {
          setup,
          showcaseState,
          showcaseVisible,
          modalText,
          screenshots: {
            discussion: discussionScreenshotPath,
            modal: showcaseVisible ? modalScreenshotPath : null,
          },
        };
      }`,
    });

    console.log(extractText(result));
  } finally {
    await client.close();
  }
}

main().catch((error) => {
  console.error('[mcp-validate-showcase] ' + String(error?.message || error));
  process.exit(1);
});
