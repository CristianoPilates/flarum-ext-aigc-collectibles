#!/usr/bin/env node

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
  const client = await createClient();

  try {
    const result = await client.callTool(
      'browser_run_code',
      {
        code: `async (page) => {
          const BASE_URL = ${JSON.stringify(forumUrl)};
          const OUTPUT_DIR = ${JSON.stringify(outputDir)};
          const WAIT_MS = 900;
          const dialogs = [];

          try {
            const isolatedPage = await page.context().newPage();
            await page.close().catch(() => {});
            page = isolatedPage;
          } catch (error) {
            dialogs.push({
              type: 'warning',
              message: '[mcp-validate-barter-composer] failed to create isolated page: ' + String(error?.message || error),
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
                      content: 'Barter composer bootstrap ' + Date.now(),
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

          async function openComposerAsBuyer(dialogId) {
            await login('buyer');
            await goto('/messages/dialog/' + dialogId);

            const threadPanel = page.locator('.BarterThreadPanel').first();
            await threadPanel.waitFor({ state: 'visible', timeout: 15000 });

            const beforeProposalId = await threadPanel
              .locator('.BarterProposalCard')
              .first()
              .getAttribute('data-proposal-id')
              .catch(() => null);

            await threadPanel.locator('.Button--primary').filter({ has: page.locator('.fa-handshake') }).first().click();

            const composerPanel = page.locator('.BarterComposerPanel').first();
            await composerPanel.waitFor({ state: 'visible', timeout: 15000 });

            const modalVisible = await page.locator('.CreateBarterProposalModal').first().isVisible().catch(() => false);
            if (modalVisible) {
              throw new Error('Legacy barter modal is still visible');
            }

            const loading = composerPanel.locator('.BarterComposerPanel-loading').first();
            const loadingVisible = await loading.isVisible().catch(() => false);
            if (loadingVisible) {
              await loading.waitFor({ state: 'hidden', timeout: 15000 }).catch(() => {});
            }

            const columns = composerPanel.locator('.BarterComposerPanel-column');
            const myOptions = columns.nth(0).locator('input[type="checkbox"]');
            const theirOptions = columns.nth(1).locator('input[type="checkbox"]');

            await page.waitForFunction(() => {
              const columns = document.querySelectorAll('.BarterComposerPanel-column');

              if (columns.length < 2) {
                return false;
              }

              const myCount = columns[0].querySelectorAll('input[type="checkbox"]').length;
              const theirCount = columns[1].querySelectorAll('input[type="checkbox"]').length;

              return myCount > 0 && theirCount > 0;
            }, undefined, { timeout: 15000 });

            const myCount = await myOptions.count();
            const theirCount = await theirOptions.count();

            if (myCount < 1 || theirCount < 1) {
              const debug = await page.evaluate(() => ({
                panelError: document.querySelector('.BarterComposerPanel-error')?.textContent?.trim() || null,
                empties: Array.from(document.querySelectorAll('.BarterComposerPanel-empty')).map((node) => node.textContent?.trim() || ''),
              }));

              throw new Error('Composer barter panel does not expose both sides assets: ' + JSON.stringify({
                myCount,
                theirCount,
                debug,
              }));
            }

            await myOptions.nth(0).evaluate((input) => {
              input.click();
            });
            await theirOptions.nth(0).evaluate((input) => {
              input.click();
            });

            const editor = page.locator('.TextEditor-editor').first();
            await editor.waitFor({ state: 'visible', timeout: 15000 });

            const messageText = 'Composer barter validation ' + new Date().toISOString();
            await editor.fill(messageText);

            await page.locator('.TextEditor-controls .Button--primary').first().evaluate((button) => {
              button.click();
            });

            await page.waitForFunction(
              ({ previousId }) => {
                const card = document.querySelector('.BarterProposalCard');

                if (!card) {
                  return false;
                }

                const proposalId = card.getAttribute('data-proposal-id');

                return Boolean(proposalId) && proposalId !== previousId;
              },
              { previousId: beforeProposalId },
              { timeout: 15000 }
            );

            const proposalId = await page.locator('.BarterProposalCard').first().getAttribute('data-proposal-id');

            const summary = await page.evaluate(() => {
              const proposal = document.querySelector('.BarterProposalCard');
              const messageNodes = Array.from(document.querySelectorAll('.MessageStream .Post-body, .MessageStream .Post-content, .MessageStream .comment-post'));

              return {
                proposalId: proposal?.getAttribute('data-proposal-id') || null,
                composerVisible: !!document.querySelector('.BarterComposerPanel'),
                proposalStatus: proposal?.querySelector('.BarterProposalCard-status')?.textContent?.trim() || null,
                proposalActions: Array.from(proposal?.querySelectorAll('.BarterProposalCard-actions .Button') || []).map((button) => button.textContent?.trim() || ''),
                latestMessages: messageNodes.slice(-3).map((node) => node.textContent?.trim() || ''),
              };
            });

            await page.screenshot({
              path: OUTPUT_DIR.replace(/\\/$/, '') + '/barter-composer-buyer.png',
              fullPage: true,
            });

            return {
              proposalId,
              myCount,
              theirCount,
              ...summary,
            };
          }

          async function acceptAsSeller(dialogId, proposalId) {
            await login('seller');
            await goto('/messages/dialog/' + dialogId);

            const proposalCard = page.locator(\`.BarterProposalCard[data-proposal-id="\${proposalId}"]\`).first();
            await proposalCard.waitFor({ state: 'visible', timeout: 15000 });

            const acceptButton = proposalCard.locator('.Button--primary').first();
            await acceptButton.waitFor({ state: 'visible', timeout: 15000 });
            await acceptButton.click();

            await page.waitForFunction(
              ({ proposalId }) => {
                const proposal = document.querySelector(\`.BarterProposalCard[data-proposal-id="\${proposalId}"]\`);

                return proposal?.getAttribute('data-proposal-status') === 'completed';
              },
              { proposalId },
              { timeout: 15000 }
            );

            const sellerView = await page.evaluate(({ proposalId }) => {
              const proposal = document.querySelector(\`.BarterProposalCard[data-proposal-id="\${proposalId}"]\`);

              return {
                proposalId,
                status: proposal?.querySelector('.BarterProposalCard-status')?.textContent?.trim() || null,
                actionTexts: Array.from(proposal?.querySelectorAll('.BarterProposalCard-actions .Button') || []).map((button) => button.textContent?.trim() || ''),
              };
            }, { proposalId });

            await page.screenshot({
              path: OUTPUT_DIR.replace(/\\/$/, '') + '/barter-composer-seller.png',
              fullPage: true,
            });

            return sellerView;
          }

          await page.setViewportSize({ width: 1480, height: 1280 });

          const dialogId = await ensureDirectDialog('buyer', 'seller');
          const buyerView = await openComposerAsBuyer(dialogId);
          const sellerView = await acceptAsSeller(dialogId, buyerView.proposalId);

          return {
            dialogId,
            dialogs,
            buyerView,
            sellerView,
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
  console.error('[mcp-validate-barter-composer] ' + String(error?.message || error));
  process.exit(1);
});
