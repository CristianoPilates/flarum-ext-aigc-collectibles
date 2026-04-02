#!/usr/bin/env node

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
    const tools = await client.listTools();
    const toolNames = new Set((tools.tools || []).map((tool) => tool.name));

    if (!toolNames.has('browser_run_code')) {
      throw new Error('browser_run_code tool is not available');
    }

    const result = await client.callTool(
      'browser_run_code',
      {
        code: `async (page) => {
          const BASE_URL = ${JSON.stringify(forumUrl)};
          const WAIT_MS = 900;
          const dialogs = [];

          try {
            const isolatedPage = await page.context().newPage();
            await page.close().catch(() => {});
            page = isolatedPage;
          } catch (error) {
            dialogs.push({
              type: 'warning',
              message: '[mcp-validate-barter-composer-validation] failed to create isolated page: ' + String(error?.message || error),
            });
          }

          const wait = (ms) => page.waitForTimeout(ms);

          page.on('dialog', async (dialog) => {
            dialogs.push({
              type: dialog.type(),
              message: dialog.message(),
            });

            await dialog.accept().catch(() => {});
          });

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
            await wait(WAIT_MS);
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

              if (!app?.forum || !app?.request || !app?.store || !app?.session?.user) {
                throw new Error('Flarum app is not ready');
              }

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
                      content: 'Barter composer validation bootstrap ' + Date.now(),
                      users: [{ id: String(target.id), type: 'users' }],
                    },
                  },
                },
              });

              const dialogId = Number(messageResponse?.data?.relationships?.dialog?.data?.id);

              if (!dialogId) {
                throw new Error('Failed to create direct dialog');
              }

              return dialogId;
            }, { toUsername });
          }

          async function prepareComposer(dialogId) {
            await login('buyer');
            await goto('/messages/dialog/' + dialogId);

            const threadPanel = page.locator('.BarterThreadPanel').first();
            await threadPanel.waitFor({ state: 'visible', timeout: 15000 });
            await threadPanel.locator('.Button--primary').filter({ has: page.locator('.fa-handshake') }).first().click();

            const composerPanel = page.locator('.BarterComposerPanel').first();
            await composerPanel.waitFor({ state: 'visible', timeout: 15000 });

            const loading = composerPanel.locator('.BarterComposerPanel-loading').first();
            const loadingVisible = await loading.isVisible().catch(() => false);
            if (loadingVisible) {
              await loading.waitFor({ state: 'hidden', timeout: 15000 }).catch(() => {});
            }

            await page.waitForFunction(() => {
              const columns = document.querySelectorAll('.BarterComposerPanel-column');

              if (columns.length < 2) {
                return false;
              }

              const myCount = columns[0].querySelectorAll('input[type="checkbox"]').length;
              const theirCount = columns[1].querySelectorAll('input[type="checkbox"]').length;

              return myCount > 0 && theirCount > 0;
            }, undefined, { timeout: 15000 });

            const editor = page.locator('.TextEditor-editor').first();
            await editor.waitFor({ state: 'visible', timeout: 15000 });

            return {
              editor,
            };
          }

          async function setComposerSelections(mode) {
            await page.evaluate((mode) => {
              const app = window.flarum?.core?.app;
              const composer = app?.composer;
              const fields = composer?.fields;
              const payload = fields?.barterAssets?.();

              if (!composer || !fields || !payload) {
                throw new Error('Composer barter payload is not available');
              }

              const firstOwnAsset =
                payload.yours?.collectibles?.[0] ||
                payload.yours?.blindBoxes?.[0] ||
                null;
              const firstTheirAsset =
                payload.theirs?.collectibles?.[0] ||
                payload.theirs?.blindBoxes?.[0] ||
                null;

              const ownToken = firstOwnAsset ? firstOwnAsset.assetType + ':' + firstOwnAsset.id : null;
              const theirToken = firstTheirAsset ? firstTheirAsset.assetType + ':' + firstTheirAsset.id : null;

              fields.barterValidationError?.(null);

              if (mode === 'none') {
                fields.barterMySelections?.([]);
                fields.barterTheirSelections?.([]);
                fields.content?.('validation message');
              }

              if (mode === 'own-only') {
                fields.barterMySelections?.(ownToken ? [ownToken] : []);
                fields.barterTheirSelections?.([]);
                fields.content?.('validation message');
              }

              if (mode === 'both-no-message') {
                fields.barterMySelections?.(ownToken ? [ownToken] : []);
                fields.barterTheirSelections?.(theirToken ? [theirToken] : []);
                fields.content?.('');
              }

              window.m?.redraw?.();
            }, mode);
          }

          async function submitAndReadError(editor, buttonText) {
            await page.locator('.TextEditor-controls .Button--primary').first().evaluate((button) => {
              button.click();
            });
            await wait(400);

            const errorNode = page.locator('.BarterComposerPanel-error').first();
            await errorNode.waitFor({ state: 'visible', timeout: 5000 });

            const errorText = (await errorNode.innerText()).trim();
            return {
              buttonText,
              errorText,
            };
          }

          const dialogId = await ensureDirectDialog('buyer', 'seller');
          const { editor } = await prepareComposer(dialogId);

          const validationResults = [];

          await setComposerSelections('none');
          validationResults.push(await submitAndReadError(editor, 'empty-assets'));

          await setComposerSelections('own-only');
          validationResults.push(await submitAndReadError(editor, 'missing-counterparty-assets'));

          await setComposerSelections('both-no-message');
          validationResults.push(await submitAndReadError(editor, 'missing-message'));

          const result = await page.evaluate(({ dialogId, validationResults }) => {
            const panel = document.querySelector('.BarterComposerPanel');
            const errorNode = document.querySelector('.BarterComposerPanel-error');
            const summary = document.querySelector('.BarterComposerPanel-summary');

            return {
              dialogId,
              validationResults,
              finalError: errorNode?.textContent?.trim() || null,
              summaryText: summary?.textContent?.replace(/\\s+/g, ' ').trim() || null,
              composerVisible: Boolean(panel),
            };
          }, { dialogId, validationResults });

          const expectedErrors = {
            'empty-assets': [
              '至少选择一项资产。',
              'Select at least one asset.',
            ],
            'missing-counterparty-assets': [
              '协商必须同时包含双方资产。',
              'The negotiation must include assets from both sides.',
            ],
            'missing-message': [
              '请先在私信输入框里写下你要发送的话，再发送协商。',
              'Write your message in the private message box before sending the negotiation.',
            ],
          };

          for (const validation of result.validationResults) {
            const accepted = expectedErrors[validation.buttonText] || [];

            if (!accepted.includes(validation.errorText)) {
              throw new Error('Unexpected barter validation message for ' + validation.buttonText + ': ' + validation.errorText);
            }
          }

          return {
            dialogs,
            ...result,
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
  console.error('[mcp-validate-barter-composer-validation] ' + String(error?.message || error));
  process.exit(1);
});
