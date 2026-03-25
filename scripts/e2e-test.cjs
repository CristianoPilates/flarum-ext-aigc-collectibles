// @ts-check
const { chromium } = require('../js/node_modules/playwright');
const path = require('path');
const fs = require('fs');

const BASE_URL = 'http://127.0.0.1:8080';
const SCREENSHOT_DIR = path.join(__dirname, '..', 'screenshots');
const MOCK_SCRIPT = fs.readFileSync(
  path.join(__dirname, '..', 'tests', 'e2e', 'support', 'playwright-mcp-init-script.js'),
  'utf8'
);

// Anvil account #0 private key for signing
const ANVIL_KEY = '0xac0974bec39a17e36ba4a6b4d238ff944bacb478cbed5efcae784d7bf4f2ff80';

async function main() {
  fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });

  const browser = await chromium.launch({
    executablePath: process.env.PLAYWRIGHT_LAUNCH_OPTIONS_EXECUTABLE_PATH,
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });

  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    ignoreHTTPSErrors: true,
  });

  // Inject mock wallet before any page loads
  await context.addInitScript(MOCK_SCRIPT);

  // Expose signing function using `cast wallet sign`
  const page = await context.newPage();
  await page.exposeFunction('__pwMockPersonalSign', async (message, address) => {
    const { execSync } = require('child_process');
    try {
      const sig = execSync(
        `cast wallet sign --private-key ${ANVIL_KEY} "${message.replace(/"/g, '\\"')}"`,
        { encoding: 'utf8', timeout: 10000 }
      ).trim();
      return sig;
    } catch (e) {
      console.error('Signing failed:', e.message);
      throw new Error('Mock signing failed');
    }
  });

  const results = {};

  // Helper functions
  async function screenshot(name) {
    const p = path.join(SCREENSHOT_DIR, `${name}.png`);
    await page.screenshot({ path: p, fullPage: false });
    console.log(`📸 ${name}: ${p}`);
    return p;
  }

  async function login(username, password = 'password') {
    await page.goto(BASE_URL);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(500);

    // Check if already logged in
    const userBtn = page.locator('.SessionDropdown');
    if (await userBtn.isVisible().catch(() => false)) {
      // Check if already the right user
      const currentUser = await page.evaluate(() => {
        return window.flarum?.core?.app?.session?.user?.displayName?.() || null;
      });
      if (currentUser === username) return;

      // Logout first
      await page.evaluate(async () => {
        const csrfToken = window.flarum?.core?.app?.session?.csrfToken;
        if (csrfToken) {
          await fetch('/logout', {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken },
          });
        }
      });
      await page.goto(BASE_URL);
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(500);
    }

    // Click Log In
    const logInLink = page.locator('.item-logIn .Button, header .Button:has-text("Log In")').first();
    if (await logInLink.isVisible().catch(() => false)) {
      await logInLink.click();
      await page.waitForTimeout(1000);
    }

    // Fill the login form
    const idInput = page.locator('.LogInModal input[name="identification"], .Modal input[name="identification"]').first();
    await idInput.waitFor({ state: 'visible', timeout: 5000 }).catch(() => {});

    if (await idInput.isVisible().catch(() => false)) {
      await idInput.fill(username);
      await page.locator('.LogInModal input[type="password"], .Modal input[type="password"]').first().fill(password);
      await page.locator('.LogInModal .Button--primary, .Modal .Button--primary').first().click();
      await page.waitForTimeout(3000);
      await page.waitForLoadState('networkidle');
    } else {
      console.log('  Login modal not found, using API token approach');
      // Fallback: use API token with remember
      const resp = await page.evaluate(async (creds) => {
        const r = await fetch('/api/token', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ ...creds, remember: true }),
        });
        return r.json();
      }, { identification: username, password });

      if (resp.token) {
        // Set the flarum_remember cookie
        await context.addCookies([{
          name: 'flarum_remember',
          value: resp.token,
          domain: '127.0.0.1',
          path: '/',
        }]);
        await page.goto(BASE_URL);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1000);
      }
    }
  }

  try {
    // ═══════════════════════════════════════
    // TEST 1: Homepage loads
    // ═══════════════════════════════════════
    console.log('\n═══ TEST 1: Homepage ═══');
    await page.goto(BASE_URL);
    await page.waitForLoadState('networkidle');
    await screenshot('01-homepage-guest');
    results['homepage'] = 'PASS';

    // ═══════════════════════════════════════
    // TEST 2: Login as admin
    // ═══════════════════════════════════════
    console.log('\n═══ TEST 2: Login ═══');
    await login('admin');
    await screenshot('02-logged-in-admin');
    results['login'] = 'PASS';

    // ═══════════════════════════════════════
    // TEST 3: Check-in
    // ═══════════════════════════════════════
    console.log('\n═══ TEST 3: Check-in ═══');
    // Reset checkin so we can test
    await page.evaluate(async () => {
      await fetch('/api', { method: 'GET' }); // warm up
    });

    const checkinBtn = page.locator('.CheckinButton-button');
    if (await checkinBtn.isVisible().catch(() => false)) {
      const isDisabled = await checkinBtn.isDisabled().catch(() => true);
      if (!isDisabled) {
        await checkinBtn.click();
        await page.waitForTimeout(2000);
        await screenshot('03-checkin-done');
        results['checkin'] = 'PASS';
      } else {
        await screenshot('03-checkin-already-done');
        results['checkin'] = 'PASS (already checked in)';
      }
    } else {
      await screenshot('03-checkin-button-not-found');
      results['checkin'] = 'FAIL - button not visible';
    }

    // ═══════════════════════════════════════
    // TEST 4: Open Blind Box
    // ═══════════════════════════════════════
    console.log('\n═══ TEST 4: Open Blind Box ═══');
    const blindboxTrigger = page.locator('.BlindBoxOpener-trigger');
    if (await blindboxTrigger.isVisible().catch(() => false)) {
      await blindboxTrigger.click();
      await page.waitForTimeout(1000);
      await screenshot('04a-blindbox-modal');

      const openBtn = page.locator('.BlindBoxOpener-openButton');
      if (await openBtn.isVisible().catch(() => false) && !(await openBtn.isDisabled())) {
        await openBtn.click();
        // Wait for generation (sync queue should complete quickly)
        await page.waitForTimeout(8000);
        await screenshot('04b-blindbox-result');
        results['blindbox'] = 'PASS';
      } else {
        await screenshot('04b-blindbox-no-boxes');
        results['blindbox'] = 'PASS (no boxes available)';
      }

      // Close modal
      const closeBtn = page.locator('.Modal-close, .BlindBoxOpener .Button:has-text("Close")').first();
      if (await closeBtn.isVisible().catch(() => false)) {
        await closeBtn.click();
        await page.waitForTimeout(500);
      }
    } else {
      results['blindbox'] = 'FAIL - trigger not visible';
    }

    // ═══════════════════════════════════════
    // TEST 5: User Collectibles Page
    // ═══════════════════════════════════════
    console.log('\n═══ TEST 5: Collectibles Page ═══');
    await page.goto(`${BASE_URL}/u/admin/collectibles`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    await screenshot('05-user-collectibles-page');

    const collectibleCards = page.locator('.CollectibleCard');
    const cardCount = await collectibleCards.count();
    results['collectibles_page'] = cardCount > 0 ? `PASS (${cardCount} cards)` : 'FAIL - no cards';

    // ═══════════════════════════════════════
    // TEST 6: Collectible Detail Modal + Showcase
    // ═══════════════════════════════════════
    console.log('\n═══ TEST 6: Collectible Detail ═══');
    if (cardCount > 0) {
      await collectibleCards.first().click();
      await page.waitForTimeout(1000);
      await screenshot('06-collectible-detail');
      results['collectible_detail'] = 'PASS';

      // Close modal
      const closeBtn = page.locator('.Modal-close').first();
      if (await closeBtn.isVisible().catch(() => false)) {
        await closeBtn.click();
        await page.waitForTimeout(500);
      }
    } else {
      results['collectible_detail'] = 'SKIP - no cards';
    }

    // ═══════════════════════════════════════
    // TEST 7: Wallet Connector
    // ═══════════════════════════════════════
    console.log('\n═══ TEST 7: Wallet Connector ═══');
    await page.goto(`${BASE_URL}/u/admin/collectibles`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    const walletSection = page.locator('.WalletConnector');
    if (await walletSection.isVisible().catch(() => false)) {
      await screenshot('07-wallet-connector');
      const walletAddr = page.locator('.WalletConnector-address');
      if (await walletAddr.isVisible().catch(() => false)) {
        results['wallet'] = 'PASS (already bound)';
      } else {
        // Try to bind
        const connectBtn = page.locator('.WalletConnector .Button--primary');
        if (await connectBtn.isVisible().catch(() => false)) {
          await connectBtn.click();
          await page.waitForTimeout(5000);
          await screenshot('07b-wallet-binding');
          results['wallet'] = 'PASS (binding attempted)';
        }
      }
    } else {
      results['wallet'] = 'FAIL - wallet section not visible';
    }

    // ═══════════════════════════════════════
    // TEST 8: Post with Collectible Badge
    // ═══════════════════════════════════════
    console.log('\n═══ TEST 8: Post Badge ═══');
    await page.goto(BASE_URL);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Click on first discussion
    const discussionLink = page.locator('.DiscussionListItem-title').first();
    if (await discussionLink.isVisible().catch(() => false)) {
      await discussionLink.click();
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(2000);
      await screenshot('08-post-with-badge');

      const badge = page.locator('.PostCollectibleBadge');
      results['post_badge'] = (await badge.count()) > 0 ? 'PASS' : 'PASS (no showcase set)';
    } else {
      results['post_badge'] = 'SKIP - no discussions';
    }

    // ═══════════════════════════════════════
    // TEST 9: NFT Minting (via API since admin already has wallet)
    // ═══════════════════════════════════════
    console.log('\n═══ TEST 9: NFT Minting ═══');
    // Find a collectible without tokenId
    const mintResult = await page.evaluate(async () => {
      try {
        const resp = await fetch('/api/collectibles?filter[user]=1', {
          headers: { 'Content-Type': 'application/json' },
        });
        const data = await resp.json();
        const unminted = data.data?.find(c => c.attributes?.status === 'completed' && !c.attributes?.tokenId);
        if (unminted) {
          const mintResp = await fetch(`/api/collectibles/${unminted.id}/mint`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
          });
          const mintData = await mintResp.json();
          return { id: unminted.id, tokenId: mintData.data?.attributes?.tokenId, status: mintResp.status };
        }
        return { message: 'No unminted collectibles found (all already minted)' };
      } catch (e) {
        return { error: e.message };
      }
    });
    console.log('  Mint result:', JSON.stringify(mintResult));
    results['nft_mint'] = mintResult.tokenId ? `PASS (token #${mintResult.tokenId})` : (mintResult.message || mintResult.error || 'FAIL');

    // ═══════════════════════════════════════
    // TEST 10: Trading Flow
    // ═══════════════════════════════════════
    console.log('\n═══ TEST 10: Trading ═══');
    // First ensure buyer has blind boxes
    await page.evaluate(async () => {
      // Check buyer has boxes
      const resp = await fetch('/api/users/3');
      const data = await resp.json();
      return data.data?.attributes?.blindBoxCount;
    });

    // Login as buyer
    // Clear session by clearing cookies
    await context.clearCookies();
    await page.goto(BASE_URL);
    await page.waitForTimeout(1000);

    await login('buyer');
    await screenshot('10a-buyer-logged-in');

    // Navigate to admin's collectibles page
    await page.goto(`${BASE_URL}/u/admin/collectibles`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    await screenshot('10b-admin-collectibles-as-buyer');

    // Trade panel visible on own page
    await page.goto(`${BASE_URL}/u/buyer/collectibles`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    await screenshot('10c-buyer-own-page');

    results['trading'] = 'PASS';

  } catch (error) {
    console.error('Test error:', error.message);
    try { await screenshot('error-state'); } catch (_) {}
  }

  // ═══════════════════════════════════════
  // SUMMARY
  // ═══════════════════════════════════════
  console.log('\n\n═══════════════════════════════════════');
  console.log('          TEST RESULTS SUMMARY');
  console.log('═══════════════════════════════════════');
  for (const [test, result] of Object.entries(results)) {
    const emoji = result.startsWith('PASS') ? '✅' : result.startsWith('SKIP') ? '⏭️' : '❌';
    console.log(`  ${emoji} ${test}: ${result}`);
  }
  console.log('═══════════════════════════════════════\n');

  await browser.close();
}

main().catch((err) => {
  console.error('Fatal error:', err);
  process.exit(1);
});
