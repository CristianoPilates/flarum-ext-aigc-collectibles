#!/usr/bin/env node

const path = require('node:path');
const { chromium } = require('playwright');

function getRequiredEnv(name) {
  const value = process.env[name];
  if (!value) {
    throw new Error(`${name} is required`);
  }

  return value;
}

function getSecretWords() {
  const raw = getRequiredEnv('METAMASK_SECRET_RECOVERY_PHRASE');

  const words = raw
    .trim()
    .split(/\s+/)
    .map((word) => word.trim())
    .filter(Boolean);

  if (words.length !== 12) {
    throw new Error(`Expected 12 recovery words, got ${words.length}`);
  }

  return words;
}

function parseExtensionDirs(rawValue) {
  if (!rawValue) {
    return [];
  }

  return rawValue
    .split(path.delimiter)
    .map((segment) => segment.trim())
    .filter(Boolean)
    .map((segment) => path.resolve(segment));
}

async function findExtensionId(context, timeoutMs = 10_000) {
  const deadline = Date.now() + timeoutMs;

  while (Date.now() < deadline) {
    const worker = context
      .serviceWorkers()
      .find((item) => item.url().startsWith('chrome-extension://'));

    if (worker) {
      return new URL(worker.url()).host;
    }

    await new Promise((resolve) => setTimeout(resolve, 250));
  }

  throw new Error('MetaMask service worker not found');
}

async function clickFirstVisible(page, selectors) {
  for (const selector of selectors) {
    const locator = page.locator(selector).first();
    if (await locator.isVisible().catch(() => false)) {
      await locator.click();
      return selector;
    }
  }

  return null;
}

async function fillFirstVisible(page, selectors, value) {
  for (const selector of selectors) {
    const locator = page.locator(selector).first();
    if (await locator.isVisible().catch(() => false)) {
      await locator.fill(value);
      return selector;
    }
  }

  return null;
}

async function ensureOnboarding(page) {
  await page.waitForLoadState('domcontentloaded');
  await page.waitForTimeout(1500);

  const alreadyUnlocked = await page.locator('input[data-testid="unlock-password"]').isVisible().catch(() => false);
  if (alreadyUnlocked) {
    console.log('[metamask-import] wallet already present; skipping import');
    return false;
  }

  await clickFirstVisible(page, [
    'button:has-text("Get started")',
    'button:has-text("Get Started")',
    '[data-testid="onboarding-get-started"]',
  ]);

  await clickFirstVisible(page, [
    'button:has-text("No thanks")',
    'button:has-text("I agree")',
    'button:has-text("Agree")',
    '[data-testid="metametrics-no-thanks"]',
    '[data-testid="metametrics-i-agree"]',
  ]);

  const imported = await clickFirstVisible(page, [
    'button:has-text("Import an existing wallet")',
    'button:has-text("Import wallet")',
    '[data-testid="onboarding-import-wallet"]',
  ]);

  if (!imported) {
    console.log('[metamask-import] import entry not found; assuming wallet may already be initialized');
    return false;
  }

  return true;
}

async function importWallet(page, secretWords, password) {
  for (let index = 0; index < secretWords.length; index += 1) {
    const value = secretWords[index];
    const filled = await fillFirstVisible(page, [
      `input[data-testid="import-srp__srp-word-${index}"]`,
      `input[id="import-srp__srp-word-${index}"]`,
      `input[data-testid="import-srp__srp-word-${index + 1}"]`,
      `input[id="import-srp__srp-word-${index + 1}"]`,
      `input[autocomplete="section-srp word ${index + 1}"]`,
      `input[type="text"] >> nth=${index}`,
      `input[inputmode="text"] >> nth=${index}`,
    ], value);

    if (!filled) {
      throw new Error(`Failed to locate SRP input ${index + 1}`);
    }
  }

  await clickFirstVisible(page, [
    'button:has-text("Confirm Secret Recovery Phrase")',
    'button:has-text("Import wallet")',
    '[data-testid="import-srp-confirm"]',
  ]);

  await page.waitForTimeout(1000);

  const passwordFilled = await fillFirstVisible(page, [
    'input[data-testid="create-password-new"]',
    'input[id="create-password-new"]',
    'input[name="password"]',
  ], password);

  const confirmFilled = await fillFirstVisible(page, [
    'input[data-testid="create-password-confirm"]',
    'input[id="create-password-confirm"]',
    'input[name="confirm-password"]',
  ], password);

  if (!passwordFilled || !confirmFilled) {
    throw new Error('Password inputs not found during MetaMask import');
  }

  await clickFirstVisible(page, [
    'input[type="checkbox"]',
    '[data-testid="create-password-terms"]',
  ]);

  await clickFirstVisible(page, [
    'button:has-text("Import my wallet")',
    'button:has-text("Create a new wallet")',
    'button:has-text("Restore")',
    '[data-testid="create-password-import"]',
  ]);
}

async function dismissEndScreens(page) {
  for (const _ of [1, 2, 3, 4, 5]) {
    const clicked = await clickFirstVisible(page, [
      'button:has-text("Got it")',
      'button:has-text("Done")',
      'button:has-text("Next")',
      'button:has-text("Remind me later")',
      'button:has-text("Maybe later")',
      'button:has-text("Skip")',
      '[data-testid="onboarding-complete-done"]',
    ]);

    if (!clicked) {
      break;
    }

    await page.waitForTimeout(500);
  }
}

async function main() {
  const forumUrl = getRequiredEnv('FORUM_URL');
  const userDataDir = getRequiredEnv('PLAYWRIGHT_MANUAL_USER_DATA_DIR');
  const channel = process.env.PLAYWRIGHT_MANUAL_CHANNEL || 'chromium';
  const extensionDirs = parseExtensionDirs(getRequiredEnv('PLAYWRIGHT_MANUAL_EXTENSION_DIRS'));
  const secretWords = getSecretWords();
  const password = process.env.METAMASK_PASSWORD || 'password12345';

  const args = [];
  const joined = extensionDirs.join(',');
  args.push(`--disable-extensions-except=${joined}`);
  args.push(`--load-extension=${joined}`);

  const context = await chromium.launchPersistentContext(userDataDir, {
    channel,
    headless: false,
    ignoreHTTPSErrors: true,
    viewport: { width: 1440, height: 960 },
    args,
    ignoreDefaultArgs: ['--disable-extensions'],
  });

  try {
    const extensionId = await findExtensionId(context);
    const page = context.pages()[0] ?? (await context.newPage());
    await page.goto(`chrome-extension://${extensionId}/home.html`, { waitUntil: 'load' });

    const shouldImport = await ensureOnboarding(page);
    if (shouldImport) {
      await importWallet(page, secretWords, password);
      await page.waitForTimeout(2000);
      await dismissEndScreens(page);
      await page.goto(forumUrl, { waitUntil: 'load' });
      console.log('[metamask-import] wallet imported into persistent manual profile');
    }
  } finally {
    await context.close().catch(() => {});
  }
}

main().catch((error) => {
  console.error(`[metamask-import] ${error.message}`);
  process.exit(1);
});
