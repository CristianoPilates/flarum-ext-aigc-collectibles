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
        const pageErrors = [];
        const consoleErrors = [];

        try {
          const isolatedPage = await page.context().newPage();
          await page.close().catch(() => {});
          page = isolatedPage;
        } catch (error) {
          consoleErrors.push('[mcp-inspect-barter-composer] failed to create isolated page: ' + String(error?.message || error));
        }

        page.on('pageerror', (error) => {
          pageErrors.push({
            message: String(error?.message || error),
            stack: String(error?.stack || ''),
          });
        });

        page.on('console', (msg) => {
          if (msg.type() === 'error') {
            consoleErrors.push(msg.text());
          }
        });

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

        async function ensureDirectDialog(fromUsername, toUsername) {
          await login(fromUsername);

          return await page.evaluate(async ({ toUsername }) => {
            const app = window.flarum?.core?.app;

            const apiUrl = app.forum.attribute('apiUrl');
            const actor = app.session.user;
            const usersPayload = await app.request({
              method: 'GET',
              url: apiUrl + '/users',
              params: {
                filter: {
                  q: toUsername,
                },
              },
            });

            const users = usersPayload?.data || [];
            const target = users.find((item) => item.attributes?.username === toUsername);

            if (!target) {
              throw new Error('Target user not found: ' + toUsername);
            }

            const dialogsPayload = await app.request({
              method: 'GET',
              url: apiUrl + '/dialogs',
              params: {
                include: 'users',
                'page[limit]': 50,
              },
            });

            const dialogs = dialogsPayload?.data || [];
            const targetDialog = dialogs.find((dialog) => {
              const userIds = (dialog.relationships?.users?.data || []).map((user) => Number(user.id));
              return userIds.length === 2 && userIds.includes(Number(actor.id())) && userIds.includes(Number(target.id));
            });

            if (targetDialog) {
              return Number(targetDialog.id);
            }

            const messageResponse = await app.request({
              method: 'POST',
              url: apiUrl + '/dialog-messages',
              body: {
                data: {
                  type: 'dialog-messages',
                  attributes: {
                    content: 'Inspect barter composer bootstrap ' + Date.now(),
                    users: [{ id: String(target.id), type: 'users' }],
                  },
                },
              },
            });

            return Number(messageResponse?.data?.relationships?.dialog?.data?.id);
          }, { toUsername });
        }

        await login('buyer');
        const dialogId = await ensureDirectDialog('buyer', 'seller');
        await goto('/messages/dialog/' + dialogId);

        let clickedCreateButton = false;
        let clickError = null;

        try {
          const createButton = page.locator('.BarterThreadPanel .Button--primary').first();
          const createVisible = await createButton.isVisible().catch(() => false);

          if (createVisible) {
            await createButton.click();
            clickedCreateButton = true;
            await wait(1500);
          }
        } catch (error) {
          clickError = {
            message: String(error?.message || error),
            stack: String(error?.stack || ''),
          };
        }

        return await page.evaluate(({ pageErrors, consoleErrors, clickedCreateButton, clickError }) => {
          const app = window.flarum?.core?.app;
          const composer = app?.composer;
          const bodyClass = composer?.body?.componentClass;
          const composerElement = document.querySelector('.Composer');
          const dialogSection = document.querySelector('.DialogSection');
          const threadPanel = document.querySelector('.BarterThreadPanel');

          const streamWrap = document.querySelector('.DialogSection-streamWrap');
          const handshakeButtons = Array.from(document.querySelectorAll('button, a'))
            .map((node) => ({
              text: node.textContent?.trim() || null,
              className: node.className || null,
            }))
            .filter((item) => item.text || String(item.className).includes('fa-handshake'));

          return {
            route: window.location.pathname,
            click: {
              clickedCreateButton,
              clickError,
            },
            dialogPage: {
              title: document.title,
              dialogSectionExists: Boolean(dialogSection),
              streamWrapExists: Boolean(streamWrap),
              threadPanelExists: Boolean(threadPanel),
              dialogSectionSnippet: dialogSection?.innerHTML?.slice(0, 2500) || null,
              pageSnippet: document.body?.innerHTML?.slice(0, 2500) || null,
              handshakeButtons,
            },
            composer: {
              position: composer?.position || null,
              mounted: composer?.mounted || false,
              visible: composer?.isVisible?.() || false,
              bodyName: bodyClass?.name || null,
              bodyString: String(bodyClass),
              attrsKeys: Object.keys(composer?.body?.attrs || {}),
              replyingToId: composer?.body?.attrs?.replyingTo?.id?.() || null,
            },
            dom: {
              composerExists: Boolean(composerElement),
              composerClass: composerElement?.className || null,
              composerDisplay: composerElement ? window.getComputedStyle(composerElement).display : null,
              editorExists: Boolean(document.querySelector('.TextEditor-editor')),
              barterPanelExists: Boolean(document.querySelector('.BarterComposerPanel')),
              barterPanelText: document.querySelector('.BarterComposerPanel')?.textContent?.trim() || null,
              composerHtmlSnippet: composerElement?.innerHTML?.slice(0, 2000) || null,
            },
            pageErrors,
            consoleErrors,
          };
        }, { pageErrors, consoleErrors, clickedCreateButton, clickError });
      }`,
    }, {
      timeout: 10 * 60 * 1000,
      maxTotalTimeout: 15 * 60 * 1000,
    });

    console.log(extractText(result));
  } finally {
    await client.close();
  }
}

main().catch((error) => {
  console.error('[mcp-inspect-barter-composer] ' + String(error?.message || error));
  process.exit(1);
});
