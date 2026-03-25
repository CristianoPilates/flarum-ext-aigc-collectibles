#!/usr/bin/env bash
set -euo pipefail

env_file="${CONTRACT_ENV_FILE:?CONTRACT_ENV_FILE is required}"

if [ ! -f "$env_file" ]; then
  echo "[sync-contract-settings] missing contract env: $env_file" >&2
  echo "[sync-contract-settings] run ensure-contract first" >&2
  exit 1
fi

set -a
. "$env_file"
set +a

mysql \
  -u "${DB_USERNAME:?DB_USERNAME is required}" \
  -p"${DB_PASSWORD:?DB_PASSWORD is required}" \
  -h "${DB_HOST:?DB_HOST is required}" \
  -P "${DB_PORT:?DB_PORT is required}" \
  "${DB_DATABASE:?DB_DATABASE is required}" <<SQL
INSERT INTO settings (\`key\`, \`value\`) VALUES
  ('donk-aigc-collectibles.blockchain-rpc-url', '$BLOCKCHAIN_RPC_URL'),
  ('donk-aigc-collectibles.nft-contract-address', '$NFT_CONTRACT_ADDRESS'),
  ('donk-aigc-collectibles.minter-private-key', '$MINTER_PRIVATE_KEY')
ON DUPLICATE KEY UPDATE \`value\` = VALUES(\`value\`);
SQL

echo "[sync-contract-settings] synced contract settings to Flarum DB"
