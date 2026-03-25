#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
project_root="$(cd -- "$script_dir/.." && pwd)"
rpc_url="${ANVIL_RPC_URL:-http://127.0.0.1:8545}"
private_key="${ANVIL_PRIVATE_KEY:-0xac0974bec39a17e36ba4a6b4d238ff944bacb478cbed5efcae784d7bf4f2ff80}"
env_file="${CONTRACT_ENV_FILE:?CONTRACT_ENV_FILE is required}"
chain_dir="$project_root/chain"
contract_path="src/CollectibleNFT.sol:CollectibleNFT"

if [ ! -d "$chain_dir" ]; then
  echo "[ensure-contract] chain directory not found: $chain_dir" >&2
  exit 1
fi

mkdir -p "$(dirname "$env_file")"

for _ in $(seq 1 60); do
  if cast chain-id --rpc-url "$rpc_url" >/dev/null 2>&1; then
    break
  fi
  sleep 1
done
cast chain-id --rpc-url "$rpc_url" >/dev/null

cd "$chain_dir"
forge build >/dev/null

contract_addr=""
if [ -f "$env_file" ]; then
  contract_addr="$(grep '^NFT_CONTRACT_ADDRESS=' "$env_file" | cut -d= -f2- || true)"
fi

if [ -n "$contract_addr" ]; then
  code="$(cast code "$contract_addr" --rpc-url "$rpc_url" 2>/dev/null || echo 0x)"
  if [ "$code" != "0x" ]; then
    echo "[ensure-contract] already deployed: $contract_addr"
    exit 0
  fi
fi

out="$(forge create "$contract_path" \
  --rpc-url "$rpc_url" \
  --private-key "$private_key" \
  --broadcast)"

contract_addr="$(echo "$out" | sed -n 's/^Deployed to: //p' | tail -n1)"
if [ -z "$contract_addr" ]; then
  echo "$out"
  echo "[ensure-contract] could not parse deployed address" >&2
  exit 1
fi

printf '%s\n' \
  "BLOCKCHAIN_RPC_URL=$rpc_url" \
  "NFT_CONTRACT_ADDRESS=$contract_addr" \
  "MINTER_PRIVATE_KEY=$private_key" \
  > "$env_file"

echo "[ensure-contract] deployed: $contract_addr"
echo "[ensure-contract] wrote $env_file"
