#!/usr/bin/env bash
# ============================================================
# dev-start.sh — One-shot startup for all AIGC Collectibles services
# Usage: bash scripts/dev-start.sh
# ============================================================
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SITE_DIR="/home/donk/development/flarum-site"
AIGC_DIR="/home/donk/development/akashgen-api-go"
STATE_DIR="${DEVENV_STATE:-$ROOT/.devenv/state}"

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

check() {
  if "$@" >/dev/null 2>&1; then
    echo -e "${GREEN}✓${NC} $1"
    return 0
  else
    echo -e "${RED}✗${NC} $1"
    return 1
  fi
}

echo "=== Starting AIGC Collectibles dev environment ==="

# 1. MySQL (managed by devenv, should already be running)
if check mysql -u flarum -pflarum -h 127.0.0.1 -e "SELECT 1"; then
  echo "  MySQL is running"
else
  echo "  ERROR: MySQL not available. Run 'devenv up' first."
  exit 1
fi

# 2. IPFS daemon
if curl -s -X POST http://127.0.0.1:5001/api/v0/id >/dev/null 2>&1; then
  echo -e "${GREEN}✓${NC} IPFS already running"
else
  echo -e "${YELLOW}→${NC} Starting IPFS daemon..."
  export IPFS_PATH="${STATE_DIR}/ipfs"
  mkdir -p "$IPFS_PATH"
  if [ ! -f "$IPFS_PATH/config" ]; then
    ipfs init --profile=server
    ipfs config Addresses.API "/ip4/127.0.0.1/tcp/5001"
    ipfs config Addresses.Gateway "/ip4/127.0.0.1/tcp/8888"
  fi
  ipfs daemon --migrate=true --enable-gc &>/tmp/ipfs-daemon.log &
  sleep 3
  check curl -s -X POST http://127.0.0.1:5001/api/v0/id
fi

# 3. Anvil (local EVM chain)
if curl -s -X POST http://127.0.0.1:8545 -H 'Content-Type: application/json' \
   -d '{"jsonrpc":"2.0","method":"eth_chainId","params":[],"id":1}' >/dev/null 2>&1; then
  echo -e "${GREEN}✓${NC} Anvil already running"
else
  echo -e "${YELLOW}→${NC} Starting Anvil..."
  ANVIL_STATE="$STATE_DIR/anvil"
  mkdir -p "$ANVIL_STATE"
  anvil --host 127.0.0.1 --port 8545 --chain-id 31337 \
    --mnemonic "test test test test test test test test test test test junk" \
    --state "$ANVIL_STATE/state.json" &>/tmp/anvil.log &
  sleep 2
  check curl -s -X POST http://127.0.0.1:8545 -H 'Content-Type: application/json' \
    -d '{"jsonrpc":"2.0","method":"eth_chainId","params":[],"id":1}'
fi

# 4. Deploy / verify NFT contract
echo -e "${YELLOW}→${NC} Ensuring NFT contract..."
bash "$ROOT/scripts/ensure-contract.sh" 2>&1 | tail -1

# 5. AIGC API (Go)
if curl -s http://127.0.0.1:6571/health >/dev/null 2>&1; then
  echo -e "${GREEN}✓${NC} AIGC API already running"
else
  if [ -d "$AIGC_DIR" ]; then
    echo -e "${YELLOW}→${NC} Starting AIGC API..."
    (cd "$AIGC_DIR" && go run ./main.go &>/tmp/aigc-api.log &)
    sleep 3
    check curl -s http://127.0.0.1:6571/health
  else
    echo -e "${YELLOW}!${NC} AIGC API dir not found: $AIGC_DIR (skipping)"
  fi
fi

# 6. PHP dev server (Flarum)
if curl -s http://127.0.0.1:8080 >/dev/null 2>&1; then
  echo -e "${GREEN}✓${NC} Flarum forum already running"
else
  if [ -d "$SITE_DIR" ]; then
    echo -e "${YELLOW}→${NC} Starting Flarum dev server..."
    (cd "$SITE_DIR" && php -S 127.0.0.1:8080 -t public &>/tmp/flarum-server.log &)
    sleep 2
    check curl -s http://127.0.0.1:8080
  else
    echo -e "${RED}✗${NC} Flarum site dir not found: $SITE_DIR"
  fi
fi

# 7. Webpack watch (frontend HMR)
if pgrep -f "webpack.*watch" >/dev/null 2>&1; then
  echo -e "${GREEN}✓${NC} Webpack watch already running"
else
  echo -e "${YELLOW}→${NC} Starting webpack watch..."
  (cd "$ROOT/js" && npm run dev &>/tmp/webpack-watch.log &)
  sleep 3
  if pgrep -f "webpack.*watch" >/dev/null 2>&1; then
    echo -e "${GREEN}✓${NC} Webpack watch started"
  else
    echo -e "${RED}✗${NC} Webpack watch failed"
    tail -5 /tmp/webpack-watch.log
  fi
fi

# 8. Push contract settings to Flarum DB
if [ -f "$STATE_DIR/contract.env" ]; then
  source "$STATE_DIR/contract.env"
  mysql -u flarum -pflarum -h 127.0.0.1 flarum 2>/dev/null << SQL
INSERT INTO settings (\`key\`, \`value\`) VALUES
  ('donk-aigc-collectibles.blockchain-rpc-url', '${BLOCKCHAIN_RPC_URL}'),
  ('donk-aigc-collectibles.nft-contract-address', '${NFT_CONTRACT_ADDRESS}'),
  ('donk-aigc-collectibles.minter-private-key', '${MINTER_PRIVATE_KEY}')
ON DUPLICATE KEY UPDATE \`value\` = VALUES(\`value\`);
SQL
  echo -e "${GREEN}✓${NC} Contract settings synced to DB"
fi

echo ""
echo "=== All services ready ==="
echo "  Forum:      http://127.0.0.1:8080"
echo "  Admin:      http://127.0.0.1:8080/admin"
echo "  IPFS API:   http://127.0.0.1:5001"
echo "  IPFS GW:    http://127.0.0.1:8888"
echo "  Anvil RPC:  http://127.0.0.1:8545"
echo "  AIGC API:   http://127.0.0.1:6571"
echo ""
echo "  Webpack watch log: /tmp/webpack-watch.log"
echo "  IPFS log:          /tmp/ipfs-daemon.log"
echo "  Anvil log:         /tmp/anvil.log"
echo "  AIGC log:          /tmp/aigc-api.log"
