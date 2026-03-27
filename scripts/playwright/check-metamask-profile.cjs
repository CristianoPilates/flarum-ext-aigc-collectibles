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

  throw new Error('extension worker not found');
}

async function main() {
  const profile = getRequiredEnv('METAMASK_PROFILE_DIR');
  const extensionDir = path.resolve(getRequiredEnv('METAMASK_EXTENSION_DIR'));

  const context = await chromium.launchPersistentContext(profile, {
    channel: 'chromium',
    headless: false,
    args: [
      `--disable-extensions-except=${extensionDir}`,
      `--load-extension=${extensionDir}`,
    ],
    ignoreDefaultArgs: ['--disable-extensions'],
  });

  try {
    const extensionId = await findExtensionId(context);
    const page = context.pages()[0] ?? (await context.newPage());

    await page.goto(`chrome-extension://${extensionId}/home.html`, { waitUntil: 'load' });
    await page.waitForTimeout(2500);

    const bodyText = (await page.locator('body').innerText()).slice(0, 4000);

    const result = {
      extensionId,
      onboarding: /Get started|Import an existing wallet|Import wallet|Secret Recovery Phrase/i.test(bodyText),
      unlock: /unlock|enter your password|password/i.test(bodyText),
      account: /Account\s*1|Assets|Activity|Tokens|NFTs/i.test(bodyText),
      textSample: bodyText.slice(0, 1200),
    };

    console.log(JSON.stringify(result, null, 2));
  } finally {
    await context.close().catch(() => {});
  }
}

main().catch((error) => {
  console.error(`[metamask-check] ${error.message}`);
  process.exit(1);
});
