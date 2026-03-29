#!/usr/bin/env node

// Optional CLI wrapper over the running Playwright MCP server for focusing the MetaMask page.

const { createClient } = require('./mcp-client.cjs');

function extractText(result) {
  return (result.content || [])
    .filter((item) => item.type === 'text')
    .map((item) => item.text)
    .join('\n');
}

function delay(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function isRetryableTransportError(error) {
  const message = String(error?.message || error || '');
  return (
    message.includes('fetch failed') ||
    message.includes('Session not found') ||
    message.includes('ECONNRESET') ||
    message.includes('socket hang up') ||
    message.includes('UND_ERR')
  );
}

async function focusOnce() {
  const client = await createClient();

  try {
    const tools = await client.listTools();
    const toolNames = new Set((tools.tools || []).map((tool) => tool.name));

    if (!toolNames.has('browser_run_code')) {
      throw new Error('browser_run_code tool is not available');
    }

    const result = await client.callTool('browser_run_code', {
      code: `async (page) => {
        async function metaMaskPages() {
          return page.context().pages().filter((candidate) => candidate.url().startsWith('chrome-extension://'));
        }

        const extensionPages = await metaMaskPages();
        if (extensionPages.length < 1) {
          throw new Error('No MetaMask extension page is currently open in the headed MCP browser.');
        }

        const target = extensionPages[0];
        await target.waitForLoadState('domcontentloaded').catch(() => {});
        await target.evaluate(() => {
          if (!window.location.pathname.endsWith('/home.html')) {
            window.location.href = window.location.origin + '/home.html';
          }
        }).catch(() => {});
        await target.waitForLoadState('domcontentloaded').catch(() => {});
        await target.bringToFront().catch(() => {});
        await target.evaluate(() => window.focus()).catch(() => {});
        await page.waitForTimeout(500);

        const bodyText = await target.locator('body').innerText().catch(() => '');

        return {
          url: target.url(),
          title: await target.title().catch(() => null),
          bodySnippet: bodyText.slice(0, 400),
          pageCount: page.context().pages().length,
        };
      }`,
    });

    console.log(extractText(result));
  } finally {
    await client.close();
  }
}

async function main() {
  let lastError = null;

  for (let attempt = 0; attempt < 5; attempt += 1) {
    try {
      await focusOnce();
      return;
    } catch (error) {
      lastError = error;
      if (!isRetryableTransportError(error) || attempt === 4) {
        throw error;
      }
      await delay(400 * (attempt + 1));
    }
  }

  throw lastError;
}

main().catch((error) => {
  console.error('[mcp-focus-metamask] ' + String(error?.message || error));
  process.exit(1);
});
