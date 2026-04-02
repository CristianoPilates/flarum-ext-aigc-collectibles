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
              message: '[mcp-validate-barter-history] failed to create isolated page: ' + String(error?.message || error),
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

              const dialog = (dialogsPayload?.data || []).find((candidate) => {
                const userIds = (candidate.relationships?.users?.data || []).map((user) => Number(user.id));
                return userIds.length === 2 && userIds.includes(Number(actor.id())) && userIds.includes(Number(target.id));
              });

              if (dialog) {
                return Number(dialog.id);
              }

              const messageResponse = await app.request({
                method: 'POST',
                url: apiUrl + '/dialog-messages',
                body: {
                  data: {
                    type: 'dialog-messages',
                    attributes: {
                      content: 'Barter history bootstrap ' + Date.now(),
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

          async function createProposal(dialogId, counterpartyUsername, replacesProposalId = null) {
            return await page.evaluate(async ({ dialogId, counterpartyUsername, replacesProposalId }) => {
              const app = window.flarum?.core?.app;

              if (!app?.forum || !app?.request || !app?.session?.user) {
                throw new Error('Flarum app is not ready');
              }

              const apiUrl = app.forum.attribute('apiUrl');
              const usersPayload = await app.request({
                method: 'GET',
                url: apiUrl + '/users',
                params: {
                  filter: {
                    q: counterpartyUsername,
                  },
                },
              });

              const users = usersPayload?.data || [];
              const target = users.find((item) => item.attributes?.username === counterpartyUsername);

              if (!target) {
                throw new Error('Target user not found: ' + counterpartyUsername);
              }

              const assetsResponse = await app.request({
                method: 'GET',
                url: apiUrl + '/barter-assets',
                params: {
                  filter: {
                    threadType: 'dialog',
                    threadId: dialogId,
                    counterpartyUserId: Number(target.id),
                  },
                },
              });

              const assets = assetsResponse?.data;
              const ownAsset =
                assets?.yours?.collectibles?.[0] ||
                assets?.yours?.blindBoxes?.[0] ||
                null;
              const theirAsset =
                assets?.theirs?.collectibles?.[0] ||
                assets?.theirs?.blindBoxes?.[0] ||
                null;

              if (!ownAsset || !theirAsset) {
                throw new Error('Not enough assets available to create a barter revision');
              }

              const proposalResponse = await app.request({
                method: 'POST',
                url: apiUrl + '/barter-proposals',
                body: {
                  data: {
                    type: 'barter-proposals',
                    attributes: {
                      threadType: 'dialog',
                      threadId: Number(dialogId),
                      counterpartyUserId: Number(target.id),
                      replacesProposalId: replacesProposalId ? Number(replacesProposalId) : null,
                      items: [
                        {
                          ownerUserId: Number(ownAsset.ownerUserId),
                          assetType: ownAsset.assetType,
                          assetId: Number(ownAsset.id),
                        },
                        {
                          ownerUserId: Number(theirAsset.ownerUserId),
                          assetType: theirAsset.assetType,
                          assetId: Number(theirAsset.id),
                        },
                      ],
                    },
                  },
                },
              });

              return Number(proposalResponse?.data?.id);
            }, { dialogId, counterpartyUsername, replacesProposalId });
          }

          async function acceptProposal(proposalId) {
            return await page.evaluate(async ({ proposalId }) => {
              const app = window.flarum?.core?.app;

              if (!app?.forum || !app?.request) {
                throw new Error('Flarum app is not ready');
              }

              return await app.request({
                method: 'POST',
                url: app.forum.attribute('apiUrl') + '/barter-proposals/' + proposalId + '/accept',
              });
            }, { proposalId });
          }

          async function openThread(dialogId, username) {
            await login(username);
            await goto('/messages/dialog/' + dialogId);

            const threadPanel = page.locator('.BarterThreadPanel').first();
            await threadPanel.waitFor({ state: 'visible', timeout: 15000 });
          }

          const dialogId = await ensureDirectDialog('buyer', 'seller');

          await openThread(dialogId, 'buyer');
          const initialProposalId = await createProposal(dialogId, 'seller', null);

          await openThread(dialogId, 'seller');
          const counterProposalId = await createProposal(dialogId, 'buyer', initialProposalId);

          await openThread(dialogId, 'buyer');
          await acceptProposal(counterProposalId);
          await openThread(dialogId, 'buyer');

          const result = await page.evaluate(({ initialProposalId, counterProposalId }) => {
            const collectCard = (proposalId) => {
              const card = document.querySelector(\`.BarterProposalCard[data-proposal-id="\${proposalId}"]\`);

              if (!card) {
                return null;
              }

              return {
                proposalId: String(proposalId),
                status: card.getAttribute('data-proposal-status'),
                revisionText: card.querySelector('.BarterProposalCard-revision')?.textContent?.trim() || null,
                noteTexts: Array.from(card.querySelectorAll('.BarterProposalCard-note')).map((node) => node.textContent?.trim() || ''),
              };
            };

            return {
              initialCard: collectCard(initialProposalId),
              counterCard: collectCard(counterProposalId),
            };
          }, { initialProposalId, counterProposalId });

          if (!result.initialCard || !result.counterCard) {
            throw new Error('History cards are missing: ' + JSON.stringify(result));
          }

          const initialRevisionNumber = String(result.initialCard.revisionText || '').match(/[0-9]+/)?.[0] || null;
          const counterRevisionNumber = String(result.counterCard.revisionText || '').match(/[0-9]+/)?.[0] || null;

          if (!initialRevisionNumber || !counterRevisionNumber) {
            throw new Error('Unable to parse revision numbers from history cards: ' + JSON.stringify(result));
          }

          if (!result.counterCard.noteTexts.some((text) => text.includes(initialRevisionNumber))) {
            throw new Error('Counter revision note is missing lineage text: ' + JSON.stringify(result.counterCard));
          }

          if (
            !result.initialCard.noteTexts.some((text) => text.includes(counterRevisionNumber)) &&
            !result.initialCard.noteTexts.some((text) => /已被后续版本替代|Replaced by a later revision/.test(text))
          ) {
            throw new Error('Initial revision note is missing replacement text: ' + JSON.stringify(result.initialCard));
          }

          if (!result.counterCard.noteTexts.some((text) => /buyer 接受|Accepted by buyer/.test(text))) {
            throw new Error('Completed revision note is missing accepted-by text: ' + JSON.stringify(result.counterCard));
          }

          return {
            dialogs,
            dialogId,
            initialProposalId,
            counterProposalId,
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
  console.error('[mcp-validate-barter-history] ' + String(error?.message || error));
  process.exit(1);
});
