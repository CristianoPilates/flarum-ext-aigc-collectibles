const { test, expect } = require('@playwright/test');

const {
  closeModalIfPresent,
  createSmokeSession,
  currentUsername,
  gotoApp,
  login,
  waitForModal,
} = require('./support/smoke-helpers.cjs');

test.describe.serial('app smoke @smoke', () => {
  /** @type {import('@playwright/test').BrowserContext} */
  let context;
  /** @type {import('@playwright/test').Page} */
  let page;

  test.beforeAll(async ({ browser }) => {
    ({ context, page } = await createSmokeSession(browser));
  });

  test.afterAll(async () => {
    await context?.close();
  });

  test('homepage loads for guests @smoke', async () => {
    await gotoApp(page, '/');
    await expect(page.locator('body')).toBeVisible();
    await expect(page.locator('.IndexPage, .App-content, .DiscussionList').first()).toBeVisible();
  });

  test('admin can log in @smoke', async () => {
    await login(page, context, 'admin');
    await expect(page.locator('.SessionDropdown')).toBeVisible();
    await expect.poll(async () => await currentUsername(page)).toBe('admin');
  });

  test('check-in entry is available @smoke', async ({}, testInfo) => {
    await gotoApp(page, '/');

    const button = page.locator('.CheckinButton-button');
    await expect(button).toBeVisible();

    if (!(await button.isDisabled())) {
      await button.click();
      await page.waitForTimeout(1500);
    } else {
      testInfo.annotations.push({ type: 'info', description: 'admin had already checked in' });
    }

    await expect(button).toBeDisabled();
  });

  test('blind box opener is reachable @smoke', async ({}, testInfo) => {
    await gotoApp(page, '/');

    const trigger = page.locator('.BlindBoxOpener-trigger');
    await expect(trigger).toBeVisible();
    await trigger.click();

    await waitForModal(page);

    const openButton = page.locator('.BlindBoxOpener-openButton').first();
    if (await openButton.isVisible().catch(() => false) && !(await openButton.isDisabled())) {
      await openButton.click();
      await page.waitForTimeout(8000);
    } else {
      testInfo.annotations.push({ type: 'info', description: 'no blind boxes were available to open' });
    }

    await expect(page.locator('.BlindBoxOpener, .Modal-content').first()).toBeVisible();
    await closeModalIfPresent(page);
  });

  test('collectibles gallery renders on admin profile @smoke', async () => {
    await gotoApp(page, '/u/admin/collectibles');

    const cards = page.locator('.CollectibleCard');
    await expect(cards.first()).toBeVisible();
    expect(await cards.count()).toBeGreaterThan(0);
  });

  test('collectible detail modal opens @smoke', async () => {
    await gotoApp(page, '/u/admin/collectibles');

    const firstCard = page.locator('.CollectibleCard').first();
    await expect(firstCard).toBeVisible();
    await firstCard.click();

    const modal = await waitForModal(page);
    await expect(modal).toBeVisible();
    await closeModalIfPresent(page);
  });

  test('unminted completed collectible shows mint action in detail modal @smoke', async ({}, testInfo) => {
    await gotoApp(page, '/u/admin/collectibles');

    const result = await page.evaluate(async () => {
      const response = await fetch('/api/collectibles?filter[user]=1&page[limit]=50&sort=-createdAt', {
        headers: { 'Content-Type': 'application/json' },
      });
      const payload = await response.json();
      const candidate = payload.data?.find((collectible) => {
        return collectible.attributes?.status === 'completed' && !collectible.attributes?.tokenId;
      });

      return candidate
        ? { id: candidate.id, name: candidate.attributes?.name || `Collectible #${candidate.id}` }
        : null;
    });

    if (!result) {
      testInfo.annotations.push({ type: 'info', description: 'no completed unminted collectible was available' });
      return;
    }

    const card = page.locator('.CollectibleCard').filter({ hasText: result.name }).first();
    await expect(card).toBeVisible();
    await card.click();

    const modal = await waitForModal(page);
    await expect(modal.locator('.Button').filter({ hasText: 'Mint as NFT' }).first()).toBeVisible();
    await closeModalIfPresent(page);
  });

  test('wallet connector is visible on collectibles page @smoke', async () => {
    await gotoApp(page, '/u/admin/collectibles');

    const walletSection = page.locator('.WalletConnector');
    await expect(walletSection).toBeVisible();

    const address = page.locator('.WalletConnector-address').first();
    const noMetamask = page.locator('.WalletConnector-noMetaMask').first();
    const connectButton = page.locator('.WalletConnector .Button--primary').first();

    if (await address.isVisible().catch(() => false)) {
      await expect(address).toContainText('0x');
      return;
    }

    if (await noMetamask.isVisible().catch(() => false)) {
      await expect(noMetamask).toContainText(/MetaMask/i);
      return;
    }

    await expect(connectButton).toBeVisible();
  });

  test('post badge flow does not regress @smoke', async ({}, testInfo) => {
    await gotoApp(page, '/');

    const discussionLink = page.locator('.DiscussionListItem-title').first();
    if (!(await discussionLink.isVisible().catch(() => false))) {
      test.skip(true, 'No discussions are available in the seeded demo forum.');
    }

    await discussionLink.click();
    await page.waitForLoadState('networkidle');

    await expect(page.locator('.PostStream, .DiscussionPage').first()).toBeVisible();
  });

  test('collectible mint API stays callable for completed collectibles @smoke', async ({}, testInfo) => {
    await gotoApp(page, '/u/admin/collectibles');

    const result = await page.evaluate(async () => {
      const csrfToken = window.flarum?.core?.app?.session?.csrfToken || null;
      const response = await fetch('/api/collectibles?filter[user]=1', {
        headers: { 'Content-Type': 'application/json' },
      });
      const payload = await response.json();
      const unminted = payload.data?.find((collectible) => {
        return collectible.attributes?.status === 'completed' && !collectible.attributes?.tokenId;
      });

      if (!unminted) {
        return { status: 'already-minted' };
      }

      const mintResponse = await fetch(`/api/collectibles/${unminted.id}/mint`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          ...(csrfToken ? { 'X-CSRF-Token': csrfToken } : {}),
        },
      });
      const mintPayload = await mintResponse.json();

      return {
        status: mintResponse.ok ? 'minted' : 'error',
        tokenId: mintPayload.data?.attributes?.tokenId || null,
        error: mintPayload.errors?.[0]?.detail || null,
      };
    });

    expect(result.error).toBeFalsy();
    expect(['already-minted', 'minted']).toContain(result.status);

    if (result.status === 'already-minted') {
      testInfo.annotations.push({ type: 'info', description: 'all completed collectibles were already minted' });
    } else {
      expect(result.tokenId).toBeTruthy();
    }
  });

  test('buyer can browse trading pages @smoke', async () => {
    await login(page, context, 'buyer');

    await gotoApp(page, '/u/admin/collectibles');
    await expect(page).toHaveURL(/\/u\/admin\/collectibles$/);
    await expect(page.locator('.UserPage, .CollectiblesPage, .WalletConnector').first()).toBeVisible();

    await expect(page.locator('.WalletConnector, .CollectibleCard, .TradesTab').first()).toBeVisible();

    await gotoApp(page, '/u/buyer/collectibles');
    await expect(page).toHaveURL(/\/u\/buyer\/collectibles$/);
    await expect(page.locator('.UserPage, .CollectiblesPage, .WalletConnector').first()).toBeVisible();
  });

  test('buyer can send a private message to seller @smoke', async () => {
    const messageText = `Smoke PM ${Date.now()}`;

    await login(page, context, 'buyer');
    await gotoApp(page, '/messages');

    await expect(page.locator('.MessagesPage, .MessagesPage-nav').first()).toBeVisible();

    const newMessageButton = page.locator('.MessagesPage-newMessage').first();
    await expect(newMessageButton).toBeVisible();
    await newMessageButton.click();

    const composer = page.locator('.Composer').first();
    await expect(composer).toBeVisible();

    const recipientsButton = composer.locator('button').filter({ hasText: 'Recipients' }).first();
    await expect(recipientsButton).toBeVisible();
    await recipientsButton.click();

    const selectionModal = await waitForModal(page);
    const searchInput = selectionModal.locator('.UserSelectionModal-form-input input.FormControl').first();
    await searchInput.fill('seller');
    await page.waitForTimeout(900);

    const sellerItem = selectionModal.locator('.UserSelectionModal-listItem').filter({ hasText: 'seller' }).first();
    await expect(sellerItem).toBeVisible();
    await sellerItem.click();

    const selectButton = selectionModal.locator('.UserSelectionModal-form-submit .Button--primary').first();
    await expect(selectButton).toBeEnabled();
    await selectButton.click();

    const editor = composer.locator('.TextEditor-editor').first();
    await editor.fill(messageText);

    const sendButton = composer.locator('.Composer-footer .Button--primary').first();
    await expect(sendButton).toBeEnabled();
    await sendButton.click();

    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1200);

    await expect(page).toHaveURL(/\/messages\/dialog\/\d+/);
    await expect(page.locator('body')).toContainText(messageText);

    await login(page, context, 'seller');
    await gotoApp(page, '/messages');
    await expect(page.locator('body')).toContainText(messageText);
  });
});
