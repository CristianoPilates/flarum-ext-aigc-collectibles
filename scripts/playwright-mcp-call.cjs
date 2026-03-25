#!/usr/bin/env node

const fs = require("fs");
const path = require("path");

function findMcpBundlePath() {
  if (process.env.PLAYWRIGHT_MCP_BUNDLE_PATH) {
    return process.env.PLAYWRIGHT_MCP_BUNDLE_PATH;
  }

  const home = process.env.HOME;
  if (!home) {
    throw new Error("HOME is not set; cannot locate Playwright MCP bundle.");
  }

  const npxCacheDir = path.join(home, ".npm", "_npx");
  if (!fs.existsSync(npxCacheDir)) {
    throw new Error(`NPX cache directory not found: ${npxCacheDir}`);
  }

  for (const entry of fs.readdirSync(npxCacheDir)) {
    const candidate = path.join(
      npxCacheDir,
      entry,
      "node_modules",
      "playwright-core",
      "lib",
      "mcpBundle.js"
    );

    if (fs.existsSync(candidate)) {
      return candidate;
    }
  }

  throw new Error("Could not locate playwright-core/lib/mcpBundle.js in ~/.npm/_npx.");
}

function readArguments(raw) {
  if (!raw || raw === "{}") {
    return {};
  }

  if (raw === "-") {
    const stdin = fs.readFileSync(0, "utf8").trim();
    return stdin ? JSON.parse(stdin) : {};
  }

  return JSON.parse(raw);
}

async function main() {
  const toolName = process.argv[2];
  const rawArgs = process.argv[3] ?? "{}";

  if (!toolName) {
    throw new Error(
      "Usage: node scripts/playwright-mcp-call.cjs <toolName> [jsonArgs|' - ' for stdin]"
    );
  }

  const bundlePath = findMcpBundlePath();
  const { Client, StreamableHTTPClientTransport } = require(bundlePath);
  const transport = new StreamableHTTPClientTransport(
    new URL(process.env.PLAYWRIGHT_MCP_URL || "http://localhost:8931/mcp")
  );
  const client = new Client({
    name: "flarum-ext-aigc-collectibles-mcp-client",
    version: "0.0.1",
  });

  await client.connect(transport);

  try {
    const result = await client.callTool({
      name: toolName,
      arguments: readArguments(rawArgs),
    });

    process.stdout.write(`${JSON.stringify(result, null, 2)}\n`);
  } finally {
    await transport.close().catch(() => {});
    await client.close().catch(() => {});
  }
}

main().catch((error) => {
  process.stderr.write(`${error.stack || error.message}\n`);
  process.exit(1);
});
