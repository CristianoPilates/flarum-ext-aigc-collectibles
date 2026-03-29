#!/usr/bin/env node

// Optional CLI wrapper over the running Playwright MCP server for the minimal real NFT flow.

const { createClient } = require('./mcp-client.cjs');

function extractText(result) {
  return (result.content || [])
    .filter((item) => item.type === 'text')
    .map((item) => item.text)
    .join('\n');
}

async function main() {
  const forumUrl = process.env.FORUM_URL || 'http://127.0.0.1:8080';
  const metaMaskPassword = process.env.METAMASK_PASSWORD || null;

  const client = await createClient();
  try {
    const tools = await client.listTools();
    const toolNames = new Set((tools.tools || []).map((tool) => tool.name));

    if (!toolNames.has('browser_run_code')) {
      throw new Error('Playwright MCP browser_run_code tool is not available.');
    }

    await client.callTool('browser_resize', { width: 1440, height: 960 });

    const result = await client.callTool('browser_run_code', {
      code: `async (page) => {
      const BASE_URL = ${JSON.stringify(forumUrl)};
      const METAMASK_PASSWORD = ${JSON.stringify(metaMaskPassword)};
      let step = 'bootstrap';

      const wait = (ms) => page.waitForTimeout(ms);
      const mark = (name) => {
        step = name;
      };

      async function evaluateStable(label, expression, ...args) {
        for (let attempt = 0; attempt < 3; attempt += 1) {
          try {
            return await page.evaluate(expression, ...args);
          } catch (error) {
            if (!String(error?.message || error).includes('Execution context was destroyed') || attempt === 2) {
              throw new Error(label + ': ' + String(error?.message || error));
            }

            await page.waitForLoadState('domcontentloaded').catch(() => {});
            await wait(300);
          }
        }
      }

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
        await page.goto(appUrl(pathname), { waitUntil: 'load' });
        await page.locator('body').waitFor({ state: 'visible', timeout: 15000 });
        await wait(500);
      }

      async function currentUsername() {
        return await evaluateStable('currentUsername', () => window.flarum?.core?.app?.session?.user?.username?.() || null);
      }

      async function login(username, password = 'password') {
        mark('login');
        await goto('/');

        const loggedIn = await page.locator('.SessionDropdown').first().isVisible().catch(() => false);
        if (loggedIn && (await currentUsername()) === username) {
          return;
        }

        const loginButton = page.locator('.item-logIn .Button, header .Button:has-text("Log In")').first();
        if (await loginButton.isVisible().catch(() => false)) {
          await loginButton.click();
          await wait(500);
        }

        const identification = page.locator('.LogInModal input[name="identification"], .Modal input[name="identification"]').first();
        await identification.waitFor({ state: 'visible', timeout: 10000 });
        await identification.fill(username);
        await page.locator('.LogInModal input[type="password"], .Modal input[type="password"]').first().fill(password);
        await page.locator('.LogInModal .Button--primary, .Modal .Button--primary').first().click();
        await page.waitForLoadState('networkidle');
        await wait(1200);
      }

      async function ensureEthereumProbe() {
        await evaluateStable('ensureEthereumProbe', () => {
          window.__pwEthereumRequests = [];
          const provider = window.ethereum;
          if (!provider || provider.__pwWrapped) {
            return;
          }

          const originalRequest = provider.request.bind(provider);
          provider.request = async (args) => {
            window.__pwEthereumRequests.push({
              method: args?.method || null,
              params: Array.isArray(args?.params) ? args.params : [],
            });
            return await originalRequest(args);
          };
          provider.__pwWrapped = true;
        });
      }

      async function providerWalletState() {
        return await evaluateStable('providerWalletState', async () => {
          const provider = window.ethereum;
          let unlocked = null;
          let selectedAddress = null;
          let walletAccounts = null;
          let ethereumChainId = null;

          try {
            unlocked = await provider?._metamask?.isUnlocked?.();
          } catch (error) {
            unlocked = 'error:' + String(error?.message || error);
          }

          try {
            selectedAddress = provider?.selectedAddress || null;
          } catch (error) {
            selectedAddress = 'error:' + String(error?.message || error);
          }

          try {
            walletAccounts = await provider?.request?.({ method: 'eth_accounts' });
          } catch (error) {
            walletAccounts = 'error:' + String(error?.message || error);
          }

          try {
            ethereumChainId = await provider?.request?.({ method: 'eth_chainId' });
          } catch (error) {
            ethereumChainId = 'error:' + String(error?.message || error);
          }

          return {
            hasEthereum: Boolean(provider),
            isMetaMask: Boolean(provider?.isMetaMask),
            unlocked,
            selectedAddress,
            walletAccounts,
            ethereumChainId,
          };
        });
      }

      async function ensureAppRequestProbe() {
        await evaluateStable('ensureAppRequestProbe', () => {
          const appInstance = window.flarum?.core?.app;
          if (!appInstance?.request || appInstance.request.__pwWrapped) {
            return;
          }

          window.__pwBlindBoxOpenResult = null;
          const originalRequest = appInstance.request.bind(appInstance);

          appInstance.request = async (options) => {
            const result = await originalRequest(options);
            const method = String(options?.method || 'GET').toUpperCase();
            const url = String(options?.url || '');

            if (method === 'POST' && /\\/blindboxes\\/\\d+\\/open$/.test(url)) {
              window.__pwBlindBoxOpenResult = JSON.parse(JSON.stringify(result));
            }

            return result;
          };

          appInstance.request.__pwWrapped = true;
        });
      }

      function selectOpenableBlindBox(blindBoxesPayload) {
        const items = Array.isArray(blindBoxesPayload?.data) ? blindBoxesPayload.data : [];

        return (
          items.find((item) => item?.attributes?.status === 'appraised') ||
          items.find((item) => item?.attributes?.status === 'unappraised') ||
          null
        );
      }

      async function fetchCollectiblesForCurrentUser() {
        return await evaluateStable('fetchCollectiblesForCurrentUser', async () => {
          const userId = window.flarum?.core?.app?.session?.user?.id?.();
          const response = await fetch('/api/collectibles?filter[user]=' + userId, {
            headers: { 'Content-Type': 'application/json' },
          });

          return await response.json();
        });
      }

      async function fetchUserRecord(userId) {
        return await evaluateStable('fetchUserRecord', async (targetUserId) => {
          const response = await fetch('/api/users/' + targetUserId, {
            headers: { 'Content-Type': 'application/json' },
          });

          return await response.json();
        }, userId);
      }

      async function currentBlindBoxCount() {
        return await evaluateStable('currentBlindBoxCount', () => window.flarum?.core?.app?.session?.user?.attribute?.('blindBoxCount') || 0);
      }

      async function fetchBlindBoxesForCurrentUser() {
        return await evaluateStable('fetchBlindBoxesForCurrentUser', async () => {
          const response = await fetch('/api/blindboxes?page[limit]=50&sort=-createdAt', {
            headers: { 'Content-Type': 'application/json' },
          });

          return await response.json();
        });
      }

      async function fetchBlindBoxById(targetBlindBoxId) {
        return await evaluateStable('fetchBlindBoxById', async (blindBoxId) => {
          const response = await fetch('/api/blindboxes/' + blindBoxId, {
            headers: { 'Content-Type': 'application/json' },
          });

          return await response.json();
        }, targetBlindBoxId);
      }

      async function fetchCollectibleById(targetCollectibleId) {
        return await evaluateStable('fetchCollectibleById', async (collectibleId) => {
          const response = await fetch('/api/collectibles/' + collectibleId, {
            headers: { 'Content-Type': 'application/json' },
          });

          return await response.json();
        }, targetCollectibleId);
      }

      async function ensureBlindBoxAvailable() {
        mark('ensureBlindBoxAvailable');

        await goto('/');

        const trigger = page.locator('.BlindBoxOpener-trigger').first();
        await trigger.waitFor({ state: 'visible', timeout: 10000 });
        await trigger.click();

        const openButton = page.locator('.BlindBoxOpener-openButton').first();
        await openButton.waitFor({ state: 'visible', timeout: 10000 });

        if (!(await openButton.isDisabled())) {
          return { source: 'existing' };
        }

        const countBefore = await currentBlindBoxCount();
        const checkinButton = page.locator('.CheckinButton-button').first();

        if (await checkinButton.isVisible().catch(() => false) && !(await checkinButton.isDisabled())) {
          await page.keyboard.press('Escape').catch(() => {});
          await wait(400);
          await checkinButton.click();
          await page.waitForTimeout(1500);
          await page.waitForFunction((previousCount) => {
            const nextCount = window.flarum?.core?.app?.session?.user?.attribute?.('blindBoxCount') || 0;
            return nextCount > previousCount;
          }, countBefore, { timeout: 10000 });

          await trigger.click();
          await openButton.waitFor({ state: 'visible', timeout: 10000 });
          if (!(await openButton.isDisabled())) {
            return { source: 'checkin' };
          }
        }

        throw new Error('No blind box is currently openable, even after attempting check-in.');
      }

      async function openBlindBoxIfNeeded() {
        mark('openBlindBoxIfNeeded');
        const prepared = await ensureBlindBoxAvailable();
        const blindBoxesBefore = await fetchBlindBoxesForCurrentUser();
        const targetBlindBox = selectOpenableBlindBox(blindBoxesBefore);
        const blindBoxCountBefore = await currentBlindBoxCount();

        if (!targetBlindBox?.id) {
          throw new Error('Blind box opener was available, but no openable blind box could be identified from the API.');
        }

        const openButton = page.locator('.BlindBoxOpener-openButton').first();
        await openButton.click();
        await wait(500);

        const openedBlindBox = await (async () => {
          const deadline = Date.now() + 120000;

          while (Date.now() < deadline) {
            const [blindBoxPayload, modalState, blindBoxCountNow] = await Promise.all([
              fetchBlindBoxById(targetBlindBox.id),
              evaluateStable('blindBoxModalState', () => ({
                generating: Boolean(document.querySelector('.BlindBoxOpener-generating')),
                reveal: Boolean(document.querySelector('.BlindBoxOpener-reveal')),
                errorText: document.querySelector('.BlindBoxOpener-error')?.textContent?.trim() || null,
              })),
              currentBlindBoxCount(),
            ]);

            const blindBox = blindBoxPayload?.data || null;
            const collectibleId = blindBox?.relationships?.collectible?.data?.id || null;
            const status = blindBox?.attributes?.status || null;

            if (modalState?.errorText) {
              throw new Error('Blind box opener reported an error: ' + modalState.errorText);
            }

            if (status === 'opened' && collectibleId) {
              return {
                blindBox,
                collectibleId,
                blindBoxCountBefore,
                blindBoxCountNow,
              };
            }

            await wait(1000);
          }

          throw new Error('Blind box open did not produce an opened blind box with a collectible link before timeout.');
        })();

        const collectibleId = openedBlindBox.collectibleId;
        if (!collectibleId) {
          throw new Error('Blind box opened, but the created collectible id was missing from the opened blind box record.');
        }

        await page.waitForFunction(async (targetCollectibleId) => {
          const response = await fetch('/api/collectibles/' + targetCollectibleId, {
            headers: { 'Content-Type': 'application/json' },
          });
          const payload = await response.json();
          const status = payload?.data?.attributes?.status;
          return status === 'completed' || status === 'failed' || status === 'generating';
        }, collectibleId, { timeout: 30000 });

        const collectiblePayload = await fetchCollectibleById(collectibleId);
        const collectible = collectiblePayload?.data || null;

        if (!collectible) {
          throw new Error('Blind box flow completed, but the created collectible could not be loaded.');
        }

        if (collectible.attributes?.status === 'generating') {
          await page.waitForFunction(async (targetCollectibleId) => {
            const response = await fetch('/api/collectibles/' + targetCollectibleId, {
              headers: { 'Content-Type': 'application/json' },
            });
            const payload = await response.json();
            const status = payload?.data?.attributes?.status;
            return status === 'completed' || status === 'failed';
          }, collectibleId, { timeout: 120000 });
        }

        const completedCollectiblePayload = await fetchCollectibleById(collectibleId);
        const completedCollectible = completedCollectiblePayload?.data || collectible;

        if (completedCollectible.attributes?.status === 'failed') {
          throw new Error('Blind box generation failed for collectible ' + collectibleId + '.');
        }

        return {
          created: true,
          collectibleId: completedCollectible.id || collectibleId,
          collectibleName: completedCollectible.attributes?.name || null,
          automaticMinted: Boolean(completedCollectible.attributes?.tokenId),
          status: completedCollectible.attributes?.status || null,
          blindBoxId: openedBlindBox.blindBox?.id || targetBlindBox.id,
          blindBoxSource: prepared.source,
          blindBoxCountBefore,
          blindBoxCountAfter: openedBlindBox.blindBoxCountNow,
        };
      }

      async function inspectWalletBindingBeforeGeneration() {
        mark('inspectWalletBindingBeforeGeneration');
        await goto('/u/admin/collectibles');

        const [mePayload, providerState] = await Promise.all([
          fetchUserRecord(1),
          providerWalletState(),
        ]);
        const address = page.locator('.WalletConnector-address').first();

        return {
          wasBound: await address.isVisible().catch(() => false),
          walletText: await address.innerText().catch(() => null),
          web3Address: mePayload?.data?.attributes?.web3Address || null,
          providerState,
        };
      }

      async function metaMaskPages() {
        return page.context().pages().filter((candidate) => candidate.url().startsWith('chrome-extension://'));
      }

      async function metaMaskNotificationPages() {
        return (await metaMaskPages()).filter((candidate) => candidate.url().includes('/notification.html#/'));
      }

      async function getMetaMaskExtensionId() {
        const parseExtensionId = (value) => {
          const match = String(value || '').match(/^chrome-extension:\\/\\/([^/]+)/);
          return match ? match[1] : null;
        };

        const extensionPage = (await metaMaskPages())[0];
        if (extensionPage) {
          return parseExtensionId(extensionPage.url());
        }

        const worker = page.context().serviceWorkers().find((candidate) => candidate.url().startsWith('chrome-extension://'));
        if (worker) {
          return parseExtensionId(worker.url());
        }

        return null;
      }

      async function openMetaMaskHomePage() {
        const extensionId = await getMetaMaskExtensionId();
        if (!extensionId) {
          throw new Error('MetaMask extension is not loaded in the headed Chromium profile.');
        }

        const existing = (await metaMaskPages()).find((candidate) => candidate.url().includes(extensionId));
        if (!existing) {
          return null;
        }

        const metaMaskPage = existing;
        await metaMaskPage.waitForLoadState('domcontentloaded').catch(() => {});
        await wait(800);
        return metaMaskPage;
      }

      async function inspectMetaMaskPage(metaMaskPage) {
        const body = await metaMaskPage.locator('body').innerText().catch(() => '');

        return {
          url: metaMaskPage.url(),
          onboarding: /Get started|Import an existing wallet|Import wallet|I have an existing wallet|Create a new wallet|Secret Recovery Phrase/i.test(body),
          locked: /unlock|enter your password/i.test(body),
          accountReady: /Account\\s*1|Assets|Activity|Tokens|NFTs|Portfolio|Your wallet is ready!|Open wallet/i.test(body),
          bodySnippet: body.slice(0, 500),
        };
      }

      async function exitMetaMaskCompletionPageIfPresent(metaMaskPage, actions) {
        const openWalletButton = metaMaskPage.locator('button:has-text("Open wallet")').first();
        if (!(await openWalletButton.isVisible().catch(() => false))) {
          return false;
        }

        await openWalletButton.click();
        actions.push('button:has-text("Open wallet")');
        await wait(1500);
        return true;
      }

      async function unlockMetaMaskIfNeeded(metaMaskPage, actions) {
        const passwordInput = metaMaskPage.locator('input[data-testid="unlock-password"], input[type="password"]').first();
        if (!(await passwordInput.isVisible().catch(() => false))) {
          return { needed: false, unlocked: false };
        }

        if (!METAMASK_PASSWORD) {
          return { needed: true, unlocked: false };
        }

        await passwordInput.fill(METAMASK_PASSWORD);
        const unlockButton = metaMaskPage.locator('button:has-text("Unlock"), [data-testid="unlock-submit"]').first();
        await unlockButton.click();
        actions.push('unlock');
        await wait(1200);
        return {
          needed: true,
          unlocked: !(await passwordInput.isVisible().catch(() => false)),
        };
      }

      async function clickFirstVisible(targetPage, selectors) {
        for (const selector of selectors) {
          const locator = targetPage.locator(selector);
          const count = await locator.count().catch(() => 0);
          for (let index = 0; index < count; index += 1) {
            const candidate = locator.nth(index);
            if (!(await candidate.isVisible().catch(() => false))) {
              continue;
            }

            if ((await candidate.isDisabled().catch(() => false)) && selector !== 'input[type="checkbox"]') {
              continue;
            }

            await candidate.scrollIntoViewIfNeeded().catch(() => {});
            await candidate.click();
            return selector + '#' + index;
          }
        }

        return null;
      }

      async function fillFirstVisible(targetPage, selectors, value) {
        for (const selector of selectors) {
          const locator = targetPage.locator(selector);
          const count = await locator.count().catch(() => 0);
          for (let index = 0; index < count; index += 1) {
            const candidate = locator.nth(index);
            if (!(await candidate.isVisible().catch(() => false))) {
              continue;
            }

            await candidate.fill(value);
            return selector + '#' + index;
          }
        }

        return null;
      }

      async function clickPreferredMetaMaskAction(targetPage) {
        const locator = targetPage.locator('button, [role="button"]');
        const count = await locator.count().catch(() => 0);
        const candidates = [];

        for (let index = 0; index < count; index += 1) {
          const candidate = locator.nth(index);
          if (!(await candidate.isVisible().catch(() => false))) {
            continue;
          }

          if (await candidate.isDisabled().catch(() => false)) {
            continue;
          }

          const text = (await candidate.innerText().catch(() => '')).trim();
          const dataTestid = await candidate.getAttribute('data-testid').catch(() => null);
          const label = [text, dataTestid || ''].join(' ').trim().toLowerCase();

          candidates.push({
            index,
            text,
            dataTestid,
            label,
            candidate,
          });
        }

        if (candidates.length === 0) {
          return null;
        }

        const isNegative = (label) => /cancel|reject|close|back|disconnect|not now/.test(label);
        const positivePatterns = [
          /signature-request-footer__sign-button/,
          /page-container-footer-confirm/,
          /page-container-footer-connect/,
          /page-container-footer-approve/,
          /confirm-footer-button/,
          /confirm-btn/,
          /\bconfirm\b/,
          /\bconnect\b/,
          /\bapprove\b/,
          /\bsign\b/,
          /\bnext\b/,
          /\bdone\b/,
          /got it/,
        ];

        const pool = candidates.filter(({ label }) => !isNegative(label));
        const preferred =
          pool.find(({ label }) => positivePatterns.some((pattern) => pattern.test(label))) ||
          pool[pool.length - 1] ||
          candidates[candidates.length - 1];

        await preferred.candidate.scrollIntoViewIfNeeded().catch(() => {});
        await preferred.candidate.click();
        return 'preferred:' + (preferred.dataTestid || preferred.text || preferred.index);
      }

      async function ensureMetaMaskWalletReady() {
        mark('ensureMetaMaskWalletReady');

        const metaMaskPage = await openMetaMaskHomePage();
        const actions = [];
        const walletState = await providerWalletState();
        const providerHasAccount = Array.isArray(walletState.walletAccounts) && walletState.walletAccounts.length > 0;

        if (metaMaskPage) {
          await metaMaskPage.bringToFront().catch(() => {});
          await unlockMetaMaskIfNeeded(metaMaskPage, actions);
          await exitMetaMaskCompletionPageIfPresent(metaMaskPage, actions);
        }

        const unlockState = {
          needed: walletState.unlocked === false,
          unlocked: walletState.unlocked === true,
        };

        const state = metaMaskPage
          ? await inspectMetaMaskPage(metaMaskPage)
          : {
              url: null,
              onboarding: false,
              locked: walletState.unlocked === false,
              accountReady: providerHasAccount,
              bodySnippet: '',
            };
        if (state.onboarding) {
          throw new Error(
            'MetaMask onboarding is still showing in the shared profile. Provider state: ' +
              JSON.stringify(walletState) +
              '. Import the wallet manually in the shared manual Chromium profile before running this flow.'
          );
        }

        if (state.locked && unlockState.needed && !unlockState.unlocked) {
          throw new Error(
            METAMASK_PASSWORD
              ? 'MetaMask stayed locked after unlock attempt. Unlock it manually in the shared profile, or update METAMASK_PASSWORD to match the imported wallet.'
              : 'MetaMask is locked in the shared profile. Unlock it manually before running this flow, or export METAMASK_PASSWORD.'
          );
        }

        const completionPageReady = /Your wallet is ready!|Open wallet/i.test(state.bodySnippet);

        if (!state.accountReady && !state.locked && !(providerHasAccount && completionPageReady)) {
          throw new Error('MetaMask page did not reach an account-ready state. ' + state.bodySnippet);
        }

        await page.bringToFront().catch(() => {});
        return {
          ...state,
          walletState,
          unlockState,
          actions,
        };
      }

      async function approveMetaMaskUntil(done, timeoutMs = 120000) {
        const deadline = Date.now() + timeoutMs;
        const actions = [];

        while (Date.now() < deadline) {
          if (await done()) {
            await page.bringToFront().catch(() => {});
            return actions;
          }

          const notificationPages = await metaMaskNotificationPages();
          const candidatePool = notificationPages.length > 0 ? notificationPages : await metaMaskPages();
          const candidates = candidatePool.sort((left, right) => {
            const score = (candidate) => {
              const url = candidate.url();
              if (url.includes('/notification.html#/confirm-transaction/')) return 0;
              if (url.includes('/notification.html#/connect/')) return 1;
              if (url.includes('/notification.html#/')) return 2;
              return 3;
            };

            return score(left) - score(right);
          });
          for (const candidate of candidates) {
            await candidate.waitForLoadState('domcontentloaded').catch(() => {});
            await candidate.bringToFront().catch(() => {});
            await unlockMetaMaskIfNeeded(candidate, actions);
            await exitMetaMaskCompletionPageIfPresent(candidate, actions);

            const isNotificationPage = candidate.url().includes('/notification.html#/');
            if (!isNotificationPage) {
              continue;
            }

            const clickedCheckbox = await clickFirstVisible(candidate, [
              'input[type="checkbox"]',
              '[role="checkbox"]',
            ]);
            if (clickedCheckbox) {
              actions.push(clickedCheckbox);
              await wait(300);
            }

            const clickedAction = await clickFirstVisible(candidate, [
              'button:has-text("Next")',
              'button:has-text("Connect")',
              'button:has-text("Confirm")',
              'button:has-text("Approve")',
              'button:has-text("Sign")',
              'button:has-text("Got it")',
              'button:has-text("Done")',
              '[data-testid="confirm-footer-button"]',
              '[data-testid="confirm-btn"]',
              '[data-testid="signature-request-footer__sign-button"]',
              '[data-testid="page-container-footer-next"]',
              '[data-testid="page-container-footer-connect"]',
              '[data-testid="page-container-footer-approve"]',
              '[data-testid="page-container-footer-confirm"]',
              '[data-testid="signature-request-scroll-button"]',
            ]);

            if (clickedAction) {
              actions.push(clickedAction);
              await wait(800);
              break;
            }

            const clickedPreferredAction = await clickPreferredMetaMaskAction(candidate);
            if (clickedPreferredAction) {
              actions.push(clickedPreferredAction);
              await wait(800);
              break;
            }
          }

          await page.bringToFront().catch(() => {});
          await wait(500);
        }

        throw new Error('MetaMask confirmation did not complete before timeout.');
      }

      async function ensureWalletBound() {
        mark('ensureWalletBound');
        await goto('/u/admin/collectibles');
        await ensureEthereumProbe();

        const address = page.locator('.WalletConnector-address').first();
        if (await address.isVisible().catch(() => false)) {
          return {
            rebound: false,
            text: await address.innerText(),
            metaMaskActions: [],
          };
        }

        await ensureMetaMaskWalletReady();
        mark('ensureWalletBound');

        const currentWalletState = async () => {
          return await evaluateStable('ensureWalletBound.currentWalletState', () => ({
            addressText: document.querySelector('.WalletConnector-address')?.textContent?.trim() || null,
            connectDisabled: Boolean(document.querySelector('.WalletConnector .Button--primary[disabled]')),
            loadingText: document.querySelector('.WalletConnector-step, .WalletConnector-connect')?.textContent?.trim() || null,
          }));
        };

        const hasPendingApprovalPopup = async () => {
          const candidates = await metaMaskNotificationPages();
          return candidates.length > 0;
        };

        const connectButton = page.locator('.WalletConnector .Button--primary').first();
        await connectButton.waitFor({ state: 'visible', timeout: 10000 });
        const buttonDisabled = await connectButton.isDisabled().catch(() => false);
        const pendingApprovalPopup = await hasPendingApprovalPopup();

        if (!buttonDisabled) {
          await connectButton.click();
        } else if (!pendingApprovalPopup) {
          const state = await currentWalletState();
          throw new Error(
            'Connect Wallet button is disabled, and no pending MetaMask approval popup is open. Wallet state: ' +
              JSON.stringify(state)
          );
        }

        const metaMaskActions = await approveMetaMaskUntil(async () => await address.isVisible().catch(() => false));

        await address.waitFor({ state: 'visible', timeout: 15000 });
        const mePayload = await fetchUserRecord(1);

        return {
          rebound: true,
          text: await address.innerText(),
          web3Address: mePayload?.data?.attributes?.web3Address || null,
          metaMaskActions,
        };
      }

      async function mintFirstPendingCollectible(targetCollectibleId) {
        mark('mintFirstPendingCollectible');

        const completedCollectible = await (async () => {
          const deadline = Date.now() + 120000;

          while (Date.now() < deadline) {
            const payload = await fetchCollectibleById(targetCollectibleId);
            const collectible = payload?.data || null;
            const status = collectible?.attributes?.status || null;

            if (status === 'completed') {
              return collectible;
            }

            if (status === 'failed') {
              throw new Error('Collectible generation failed before mint. Collectible id: ' + targetCollectibleId);
            }

            await wait(1000);
          }

          throw new Error('Collectible did not reach completed status before mint timeout. Collectible id: ' + targetCollectibleId);
        })();

        async function collectibleStoreState(desiredId) {
          return await evaluateStable('mintFirstPendingCollectible.collectibleStoreState', (collectibleId) => {
            const store = window.flarum?.core?.app?.store;
            const collectible = store?.getById?.('collectibles', String(collectibleId));

            return {
              id: String(collectibleId),
              name: collectible?.attribute?.('name') || collectible?.name?.() || null,
              status: collectible?.attribute?.('status') || collectible?.status?.() || null,
              tokenId: collectible?.attribute?.('tokenId') || collectible?.tokenId?.() || null,
              canMint: collectible?.attribute?.('canMint') ?? collectible?.canMint?.() ?? null,
            };
          }, targetCollectibleId);
        }

        async function openCollectibleDetailModalById(desiredId) {
          return await evaluateStable('mintFirstPendingCollectible.openCollectibleDetailModalById', async (collectibleId) => {
            const appInstance = window.flarum?.core?.app;
            const apiUrl = appInstance?.forum?.attribute?.('apiUrl');
            const DetailModal = window.flarum?.reg?.get?.('donk-aigc-collectibles', 'forum/components/CollectibleDetailModal');

            if (!appInstance || !apiUrl || !DetailModal) {
              return {
                opened: false,
                reason: 'missing-app-or-modal',
              };
            }

            const response = await appInstance.request({
              method: 'GET',
              url: apiUrl + '/collectibles/' + collectibleId,
            });

            appInstance.store.pushPayload(response);
            const collectible = appInstance.store.getById?.('collectibles', String(collectibleId));

            if (!collectible) {
              return {
                opened: false,
                reason: 'collectible-not-in-store',
              };
            }

            appInstance.modal.show(DetailModal, {
              collectible,
              isOwnProfile: true,
              onUpdated: () => {},
            });

            window.m?.redraw?.();

            return {
              opened: true,
              status: collectible.attribute?.('status') || collectible.status?.() || null,
              tokenId: collectible.attribute?.('tokenId') || collectible.tokenId?.() || null,
              canMint: collectible.attribute?.('canMint') ?? collectible.canMint?.() ?? null,
              name: collectible.attribute?.('name') || collectible.name?.() || null,
            };
          }, desiredId);
        }

        await goto('/u/admin/collectibles');
        const targetName = 'Collectible #' + targetCollectibleId;
        const openResult = await openCollectibleDetailModalById(targetCollectibleId);
        await wait(400);

        const modal = page.locator('.CollectibleDetailModal, .CollectibleDetailModal-body').first();
        await modal.waitFor({ state: 'visible', timeout: 15000 });

        let mintButton = modal.locator('.Button').filter({ hasText: 'Mint as NFT' }).first();
        const initialMintVisible = await mintButton.isVisible().catch(() => false);

        if (!initialMintVisible) {
          await page.keyboard.press('Escape').catch(() => {});
          await wait(300);
          const reopened = await openCollectibleDetailModalById(targetCollectibleId);
          await wait(400);
          await modal.waitFor({ state: 'visible', timeout: 10000 });
          mintButton = modal.locator('.Button').filter({ hasText: 'Mint as NFT' }).first();

          if (!(await mintButton.isVisible().catch(() => false))) {
            const [state, modalText] = await Promise.all([
              collectibleStoreState(targetCollectibleId),
              modal.innerText().catch(() => ''),
            ]);

            throw new Error(
              'Mint button stayed hidden. Collectible state: ' +
                JSON.stringify(state) +
                '. Open result: ' +
                JSON.stringify(openResult) +
                '. Reopen result: ' +
                JSON.stringify(reopened) +
                '. Modal text: ' +
                String(modalText).slice(0, 600)
            );
          }
        }

        await mintButton.click();

        await page.waitForFunction((desiredId) => {
          const store = window.flarum?.core?.app?.store;
          const collectible = store?.getById?.('collectibles', String(desiredId));
          return Boolean(collectible?.attribute?.('tokenId'));
        }, targetCollectibleId, { timeout: 30000 });

        return await evaluateStable('mintFirstPendingCollectible.result', (desiredId) => {
          const store = window.flarum?.core?.app?.store;
          const collectible = store?.getById?.('collectibles', String(desiredId));

          return {
            status: collectible?.attribute?.('tokenId') ? 'minted' : 'missing',
            collectibleId: desiredId,
            tokenId: collectible?.attribute?.('tokenId') || null,
            collectibleStatus: collectible?.attribute?.('status') || collectible?.status?.() || null,
          };
        }, targetCollectibleId);
      }

      try {
        await login('admin');

        mark('guiSignal');
        const guiSignal = await evaluateStable('guiSignal', () => ({
          href: window.location.href,
          title: document.title,
          hasEthereum: Boolean(window.ethereum?.isMetaMask),
        }));

        const walletBeforeGeneration = await inspectWalletBindingBeforeGeneration();
        const opened = await openBlindBoxIfNeeded();
        if (opened.automaticMinted) {
          throw new Error('Generated collectible was auto-minted before manual mint could be exercised.');
        }

        const wallet = await ensureWalletBound();
        const minted = await mintFirstPendingCollectible(opened.collectibleId);

        mark('verification');
        const verification = await evaluateStable('verification', async (collectibleId, blindBoxId) => {
          const [collectiblesResponse, meResponse] = await Promise.all([
            fetch('/api/collectibles?filter[user]=1', { headers: { 'Content-Type': 'application/json' } }),
            fetch('/api/users/1', { headers: { 'Content-Type': 'application/json' } }),
          ]);

          const collectiblesPayload = await collectiblesResponse.json();
          const mePayload = await meResponse.json();
          const collectible = (collectiblesPayload.data || []).find((item) => String(item.id) === String(collectibleId));
          let blindBox = null;

          if (blindBoxId) {
            const blindBoxResponse = await fetch('/api/blindboxes/' + blindBoxId, {
              headers: { 'Content-Type': 'application/json' },
            });
            const blindBoxPayload = await blindBoxResponse.json();
            blindBox = blindBoxPayload?.data || null;
          }

          return {
            collectibleId,
            rarity: collectible?.attributes?.rarity || null,
            aigcPrompt: collectible?.attributes?.aigcPrompt || null,
            tokenId: collectible?.attributes?.tokenId || null,
            metadataCid: collectible?.attributes?.metadataCid || null,
            ipfsCid: collectible?.attributes?.ipfsCid || null,
            canMint: collectible?.attributes?.canMint ?? null,
            blindBoxId: blindBox?.id || blindBoxId || null,
            blindBoxBudget: blindBox?.attributes?.budget || null,
            web3Address: mePayload?.data?.attributes?.web3Address || null,
            ethereumRequests: window.__pwEthereumRequests || [],
          };
        }, minted.collectibleId, opened.blindBoxId);

        return {
          guiSignal,
          walletBeforeGeneration,
          opened,
          wallet,
          minted,
          verification,
        };
      } catch (error) {
        throw new Error('[step:' + step + '] ' + String(error?.message || error));
      }
    }`,
    });

    const text = extractText(result);
    console.log(text);
  } finally {
    await client.close();
  }
}

main().catch((error) => {
  console.error('[mcp-minimal-nft] ' + error.message);
  process.exit(1);
});
