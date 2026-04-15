#!/usr/bin/env node

const { createClient } = require('./mcp-client.cjs');

function extractText(result) {
  return (result.content || [])
    .filter((item) => item.type === 'text')
    .map((item) => item.text)
    .join('\n');
}

async function main() {
  const client = await createClient();

  try {
    const result = await client.callTool(
      'browser_run_code',
      {
        code: `async (page) => {
          const BASE_URL = 'http://127.0.0.1:8080';
          const wait = (ms) => page.waitForTimeout(ms);

          function appUrl(pathname) {
            if (pathname.startsWith('http://') || pathname.startsWith('https://')) return pathname;
            if (pathname.startsWith('/')) return BASE_URL + pathname;
            return BASE_URL + '/' + pathname;
          }

          async function goto(pathname) {
            await page.goto(appUrl(pathname), { waitUntil: 'domcontentloaded' });
            await page.locator('body').waitFor({ state: 'visible', timeout: 15000 });
            await page.waitForLoadState('networkidle').catch(() => {});
            await wait(900);
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

          await login('admin');

          const setup = await page.evaluate(async () => {
            const app = window.flarum?.core?.app;
            if (!app?.forum || !app?.session?.user || !app?.request) {
              throw new Error('Flarum app is not ready');
            }

            const apiUrl = app.forum.attribute('apiUrl');
            const actor = app.session.user;
            const csrfToken = app.session.csrfToken || null;
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

            const selected = (payload.data || []).find((item) => item.attributes?.status === 'completed' && item.attributes?.ipfsCid);
            if (!selected) return null;

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
              showcaseCollectibleName: selected.attributes?.name || null,
              showcaseCollectibleCid: selected.attributes?.ipfsCid || null,
              showcaseCollectibleRarity: selected.attributes?.rarity || null,
              showcaseCollectibleTokenId: selected.attributes?.tokenId || null,
            });

            const discussionResponse = await fetch(apiUrl + '/discussions', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                ...(csrfToken ? { 'X-CSRF-Token': csrfToken } : {}),
              },
              body: JSON.stringify({
                data: {
                  type: 'discussions',
                  attributes: {
                    title: 'Showcase CTA Click Validation ' + Date.now(),
                    content: 'Starter post for CTA click validation.',
                  },
                },
              }),
            });

            const discussionPayload = await discussionResponse.json();
            const discussionId = discussionPayload?.data?.id;

            if (!discussionResponse.ok || !discussionId) {
              throw new Error(discussionPayload?.errors?.[0]?.detail || 'Failed to create discussion');
            }

            const postResponse = await fetch(apiUrl + '/posts', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                ...(csrfToken ? { 'X-CSRF-Token': csrfToken } : {}),
              },
              body: JSON.stringify({
                data: {
                  type: 'posts',
                  attributes: {
                    content: 'Reply post with showcase CTA.',
                  },
                  relationships: {
                    discussion: {
                      data: {
                        type: 'discussions',
                        id: String(discussionId),
                      },
                    },
                  },
                },
              }),
            });

            const postPayload = await postResponse.json();
            const replyNumber = postPayload?.data?.attributes?.number;

            if (!postResponse.ok || !replyNumber) {
              throw new Error(postPayload?.errors?.[0]?.detail || 'Failed to create reply');
            }

            return {
              discussionUrl: '/d/' + discussionId + '/' + replyNumber,
              discussionTitle: discussionPayload?.data?.attributes?.title || null,
            };
          });

          if (!setup?.discussionUrl) {
            return { skipped: true, reason: 'No completed collectible with IPFS image was available.' };
          }

          await login('buyer');
          await goto(setup.discussionUrl);

          const showcase = page.locator('.PostCollectibleShowcase').first();
          await showcase.waitFor({ state: 'visible', timeout: 10000 });
          await showcase.hover();
          await wait(300);

          const button = page.locator('.PostCollectibleShowcase-messageButton').first();
          await button.waitFor({ state: 'visible', timeout: 10000 });

          const preClick = await page.evaluate(() => {
            const btn = document.querySelector('.PostCollectibleShowcase-messageButton');
            const rect = btn?.getBoundingClientRect();
            if (!btn || !rect) return null;
            const x = rect.left + rect.width / 2;
            const y = rect.top + rect.height / 2;
            const top = document.elementFromPoint(x, y);

            return {
              topTag: top?.tagName || null,
              topClass: top?.className || null,
            };
          });

          await button.click();
          await wait(1200);

          const composer = page.locator('.Composer').first();
          const editor = composer.locator('.TextEditor-editor').first();

          let editorVisible = false;
          let waitError = null;
          let editorValue = null;

          try {
            await editor.waitFor({ state: 'visible', timeout: 10000 });
            editorVisible = true;
            editorValue = await editor.inputValue();
          } catch (error) {
            waitError = String(error?.message || error);
          }

          const postClick = await page.evaluate(() => {
            const app = window.flarum?.core?.app;
            const composerRoot = document.querySelector('#composer');
            const composer = composerRoot?.querySelector('.Composer');
            const editor = composerRoot?.querySelector('.TextEditor-editor');

            return {
              url: window.location.href,
              routeName: app?.current?.data?.routeName || null,
              composerMounted: Boolean(app?.composer?.mounted),
              composerPosition: app?.composer?.position || null,
              composerVisibleFlag: app?.composer?.isVisible?.() || false,
              composerBodyName: app?.composer?.body?.componentClass?.name || null,
              composerClass: composer?.className || null,
              composerDisplay: composer ? window.getComputedStyle(composer).display : null,
              composerVisibility: composer ? window.getComputedStyle(composer).visibility : null,
              composerOpacity: composer ? window.getComputedStyle(composer).opacity : null,
              editorExists: Boolean(editor),
              editorText: editor?.textContent || null,
            };
          });

          return {
            skipped: false,
            setup,
            preClick,
            composerVisible: await composer.isVisible().catch(() => false),
            editorVisible,
            editorValue,
            waitError,
            currentUrl: page.url(),
            postClick,
          };
        }`,
      },
      {
        timeout: 10 * 60 * 1000,
        maxTotalTimeout: 15 * 60 * 1000,
      }
    );

    console.log(extractText(result));
  } finally {
    await client.close();
  }
}

main().catch((error) => {
  console.error('[validate-showcase-cta-click] ' + String(error?.message || error));
  process.exit(1);
});
