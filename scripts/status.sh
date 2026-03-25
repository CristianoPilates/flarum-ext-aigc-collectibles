#!/usr/bin/env bash
set -euo pipefail

probe() {
  local label="$1"
  shift

  if "$@" >/dev/null 2>&1; then
    echo "[up]   $label"
  else
    echo "[down] $label"
  fi
}

probe "mysql" mysql \
  -u "${DB_USERNAME:?DB_USERNAME is required}" \
  -p"${DB_PASSWORD:?DB_PASSWORD is required}" \
  -h "${DB_HOST:?DB_HOST is required}" \
  -P "${DB_PORT:?DB_PORT is required}" \
  -e "SELECT 1"
probe "ipfs" curl -fsS -X POST "${IPFS_API_ID_URL:?IPFS_API_ID_URL is required}"
probe "anvil" cast chain-id --rpc-url "${ANVIL_RPC_URL:?ANVIL_RPC_URL is required}"
probe "forum" curl -fsS "${FORUM_URL:?FORUM_URL is required}"
probe "akashgen" curl -fsS "${AIGC_API_HEALTH_URL:?AIGC_API_HEALTH_URL is required}"

mcp_status_code="$(curl -sS -o /dev/null -w '%{http_code}' "${PLAYWRIGHT_MCP_URL:?PLAYWRIGHT_MCP_URL is required}" || true)"
if [ "$mcp_status_code" = "000" ]; then
  echo "[down] playwright-mcp"
else
  echo "[up]   playwright-mcp (HTTP $mcp_status_code)"
fi
