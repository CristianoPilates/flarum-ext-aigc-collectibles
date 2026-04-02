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
            await wait(1000);
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

          async function ensureDirectDialog() {
            await login('buyer');

            return await page.evaluate(async () => {
              const app = window.flarum?.core?.app;
              const apiUrl = app.forum.attribute('apiUrl');
              const actorId = Number(app.session.user.id());
              const usersPayload = await app.request({
                method: 'GET',
                url: apiUrl + '/users',
                params: { filter: { q: 'seller' } },
              });
              const target = (usersPayload?.data || []).find((item) => item.attributes?.username === 'seller');
              const dialogsPayload = await app.request({
                method: 'GET',
                url: apiUrl + '/dialogs',
                params: { include: 'users', 'page[limit]': 50 },
              });
              const dialogs = dialogsPayload?.data || [];
              const existing = dialogs.find((dialog) => {
                const userIds = (dialog.relationships?.users?.data || []).map((user) => Number(user.id));

                return userIds.length === 2 && userIds.includes(actorId) && userIds.includes(Number(target.id));
              });

              if (existing) {
                return Number(existing.id);
              }

              const created = await app.request({
                method: 'POST',
                url: apiUrl + '/dialog-messages',
                body: {
                  data: {
                    type: 'dialog-messages',
                    attributes: {
                      content: 'modal debug bootstrap ' + Date.now(),
                      users: [{ id: String(target.id), type: 'users' }],
                    },
                  },
                },
              });

              return Number(created?.data?.relationships?.dialog?.data?.id);
            });
          }

          const dialogId = await ensureDirectDialog();
          await goto('/messages/dialog/' + dialogId);

          return await page.evaluate((currentDialogId) => {
            const app = window.flarum?.core?.app;
            const modalState = app?.modal;
            const modalRoot = document.querySelector('#modal');

            return {
              dialogId: currentDialogId,
              url: window.location.href,
              modal: modalState?.modal
                ? {
                    key: modalState.modal.key,
                    className: modalState.modal.componentClass?.name || null,
                    attrsKeys: Object.keys(modalState.modal.attrs || {}),
                  }
                : null,
              modalList: (modalState?.modalList || []).map((modal) => ({
                key: modal.key,
                className: modal.componentClass?.name || null,
                attrsKeys: Object.keys(modal.attrs || {}),
              })),
              backdropShown: modalState?.backdropShown || false,
              loadingModal: modalState?.loadingModal || false,
              invisibleBackdrops: Array.from(document.querySelectorAll('.ModalManager-invisibleBackdrop')).map((node) => ({
                pointerEvents: window.getComputedStyle(node).pointerEvents,
                display: window.getComputedStyle(node).display,
                opacity: window.getComputedStyle(node).opacity,
              })),
              modalManagers: Array.from(document.querySelectorAll('.ModalManager')).map((node) => ({
                className: node.className,
                modalKey: node.getAttribute('data-modal-key'),
                ariaHidden: node.getAttribute('aria-hidden'),
                html: node.innerHTML.slice(0, 500),
              })),
              modalHtml: modalRoot ? modalRoot.innerHTML.slice(0, 1800) : null,
              bodyClasses: document.body.className,
              panelExists: Boolean(document.querySelector('.BarterThreadPanel')),
            };
          }, dialogId);
        }`,
      },
      {
        timeout: 120000,
        maxTotalTimeout: 180000,
      }
    );

    console.log(extractText(result));
  } finally {
    await client.close();
  }
}

main().catch((error) => {
  console.error('[mcp-inspect-barter-modal] ' + String(error?.stack || error?.message || error));
  process.exit(1);
});
