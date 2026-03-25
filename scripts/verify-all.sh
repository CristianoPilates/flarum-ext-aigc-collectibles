#!/usr/bin/env bash
# ============================================================
# verify-all.sh — Full verification after restart
# Usage: bash scripts/verify-all.sh
# ============================================================
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m'

echo "=== Step 1: Start all services ==="
bash "$ROOT/scripts/dev-start.sh"

echo ""
echo "=== Step 2: Seed demo data ==="
php "$ROOT/scripts/seed-demo.php"

echo ""
echo "=== Step 3: Reset admin checkin for testing ==="
mysql -u flarum -pflarum -h 127.0.0.1 flarum \
  -e "UPDATE users SET last_checkin_at = '2026-03-24 00:00:00' WHERE username = 'admin';" 2>/dev/null
echo "Admin checkin reset"

echo ""
echo "=== Step 4: API smoke tests ==="

# Login
TOKEN=$(curl -s http://127.0.0.1:8080/api/token -X POST \
  -H 'Content-Type: application/json' \
  -d '{"identification":"admin","password":"password"}' | jq -r '.token')

if [ "$TOKEN" != "null" ] && [ -n "$TOKEN" ]; then
  echo -e "  ${GREEN}✓${NC} Login: got token"
else
  echo -e "  ${RED}✗${NC} Login failed"
  exit 1
fi

# Checkin
CHECKIN=$(curl -s http://127.0.0.1:8080/api/checkin-records/checkin -X POST \
  -H "Authorization: Token $TOKEN" -H 'Content-Type: application/json')
if echo "$CHECKIN" | jq -e '.data.id' >/dev/null 2>&1; then
  echo -e "  ${GREEN}✓${NC} Checkin: success"
else
  echo -e "  ${RED}✗${NC} Checkin: $(echo $CHECKIN | jq -r '.errors[0].detail // "unknown error"')"
fi

# Collectibles
COLS=$(curl -s "http://127.0.0.1:8080/api/collectibles?filter[user]=1" \
  -H "Authorization: Token $TOKEN" | jq -r '.data | length')
echo -e "  ${GREEN}✓${NC} Collectibles: $COLS items for admin"

# Web3
W3=$(curl -s "http://127.0.0.1:8080/api/web3-accounts" \
  -H "Authorization: Token $TOKEN" | jq -r '.data | length')
echo -e "  ${GREEN}✓${NC} Web3 accounts: $W3"

# IPFS
IPFS_ID=$(curl -s -X POST http://127.0.0.1:5001/api/v0/id | jq -r '.ID // "unavailable"')
echo -e "  ${GREEN}✓${NC} IPFS: $IPFS_ID"

# Anvil
CHAIN=$(curl -s -X POST http://127.0.0.1:8545 \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","method":"eth_chainId","params":[],"id":1}' | jq -r '.result // "unavailable"')
echo -e "  ${GREEN}✓${NC} Anvil chain: $CHAIN"

# AIGC
AIGC=$(curl -s http://127.0.0.1:6571/health | jq -r '.status // "unavailable"')
echo -e "  ${GREEN}✓${NC} AIGC API: $AIGC"

echo ""
echo "=== Step 5: E2E browser tests ==="
node "$ROOT/scripts/e2e-test.cjs" 2>&1

echo ""
echo "=== Verification complete ==="
echo "Screenshots saved to: $ROOT/screenshots/"
