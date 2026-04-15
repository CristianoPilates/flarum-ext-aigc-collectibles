const { test, expect } = require('@playwright/test');

const {
  createSmokeSession,
  login,
  gotoApp,
} = require('./support/smoke-helpers.cjs');

test.describe.serial('barter blind box overlay @barter-debug', () => {
  let context;
  let page;

  test.beforeAll(async ({ browser }) => {
    ({ context, page } = await createSmokeSession(browser));
  });

  test.afterAll(async () => {
    await context?.close();
  });

  test('inspect barter blind box data in overlay', async ({}, testInfo) => {
    const consoleLogs = [];
    const barterNetworkLogs = [];

    page.on('console', (msg) => {
      consoleLogs.push({ type: msg.type(), text: msg.text() });
    });

    page.on('response', (response) => {
      const url = response.url();
      if (url.includes('barter-assets')) {
        barterNetworkLogs.push({ url, status: response.status() });
      }
    });

    // Step 1: Login as admin
    await login(page, context, 'admin');

    // Step 2: Go to messages page
    await gotoApp(page, '/messages');
    await page.waitForTimeout(1000);

    // Step 3: Click on the first dialog link (if any)
    const dialogLinks = await page.locator('.MessagesPage-nav a[href*="/messages/dialog/"]').all();
    if (dialogLinks.length === 0) {
      // Try alternative: go to buyer profile and message from there
      await gotoApp(page, '/u/buyer');
      await page.waitForTimeout(800);

      // Try to find and click "Send Message" button
      const msgBtn = page.locator('a[href*="/messages/new"], button:has-text("Message"), a:has-text("Send Message")').first();
      if (await msgBtn.isVisible().catch(() => false)) {
        await msgBtn.click();
        await page.waitForTimeout(1500);
      }
    } else {
      await dialogLinks[0].click();
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(800);
    }

    // Step 4: Check current URL - we should be in a dialog
    const currentUrl = page.url();
    testInfo.annotations.push({ type: 'info', description: `Current URL: ${currentUrl}` });

    // Step 5: Open composer (reply placeholder) if visible
    const replyPlaceholder = page.locator('.ReplyPlaceholder, .MessageStream-replyPrompt').first();
    if (await replyPlaceholder.isVisible().catch(() => false)) {
      await replyPlaceholder.click();
      await page.waitForTimeout(1500);
      await page.waitForLoadState('networkidle');
    }

    // Step 6: Enable barter if needed by clicking the enable button
    // First check if BarterComposerPanel is visible
    const barterPanel = page.locator('.BarterComposerPanel').first();
    const barterVisible = await barterPanel.isVisible().catch(() => false);
    testInfo.annotations.push({ type: 'info', description: `BarterComposerPanel visible: ${barterVisible}` });

    if (barterVisible) {
      // Check if barter is already enabled (shows "Edit" button)
      const editBtn = page.locator('.BarterComposerPanel-editBtn').first();
      const editBtnVisible = await editBtn.isVisible().catch(() => false);

      if (!editBtnVisible) {
        // Need to enable barter first - look for enable button
        const enableBtn = page.locator('.BarterComposerPanel .Button--primary:has-text("Barter"), .BarterComposerPanel button:has-text("Enable")').first();
        if (await enableBtn.isVisible().catch(() => false)) {
          await enableBtn.click();
          await page.waitForTimeout(2000);
          await page.waitForLoadState('networkidle');
        }
      }

      // Step 7: Click the Edit button to open overlay
      const editBtnNow = page.locator('.BarterComposerPanel-editBtn').first();
      if (await editBtnNow.isVisible().catch(() => false)) {
        await editBtnNow.click();
        await page.waitForTimeout(2000);
        await page.waitForLoadState('networkidle');
      }
    }

    // Step 8: Inspect the overlay state via console
    await page.waitForTimeout(500);

    // Check if overlay is visible
    const overlayVisible = await page.locator('.BarterConfigOverlay').isVisible().catch(() => false);
    testInfo.annotations.push({ type: 'info', description: `BarterConfigOverlay visible: ${overlayVisible}` });

    // Step 9: Capture barter-related console logs
    const barterLogs = consoleLogs.filter((log) => {
      return log.text.includes('BarterConfigOverlay') ||
             log.text.includes('loadBarterAssets') ||
             log.text.includes('barter-assets') ||
             log.text.includes('blindBox') ||
             log.text.includes('blindBoxes') ||
             log.text.includes('barterMessaging');
    });

    console.log('\n=== BARTER CONSOLE LOGS ===');
    barterLogs.forEach((log) => console.log(`[${log.type}] ${log.text}`));

    // Step 10: Fetch the API directly to see what it returns
    if (barterNetworkLogs.length > 0) {
      console.log('\n=== BARTER API REQUESTS ===');
      for (const log of barterNetworkLogs) {
        console.log(`${log.status} ${log.url}`);
        try {
          const resp = await page.request.get(log.url);
          const data = await resp.json();
          console.log('Response:', JSON.stringify(data, null, 2).substring(0, 4000));
        } catch (e) {
          console.log('Error fetching:', e.message);
        }
      }
    } else {
      // No API call was made - try to manually trigger loadBarterAssets via JS
      console.log('\n=== NO API CALL MADE - INJECTING DEBUG ===');
      const debugResult = await page.evaluate(async () => {
        const app = window.flarum?.core?.app;
        if (!app) return { error: 'no flarum app' };

        const apiUrl = app.forum?.attribute('apiUrl');
        if (!apiUrl) return { error: 'no apiUrl' };

        // Find a dialog from the messages page
        const dialogs = app.store?.all('dialogs') || [];
        if (dialogs.length === 0) return { error: 'no dialogs in store', dialogCount: 0 };

        const firstDialog = dialogs[0];
        const dialogId = firstDialog?.id?.();
        const recipient = firstDialog?.recipient?.();
        const recipientId = recipient?.id?.();

        if (!dialogId || !recipientId) {
          return { error: 'missing dialogId or recipientId', dialogId, recipientId, firstDialogKeys: Object.keys(firstDialog || {}) };
        }

        // Make the API call directly
        const response = await app.request({
          method: 'GET',
          url: `${apiUrl}/barter-assets`,
          params: {
            filter: {
              thread_type: 'dialog',
              thread_id: dialogId,
              counterparty_user_id: recipientId,
            },
          },
        });

        return {
          dialogId,
          recipientId,
          hasData: !!response?.data,
          dataKeys: response?.data ? Object.keys(response.data) : [],
          yoursCollectibles: response?.data?.yours?.collectibles?.length,
          yoursBlindBoxes: response?.data?.yours?.blindBoxes?.length,
          theirsCollectibles: response?.data?.theirs?.collectibles?.length,
          theirsBlindBoxes: response?.data?.theirs?.blindBoxes?.length,
        };
      });

      console.log('Direct API call result:', JSON.stringify(debugResult, null, 2));
      testInfo.annotations.push({ type: 'info', description: `Direct API result: ${JSON.stringify(debugResult)}` });
    }

    // Step 11: Try to click the blindboxes tab in the overlay and capture logs
    if (overlayVisible) {
      const tabs = page.locator('.BarterConfigOverlay-tab').all();
      console.log(`\n=== FOUND ${tabs.length} OVERLAY TABS ===`);
      for (let i = 0; i < tabs.length; i++) {
        const tabText = await tabs[i].textContent();
        const isActive = await tabs[i].getAttribute('aria-selected');
        console.log(`Tab ${i}: "${tabText}" (active: ${isActive})`);
      }

      // Click each tab to trigger renders
      for (let i = 0; i < tabs.length; i++) {
        const tabText = await tabs[i].textContent();
        await tabs[i].click();
        await page.waitForTimeout(500);
        console.log(`Clicked tab: "${tabText}"`);
      }

      // Capture more logs after tab interactions
      const postClickLogs = consoleLogs.slice(-50);
      console.log('\n=== POST-TAB CLICK LOGS ===');
      postClickLogs.forEach((log) => {
        if (log.text.includes('BarterConfigOverlay') ||
            log.text.includes('loadBarterAssets') ||
            log.text.includes('blindBox') ||
            log.text.includes('getActiveBucket') ||
            log.text.includes('renderGrid') ||
            log.text.includes('getBucket')) {
          console.log(`[${log.type}] ${log.text}`);
        }
      });

      // Check grid state
      const gridItems = page.locator('.BarterAssetCard').all();
      console.log(`\n=== GRID ITEMS: ${gridItems.length} ===`);

      const emptyState = page.locator('.BarterConfigOverlay-empty').isVisible().catch(() => false);
      console.log(`Empty state visible: ${emptyState}`);

      if (emptyState) {
        const emptyTitle = await page.locator('.BarterConfigOverlay-emptyTitle').textContent().catch(() => 'N/A');
        console.log(`Empty message: ${emptyTitle}`);
      }
    }

    // Final summary
    testInfo.annotations.push({
      type: 'info',
      description: `Total console logs: ${consoleLogs.length}. Barter-specific: ${barterLogs.length}. Network requests: ${barterNetworkLogs.length}`,
    });
  });
});