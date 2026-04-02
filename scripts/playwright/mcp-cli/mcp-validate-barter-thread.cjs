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
                      content: 'Barter validation bootstrap ' + Date.now(),
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

          async function ensureTradeableInventory(username) {
            await login(username);

            return await page.evaluate(async () => {
              const app = window.flarum?.core?.app;

              if (!app?.forum || !app?.request || !app?.session?.user) {
                throw new Error('Flarum app is not ready');
              }

              const apiUrl = app.forum.attribute('apiUrl');
              const actor = app.session.user;

              const blindBoxesPayload = await app.request({
                method: 'GET',
                url: apiUrl + '/blindboxes',
                params: {
                  'page[limit]': 20,
                  sort: '-createdAt',
                },
              });

              const blindBoxes = blindBoxesPayload?.data || [];
              const tradeableBlindBox = blindBoxes.find((item) => ['unappraised', 'appraised'].includes(item.attributes?.status));

              const collectiblesPayload = await app.request({
                method: 'GET',
                url: apiUrl + '/collectibles',
                params: {
                  'filter[owner]': actor.id(),
                  include: 'owner',
                  'page[limit]': 20,
                },
              });

              const collectibles = collectiblesPayload?.data || [];
              const completedCollectible = collectibles.find((item) => item.attributes?.status === 'completed');

              return {
                userId: Number(actor.id()),
                blindBoxId: tradeableBlindBox ? Number(tradeableBlindBox.id) : null,
                collectibleId: completedCollectible ? Number(completedCollectible.id) : null,
              };
            });
          }

          async function createProposalAsBuyer(dialogId) {
            await login('buyer');
            await goto('/messages/dialog/' + dialogId);

            const threadPanel = page.locator('.BarterThreadPanel').first();
            await threadPanel.waitFor({ state: 'visible', timeout: 15000 });

            await threadPanel.locator('.Button--primary').filter({ has: page.locator('.fa-handshake') }).first().click();

            const modal = page.locator('.CreateBarterProposalModal').first();
            await modal.waitFor({ state: 'visible', timeout: 15000 });
            const loadingBlock = modal.locator('.CreateBarterProposalModal-loading').first();
            const loadingVisible = await loadingBlock.isVisible().catch(() => false);

            if (loadingVisible) {
              await loadingBlock.waitFor({ state: 'hidden', timeout: 15000 }).catch(() => {});
              await wait(500);
            }

            const columns = modal.locator('.CreateBarterProposalModal-column');
            const myOptions = columns.nth(0).locator('input[type="checkbox"]');
            const theirOptions = columns.nth(1).locator('input[type="checkbox"]');

            const myCount = await myOptions.count();
            const theirCount = await theirOptions.count();

            if (myCount < 1 || theirCount < 1) {
              const debugState = await page.evaluate(() => ({
                error: document.querySelector('.CreateBarterProposalModal-error')?.textContent?.trim() || null,
                emptyStates: Array.from(document.querySelectorAll('.CreateBarterProposalModal-empty')).map((node) => node.textContent?.trim() || ''),
                optionTexts: Array.from(document.querySelectorAll('.CreateBarterProposalModal-option')).map((node) => node.textContent?.trim() || ''),
              }));

              throw new Error('Barter modal does not expose both-side selectable assets: ' + JSON.stringify({
                myCount,
                theirCount,
                debugState,
              }));
            }

            await myOptions.nth(0).check();
            await theirOptions.nth(0).check();

            const textarea = modal.locator('textarea.FormControl').first();
            await textarea.fill('Thread barter validation ' + new Date().toISOString());

            await modal.locator('.CreateBarterProposalModal-actions .Button--primary').first().click();
            await modal.waitFor({ state: 'hidden', timeout: 15000 });

            const proposalCard = page.locator('.BarterProposalCard').first();
            await proposalCard.waitFor({ state: 'visible', timeout: 15000 });

            await page.screenshot({
              path: OUTPUT_DIR.replace(/\\/$/, '') + '/barter-buyer-thread.png',
              fullPage: true,
            });

            const proposalSummary = await page.evaluate(() => {
              const proposal = document.querySelector('.BarterProposalCard');
              const status = proposal?.querySelector('.BarterProposalCard-status')?.textContent?.trim() || null;
              const message = proposal?.querySelector('.BarterProposalCard-message')?.textContent?.trim() || null;

              return {
                status,
                message,
              };
            });

            return proposalSummary;
          }

          async function acceptProposalAsSeller(dialogId) {
            await login('seller');
            await goto('/messages/dialog/' + dialogId);

            const proposalCard = page.locator('.BarterProposalCard').first();
            await proposalCard.waitFor({ state: 'visible', timeout: 15000 });

            const acceptButton = proposalCard.locator('.Button--primary').first();
            await acceptButton.waitFor({ state: 'visible', timeout: 15000 });
            await acceptButton.click();

            await page.locator('.BarterProposalCard-status--completed').first().waitFor({ state: 'visible', timeout: 15000 });

            await page.screenshot({
              path: OUTPUT_DIR.replace(/\\/$/, '') + '/barter-seller-thread.png',
              fullPage: true,
            });

            return await page.evaluate(() => {
              const status = document.querySelector('.BarterProposalCard-status')?.textContent?.trim() || null;
              const actionTexts = Array.from(document.querySelectorAll('.BarterProposalCard-actions .Button')).map((button) => button.textContent?.trim() || '');

              return {
                status,
                actionTexts,
              };
            });
          }

          await page.setViewportSize({ width: 1480, height: 1280 });

          const dialogId = await ensureDirectDialog('buyer', 'seller');
          const buyerInventory = await ensureTradeableInventory('buyer');
          const sellerInventory = await ensureTradeableInventory('seller');

          if (!buyerInventory.blindBoxId) {
            throw new Error('Buyer has no tradeable blind box');
          }

          if (!sellerInventory.blindBoxId && !sellerInventory.collectibleId) {
            throw new Error('Seller has no tradeable assets');
          }

          const buyerView = await createProposalAsBuyer(dialogId);
          const sellerView = await acceptProposalAsSeller(dialogId);

          return {
            dialogId,
            buyerInventory,
            sellerInventory,
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
  console.error('[mcp-validate-barter-thread] ' + String(error?.message || error));
  process.exit(1);
});
