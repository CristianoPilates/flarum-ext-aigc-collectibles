#!/usr/bin/env node

// Shared transport helper for repository CLI wrappers that talk to a running Playwright MCP server.

const path = require('node:path');

function getRequiredEnv(name) {
  const value = process.env[name];
  if (!value) {
    throw new Error(`${name} is required`);
  }

  return value;
}

function parsePositiveInteger(value, fallback) {
  const parsed = Number.parseInt(String(value || ''), 10);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
}

function delay(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function loadPlaywrightMcpBundle() {
  const corePackageJson = require.resolve('playwright-core/package.json');
  return require(path.join(path.dirname(corePackageJson), 'lib/mcpBundle.js'));
}

class PlaywrightMcpClient {
  constructor(baseUrl) {
    this.baseUrl = baseUrl;
    this._bundle = loadPlaywrightMcpBundle();
    this._client = null;
    this._transport = null;
    this._queue = Promise.resolve();
    this._requestOptions = {
      timeout: parsePositiveInteger(process.env.PLAYWRIGHT_MCP_REQUEST_TIMEOUT_MS, 10 * 60 * 1000),
      maxTotalTimeout: parsePositiveInteger(process.env.PLAYWRIGHT_MCP_MAX_TOTAL_TIMEOUT_MS, 15 * 60 * 1000),
    };
    this._connectRetries = parsePositiveInteger(process.env.PLAYWRIGHT_MCP_CONNECT_RETRIES, 4);
    this._connectRetryDelayMs = parsePositiveInteger(process.env.PLAYWRIGHT_MCP_CONNECT_RETRY_DELAY_MS, 350);
  }

  _isRetryableTransportError(error) {
    const message = String(error?.message || error || '');
    return (
      message.includes('Session not found') ||
      message.includes('fetch failed') ||
      message.includes('ECONNRESET') ||
      message.includes('socket hang up') ||
      message.includes('other side closed') ||
      message.includes('UND_ERR')
    );
  }

  async _connectOnce() {
    if (this._transport) {
      await this._transport.close().catch(() => {});
    }

    const { Client, StreamableHTTPClientTransport } = this._bundle;

    this._transport = new StreamableHTTPClientTransport(this.baseUrl);
    this._client = new Client({
      name: 'codex-mcp-client',
      version: '1.0.0',
    });

    await this._client.connect(this._transport);
  }

  async _connectFresh() {
    let lastError = null;

    for (let attempt = 0; attempt < this._connectRetries; attempt += 1) {
      try {
        await this._connectOnce();
        return;
      } catch (error) {
        lastError = error;

        if (!this._isRetryableTransportError(error) || attempt === this._connectRetries - 1) {
          throw error;
        }

        await delay(this._connectRetryDelayMs * (attempt + 1));
      }
    }

    throw lastError;
  }

  async _ensureConnected() {
    if (!this._client || !this._transport) {
      await this._connectFresh();
    }
  }

  async _runWithReconnect(operation) {
    await this._ensureConnected();

    try {
      return await operation(this._client);
    } catch (error) {
      if (!this._isRetryableTransportError(error)) {
        throw error;
      }

      await this._connectFresh();
      return await operation(this._client);
    }
  }

  async _enqueue(operation) {
    const run = async () => await this._runWithReconnect(operation);
    const pending = this._queue.then(run, run);
    this._queue = pending.catch(() => {});
    return await pending;
  }

  _buildRequestOptions(overrides = {}) {
    return {
      ...this._requestOptions,
      ...overrides,
    };
  }

  async listTools(options = {}) {
    return await this._enqueue(async (client) => await client.listTools({}, this._buildRequestOptions(options)));
  }

  async callTool(name, args = {}, options = {}) {
    return await this._enqueue(async (client) => {
      return await client.callTool({
        name,
        arguments: args,
      }, undefined, this._buildRequestOptions(options));
    });
  }

  async close() {
    if (this._transport) {
      await this._transport.close().catch(() => {});
    }
    this._transport = null;
    this._client = null;
  }
}

async function createClient() {
  return new PlaywrightMcpClient(getRequiredEnv('PLAYWRIGHT_MCP_URL'));
}

module.exports = {
  PlaywrightMcpClient,
  createClient,
};
