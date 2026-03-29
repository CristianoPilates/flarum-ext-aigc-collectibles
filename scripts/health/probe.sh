#!/usr/bin/env bash
set -euo pipefail

exit_status=0

probe_required() {
  local label="$1"
  shift

  if "$@" >/dev/null 2>&1; then
    echo "[up]   $label"
  else
    echo "[down] $label"
    exit_status=1
  fi
}

probe_required "mysql" mysql \
  -u "${DB_USERNAME:?DB_USERNAME is required}" \
  -p"${DB_PASSWORD:?DB_PASSWORD is required}" \
  -h "${DB_HOST:?DB_HOST is required}" \
  -P "${DB_PORT:?DB_PORT is required}" \
  -e "SELECT 1"
probe_required "ipfs" curl -fsS -X POST "${IPFS_API_ID_URL:?IPFS_API_ID_URL is required}"
probe_required "anvil" cast chain-id --rpc-url "${ANVIL_RPC_URL:?ANVIL_RPC_URL is required}"
probe_required "forum" curl -fsS "${FORUM_URL:?FORUM_URL is required}"
probe_required "akashgen" curl -fsS "${AIGC_API_HEALTH_URL:?AIGC_API_HEALTH_URL is required}"
probe_required "frontend" pgrep -af "npm run dev|webpack --mode development --watch"

mcp_status_code="$(curl -sS -o /dev/null -w '%{http_code}' "${PLAYWRIGHT_MCP_URL:?PLAYWRIGHT_MCP_URL is required}" 2>/dev/null || true)"
if [ "$mcp_status_code" = "000" ]; then
  echo "[down] playwright-mcp"
else
  echo "[up]   playwright-mcp (HTTP $mcp_status_code)"
fi

if [ "${PROBE_STRICT:-0}" = "1" ] && [ "$exit_status" -ne 0 ]; then
  exit "$exit_status"
fi
