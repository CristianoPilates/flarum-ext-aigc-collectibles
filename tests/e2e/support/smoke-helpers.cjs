const path = require('node:path');
const { execFile } = require('node:child_process');
const { promisify } = require('node:util');

const execFileAsync = promisify(execFile);

const TEST_MNEMONIC = 'test test test test test test test test test test test junk';
const ACCOUNT_INDEX_BY_ADDRESS = {
  '0xf39fd6e51aad88f6f4ce6ab8827279cfffb92266': 0,
  '0x70997970c51812dc3a010c7d01b50e0d17dc79c8': 1,
  '0x3c44cdddb6a900fa2b585dd299e03d12fa4293bc': 2,
};

const INIT_SCRIPT_PATH = path.join(__dirname, 'playwright-mcp-init-script.js');
const BASE_URL = process.env.FORUM_URL || 'http://127.0.0.1:8080';

function normalizeAddress(value) {
  return typeof value === 'string' ? value.trim().toLowerCase() : '';
}

async function signWithCast(message, address) {
  const mnemonicIndex = ACCOUNT_INDEX_BY_ADDRESS[normalizeAddress(address)];

  if (mnemonicIndex === undefined) {
    throw new Error(`Unsupported mock wallet address: ${address}`);
  }

  const { stdout } = await execFileAsync('cast', [
    'wallet',
    'sign',
    '--mnemonic',
    TEST_MNEMONIC,
    '--mnemonic-index',
    String(mnemonicIndex),
    message,
  ]);

  return stdout.trim();
}

async function createSmokeSession(browser) {
  const context = await browser.newContext({
    viewport: { width: 1440, height: 960 },
    ignoreHTTPSErrors: true,
  });

  await context.addInitScript({ path: INIT_SCRIPT_PATH });

  const page = await context.newPage();
  await page.exposeFunction('__pwMockPersonalSign', signWithCast);

  return { context, page };
}

async function gotoApp(page, pathname = '/') {
  const target = new URL(pathname, BASE_URL).toString();
  await page.goto(target);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(500);
}

async function currentUsername(page) {
  return await page.evaluate(() => {
    return window.flarum?.core?.app?.session?.user?.username?.() || null;
  });
}

async function logout(page, context) {
  await page.evaluate(async () => {
    const csrfToken = window.flarum?.core?.app?.session?.csrfToken;
    if (csrfToken) {
      await fetch('/logout', {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrfToken },
      });
    }
  }).catch(() => {});

  await context.clearCookies();
  await gotoApp(page, '/');
}

async function login(page, context, username, password = 'password') {
  await gotoApp(page, '/');

  const sessionDropdown = page.locator('.SessionDropdown');
  if (await sessionDropdown.isVisible().catch(() => false)) {
    if ((await currentUsername(page)) === username) {
      return;
    }

    await logout(page, context);
  }

  const logInButton = page
    .locator('.item-logIn .Button, header .Button:has-text("Log In")')
    .first();

  if (await logInButton.isVisible().catch(() => false)) {
    await logInButton.click();
    await page.waitForTimeout(750);
  }

  const identification = page
    .locator('.LogInModal input[name="identification"], .Modal input[name="identification"]')
    .first();

  if (await identification.isVisible().catch(() => false)) {
    await identification.fill(username);
    await page
      .locator('.LogInModal input[type="password"], .Modal input[type="password"]')
      .first()
      .fill(password);
    await page.locator('.LogInModal .Button--primary, .Modal .Button--primary').first().click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);
    return;
  }

  const response = await page.evaluate(async (credentials) => {
    const request = await fetch('/api/token', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ...credentials, remember: true }),
    });

    return await request.json();
  }, { identification: username, password });

  if (!response.token) {
    throw new Error(`Login failed for ${username}`);
  }

  await context.addCookies([
    {
      name: 'flarum_remember',
      value: response.token,
      domain: '127.0.0.1',
      path: '/',
    },
  ]);

  await gotoApp(page, '/');
}

async function waitForModal(page) {
  const modal = page.locator('.Modal, .ModalManager .Modal').first();
  await modal.waitFor({ state: 'visible', timeout: 10_000 });
  return modal;
}

async function closeModalIfPresent(page) {
  const closeButton = page.locator('.Modal-close').first();
  if (await closeButton.isVisible().catch(() => false)) {
    await closeButton.click();
    await page.waitForTimeout(300);
  }
}

module.exports = {
  BASE_URL,
  closeModalIfPresent,
  createSmokeSession,
  currentUsername,
  gotoApp,
  login,
  logout,
  waitForModal,
};
