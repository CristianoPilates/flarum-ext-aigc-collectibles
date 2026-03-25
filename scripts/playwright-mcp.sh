#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="/home/donk/development/flarum-ext-aigc-collectibles"
OUTPUT_DIR="/tmp/playwright-mcp-output"
USER_DATA_DIR="/tmp/playwright-mcp-profile"
NODE_BIN="/nix/store/h4apbnz8p5fmjwcmzlaxcjm5vr70d97x-nodejs-slim-24.13.0/bin/node"
CAST_BIN_DIR="/nix/store/wvk2z74lf6iwhfaw3z7q8v6m690ad37v-foundry-1.5.1/bin"
PLAYWRIGHT_BROWSERS_DIR="/nix/store/gciw287d2jz2slj252cv91hgnylgq1sv-playwright-browsers"
PLAYWRIGHT_EXECUTABLE="$PLAYWRIGHT_BROWSERS_DIR/chromium-1169/chrome-linux/chrome"

if [[ -d "${HOME}/.npm/_npx" ]]; then
  MCP_CLI="$(find "${HOME}/.npm/_npx" -path '*/node_modules/@playwright/mcp/cli.js' -print -quit 2>/dev/null || true)"
else
  MCP_CLI=""
fi

mkdir -p "$OUTPUT_DIR"
mkdir -p "$USER_DATA_DIR"

export PATH="${CAST_BIN_DIR}:$PATH"
export PLAYWRIGHT_BROWSERS_PATH="$PLAYWRIGHT_BROWSERS_DIR"
export PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD="1"
export PLAYWRIGHT_SKIP_VALIDATE_HOST_REQUIREMENTS="1"
export PLAYWRIGHT_NODEJS_PATH="$NODE_BIN"
export PLAYWRIGHT_LAUNCH_OPTIONS_EXECUTABLE_PATH="$PLAYWRIGHT_EXECUTABLE"
export PLAYWRIGHT_HOST_PLATFORM_OVERRIDE="ubuntu-24.04"
export PWMCP_PROFILES_DIR_FOR_TEST="$USER_DATA_DIR"

if [[ -n "$MCP_CLI" && -f "$MCP_CLI" ]]; then
  exec "$NODE_BIN" "$MCP_CLI" \
    --headless \
    --no-sandbox \
    --browser chrome \
    --executable-path "$PLAYWRIGHT_EXECUTABLE" \
    --user-data-dir "$USER_DATA_DIR" \
    --init-page "$PROJECT_ROOT/tests/e2e/support/playwright-mcp-init-page.ts" \
    --init-script "$PROJECT_ROOT/tests/e2e/support/playwright-mcp-init-script.js" \
    --output-dir "$OUTPUT_DIR" \
    "$@"
fi

exec /home/donk/.nix-profile/bin/npx -y @playwright/mcp@latest \
  --headless \
  --no-sandbox \
  --browser chrome \
  --executable-path "$PLAYWRIGHT_EXECUTABLE" \
  --user-data-dir "$USER_DATA_DIR" \
  --init-page "$PROJECT_ROOT/tests/e2e/support/playwright-mcp-init-page.ts" \
  --init-script "$PROJECT_ROOT/tests/e2e/support/playwright-mcp-init-script.js" \
  --output-dir "$OUTPUT_DIR" \
  "$@"
