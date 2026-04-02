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
    const result = await client.callTool('browser_run_code', {
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
          const params = new URLSearchParams({
            'filter[user]': String(actor.id()),
            'page[limit]': '50',
            sort: '-createdAt',
          });

          const payload = await app.request({
            method: 'GET',
            url: apiUrl + '/collectibles?' + params.toString(),
          });

          const selected = (payload.data || []).find((item) => item.attributes?.status === 'completed' && item.attributes?.ipfsCid);
          if (!selected) {
            throw new Error('No completed collectible found');
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
                  title: 'PM Context Validation ' + Date.now(),
                  content: 'Starter post for PM context validation.',
                },
              },
            }),
          });

          const discussionPayload = await discussionResponse.json();
          const discussionId = discussionPayload?.data?.id;

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

          return {
            collectibleName: selected.attributes?.name || null,
            collectibleRarity: selected.attributes?.rarity || null,
            tokenId: selected.attributes?.tokenId || null,
            discussionTitle: discussionPayload?.data?.attributes?.title || null,
            discussionUrl: '/d/' + discussionId + '/' + replyNumber,
          };
        });

        await login('buyer');
        await goto(setup.discussionUrl);

        const showcase = page.locator('.PostCollectibleShowcase').first();
        await showcase.waitFor({ state: 'visible', timeout: 10000 });
        await showcase.hover();
        await wait(300);

        await page.locator('.PostCollectibleShowcase-messageButton').first().click();
        await wait(1200);

        const editor = page.locator('.TextEditor-editor').first();
        await editor.waitFor({ state: 'visible', timeout: 10000 });

        const editorValue = await editor.inputValue();

        return {
          setup,
          editorValue,
        };
      }`,
    });

    console.log(extractText(result));
  } finally {
    await client.close();
  }
}

main().catch((error) => {
  console.error('[validate-showcase-context] ' + String(error?.message || error));
  process.exit(1);
});
