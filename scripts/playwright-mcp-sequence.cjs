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

function readSteps(raw) {
  if (raw === "-") {
    return JSON.parse(fs.readFileSync(0, "utf8"));
  }

  return JSON.parse(fs.readFileSync(raw, "utf8"));
}

async function main() {
  const input = process.argv[2];

  if (!input) {
    throw new Error(
      "Usage: node scripts/playwright-mcp-sequence.cjs <steps.json|' - ' for stdin>"
    );
  }

  const steps = readSteps(input);
  if (!Array.isArray(steps)) {
    throw new Error("Expected a JSON array of steps.");
  }

  const bundlePath = findMcpBundlePath();
  const { Client, StreamableHTTPClientTransport } = require(bundlePath);
  const transport = new StreamableHTTPClientTransport(
    new URL(process.env.PLAYWRIGHT_MCP_URL || "http://localhost:8931/mcp")
  );
  const client = new Client({
    name: "flarum-ext-aigc-collectibles-mcp-sequence",
    version: "0.0.1",
  });

  await client.connect(transport);

  try {
    const results = [];

    for (const [index, step] of steps.entries()) {
      if (!step || typeof step.name !== "string") {
        throw new Error(`Step ${index} is missing a string 'name'.`);
      }

      const result = await client.callTool({
        name: step.name,
        arguments: step.arguments || {},
      });

      results.push({
        index,
        name: step.name,
        result,
      });
    }

    process.stdout.write(`${JSON.stringify(results, null, 2)}\n`);
  } finally {
    await transport.close().catch(() => {});
    await client.close().catch(() => {});
  }
}

main().catch((error) => {
  process.stderr.write(`${error.stack || error.message}\n`);
  process.exit(1);
});
