#!/usr/bin/env bash
set -euo pipefail

# ===== Required from .env =====
: "${ANVIL_PRIVATE_KEY:?ANVIL_PRIVATE_KEY is required in .env}"

# ===== Optional =====
RPC_URL="${ANVIL_RPC_URL:-http://127.0.0.1:8545}"
PK="$ANVIL_PRIVATE_KEY"

STATE_DIR="${DEVENV_STATE:-.devenv/state}"
ENV_FILE="${CONTRACT_ENV_FILE:-$STATE_DIR/contract.env}"

# Resolve ENV_FILE to absolute path before changing directories.
# This avoids issues when DEVENV_STATE/CONTRACT_ENV_FILE are relative paths.
case "$ENV_FILE" in
  /*) ;;
  *) ENV_FILE="$(pwd)/$ENV_FILE" ;;
esac

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CHAIN_DIR="$PROJECT_ROOT/chain"
CONTRACT_PATH="src/CollectibleNFT.sol:CollectibleNFT"

if [ ! -d "$CHAIN_DIR" ]; then
  echo "[ensure-contract] ERROR: chain directory not found: $CHAIN_DIR"
  exit 1
fi

mkdir -p "$STATE_DIR"
mkdir -p "$(dirname "$ENV_FILE")"

echo "[ensure-contract] RPC_URL=$RPC_URL"
echo "[ensure-contract] ENV_FILE=$ENV_FILE"

# 1) wait anvil ready
for i in $(seq 1 60); do
  if cast chain-id --rpc-url "$RPC_URL" >/dev/null 2>&1; then
    break
  fi
  sleep 1
done
cast chain-id --rpc-url "$RPC_URL" >/dev/null

# 2) build (idempotent)
cd "$CHAIN_DIR"
forge build >/dev/null

# 3) read existing deployed address
CONTRACT_ADDR=""
if [ -f "$ENV_FILE" ]; then
  CONTRACT_ADDR="$(grep '^NFT_CONTRACT_ADDRESS=' "$ENV_FILE" | cut -d= -f2- || true)"
fi

# 4) exists + on-chain code => skip
if [ -n "$CONTRACT_ADDR" ]; then
  CODE="$(cast code "$CONTRACT_ADDR" --rpc-url "$RPC_URL" || echo "0x")"
  if [ "$CODE" != "0x" ]; then
    echo "[ensure-contract] already deployed: $CONTRACT_ADDR"
    exit 0
  fi
fi

# 5) deploy (non-idempotent op, protected by checks above)
OUT="$(forge create "$CONTRACT_PATH" \
  --rpc-url "$RPC_URL" \
  --private-key "$PK" \
  --broadcast)"

CONTRACT_ADDR="$(echo "$OUT" | sed -n 's/^Deployed to: //p' | tail -n1)"
if [ -z "$CONTRACT_ADDR" ]; then
  echo "[ensure-contract] deploy output:"
  echo "$OUT"
  echo "[ensure-contract] ERROR: no deployed address parsed"
  exit 1
fi

cat > "$ENV_FILE" <<EOF
BLOCKCHAIN_RPC_URL=$RPC_URL
NFT_CONTRACT_ADDRESS=$CONTRACT_ADDR
MINTER_PRIVATE_KEY=$PK
EOF

echo "[ensure-contract] deployed: $CONTRACT_ADDR"
echo "[ensure-contract] wrote $ENV_FILE"
