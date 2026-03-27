#!/usr/bin/env node

const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

function getRequiredEnv(name) {
  const value = process.env[name];
  if (!value) {
    throw new Error(`${name} is required`);
  }

  return value;
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

function assertDirectoryExists(dirPath) {
  const stat = fs.statSync(dirPath, { throwIfNoEntry: false });
  if (!stat || !stat.isDirectory()) {
    throw new Error(`Extension directory does not exist: ${dirPath}`);
  }
}

async function gotoForum(context, forumUrl) {
  const page = context.pages()[0] ?? (await context.newPage());
  await page.goto(forumUrl, { waitUntil: 'load' });
  return page;
}

async function waitForExtensionWorkers(context, timeoutMs) {
  const deadline = Date.now() + timeoutMs;

  while (Date.now() < deadline) {
    const workers = context
      .serviceWorkers()
      .map((worker) => worker.url())
      .filter((url) => url.startsWith('chrome-extension://'));

    if (workers.length > 0) {
      return workers;
    }

    await new Promise((resolve) => setTimeout(resolve, 250));
  }

  return context
    .serviceWorkers()
    .map((worker) => worker.url())
    .filter((url) => url.startsWith('chrome-extension://'));
}

async function main() {
  const forumUrl = getRequiredEnv('FORUM_URL');
  const userDataDir = getRequiredEnv('PLAYWRIGHT_MANUAL_USER_DATA_DIR');
  const channel = process.env.PLAYWRIGHT_MANUAL_CHANNEL || 'chromium';
  const extensionDirs = parseExtensionDirs(process.env.PLAYWRIGHT_MANUAL_EXTENSION_DIRS || '');
  const exitAfterMs = Number.parseInt(process.env.PLAYWRIGHT_MANUAL_EXIT_AFTER_MS || '', 10);

  fs.mkdirSync(userDataDir, { recursive: true });
  extensionDirs.forEach(assertDirectoryExists);

  const args = [];
  if (extensionDirs.length > 0) {
    const joined = extensionDirs.join(',');
    args.push(`--disable-extensions-except=${joined}`);
    args.push(`--load-extension=${joined}`);
  }

  console.log(`[pw-manual] channel: ${channel}`);
  console.log(`[pw-manual] profile: ${userDataDir}`);
  console.log(`[pw-manual] forum: ${forumUrl}`);
  if (extensionDirs.length === 0) {
    console.log('[pw-manual] extensions: none requested; browser still allows persisted extensions');
  } else {
    console.log(`[pw-manual] extensions: ${extensionDirs.join(', ')}`);
  }

  const context = await chromium.launchPersistentContext(userDataDir, {
    channel,
    headless: false,
    ignoreHTTPSErrors: true,
    viewport: { width: 1440, height: 960 },
    args,
    ignoreDefaultArgs: ['--disable-extensions'],
  });

  let shuttingDown = false;
  const browser = context.browser();

  const shutdown = async (reason, exitCode = 0) => {
    if (shuttingDown) {
      return;
    }
    shuttingDown = true;

    console.log(`[pw-manual] shutting down: ${reason}`);
    await context.close().catch(() => {});
    process.exit(exitCode);
  };

  process.on('SIGINT', () => {
    void shutdown('SIGINT');
  });
  process.on('SIGTERM', () => {
    void shutdown('SIGTERM');
  });

  await gotoForum(context, forumUrl);

  if (extensionDirs.length > 0) {
    const workerUrls = await waitForExtensionWorkers(context, 5_000);
    if (workerUrls.length > 0) {
      for (const workerUrl of workerUrls) {
        console.log(`[pw-manual] extension worker: ${workerUrl}`);
      }
    } else {
      console.log('[pw-manual] extension worker not observed yet; check chrome://extensions in the browser');
    }
  }

  if (Number.isFinite(exitAfterMs) && exitAfterMs > 0) {
    setTimeout(() => {
      void shutdown(`timeout ${exitAfterMs}ms`);
    }, exitAfterMs).unref();
  }

  await new Promise((resolve) => {
    browser.on('disconnected', resolve);
  });
}

main().catch((error) => {
  console.error(`[pw-manual] ${error.message}`);
  process.exit(1);
});
