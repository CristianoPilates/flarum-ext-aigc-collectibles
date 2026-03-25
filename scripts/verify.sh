#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"

"$script_dir/status.sh"
"$script_dir/chain-ready.sh"
php "$script_dir/seed-demo.php"

mysql \
  -u "${DB_USERNAME:?DB_USERNAME is required}" \
  -p"${DB_PASSWORD:?DB_PASSWORD is required}" \
  -h "${DB_HOST:?DB_HOST is required}" \
  -P "${DB_PORT:?DB_PORT is required}" \
  "${DB_DATABASE:?DB_DATABASE is required}" \
  -e "UPDATE users SET last_checkin_at = '2026-03-24 00:00:00' WHERE username = 'admin';" >/dev/null

token="$(curl -fsS "${FORUM_URL:?FORUM_URL is required}/api/token" -X POST \
  -H 'Content-Type: application/json' \
  -d '{"identification":"admin","password":"password"}' | jq -r '.token')"

if [ "$token" = "null" ] || [ -z "$token" ]; then
  echo "[verify] login failed" >&2
  exit 1
fi
echo "[verify] login ok"

checkin="$(curl -fsS "${FORUM_URL:?FORUM_URL is required}/api/checkin-records/checkin" -X POST \
  -H "Authorization: Token $token" \
  -H 'Content-Type: application/json')"
if echo "$checkin" | jq -e '.data.id' >/dev/null 2>&1; then
  echo "[verify] checkin ok"
else
  echo "[verify] checkin failed: $(echo "$checkin" | jq -r '.errors[0].detail // "unknown error"')" >&2
  exit 1
fi

collectibles="$(curl -fsS "${FORUM_URL:?FORUM_URL is required}/api/collectibles?filter[user]=1" \
  -H "Authorization: Token $token" | jq -r '.data | length')"
echo "[verify] collectibles: $collectibles"

web3_accounts="$(curl -fsS "${FORUM_URL:?FORUM_URL is required}/api/web3-accounts" \
  -H "Authorization: Token $token" | jq -r '.data | length')"
echo "[verify] web3 accounts: $web3_accounts"

ipfs_id="$(curl -fsS -X POST "${IPFS_API_ID_URL:?IPFS_API_ID_URL is required}" | jq -r '.ID // "unavailable"')"
echo "[verify] ipfs: $ipfs_id"

chain_id="$(curl -fsS -X POST "${ANVIL_RPC_URL:?ANVIL_RPC_URL is required}" \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","method":"eth_chainId","params":[],"id":1}' | jq -r '.result // "unavailable"')"
echo "[verify] anvil chain: $chain_id"

aigc_status="$(curl -fsS "${AIGC_API_HEALTH_URL:?AIGC_API_HEALTH_URL is required}" | jq -r '.status // "unavailable"')"
echo "[verify] aigc api: $aigc_status"

playwright test
