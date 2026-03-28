# AIGC Collectibles — E2E Verification Notes

## Date: 2026-03-25

## Services Architecture

| Service | Address | Purpose |
|---------|---------|---------|
| Flarum Forum | http://127.0.0.1:8080 | PHP dev server (flarum-site/) |
| MySQL | 127.0.0.1:3306 | DB: `flarum` (demo), `flarum_aigc_collectibles_test` (tests) |
| IPFS (Kubo) | API: 5001, GW: 8888 | Image & metadata storage |
| Anvil (EVM) | 8545 | Local blockchain, chain ID 31337 |
| AIGC API (Go) | 6571 | akashgen-api-go image generation proxy |
| Webpack Watch | — | Frontend HMR via `npm run dev` |

## Demo Users

| User | Password | Blind Boxes | Wallet |
|------|----------|-------------|--------|
| admin | password | 10+ | 0xf39f...2266 (Anvil #0) |
| buyer | password | 5 | — |
| seller | password | 3 | — |

## Devenv Entry Points

| Command | Purpose |
|--------|---------|
| `make dev` | Start the full dev stack |
| `make up-site` | Start forum, mysql, and frontend watch |
| `make up-external` | Start ipfs, anvil, and akashgen |
| `make status` | Probe process health |
| `make init-site` | Install and configure the forum site |
| `make init-chain` | Bootstrap contract state and sync settings |
| `make init-test-data` | Create/reset Playwright demo users and data |
| `make pw-smoke` | Full smoke test loop with retained scene |
| `make pw-manual` | Open headed Chromium with a persistent manual profile and optional unpacked extensions |
| `make mcp` | Start Playwright MCP |
| `make mcp-state` | Run the optional MCP CLI wrapper that inspects shared profile + MetaMask state |
| `make mcp-minimal-nft` | Run the optional MCP CLI wrapper for the minimal NFT flow |
| `make verify` | Full verification run |

## Key Pitfalls & Solutions

### 1. DB Separation (Critical)
- **Problem**: `flarum-site/config.php` used `flarum_test` as its database. Integration tests (`flarum/testing`) calls `dropAllTables()` on the test DB, destroying demo data.
- **Fix**: Changed demo site to use `flarum` DB. Integration tests use `flarum_aigc_collectibles_test`. Three separate databases in `devenv.nix`.

### 2. AIGC API Goes Down
- **Problem**: The Go-based AIGC API (`akashgen-api-go`) exits when idle or on error, causing 500s on blind box open.
- **Fix**: Keep the stack under process management and run `make status` before `make verify`.

### 3. Flarum Login in Playwright
- **Problem**: Setting `flarum_remember` cookie via `document.cookie` doesn't work — Flarum needs it set at the browser context level.
- **Fix**: Use `context.addCookies()` for API token approach, or click through the actual login modal UI.

### 4. Playwright Browser Path in Nix
- **Problem**: Built-in Playwright MCP tool tries to mkdir inside read-only Nix store.
- **Fix**: Use nixpkgs-provided `playwright` / `mcp-server-playwright`, which already wrap `@playwright/test`, set `NODE_PATH`, export `PLAYWRIGHT_BROWSERS_PATH`, and keep MCP pinned to the same Playwright runtime version.

### 5. Real MetaMask in Persistent Profiles
- **Problem**: Wallet verification against a temporary mock diverges from the real browser-extension path.
- **Fix**: Use the persistent Chromium profile with the real MetaMask extension for headed manual and MCP verification. Headless smoke no longer injects a fake `window.ethereum`.

### 6. Sync Queue + AIGC Timing
- **Problem**: With `queue.driver = sync`, the blind box open endpoint blocks until AIGC + IPFS + NFT minting all complete (~5-20s).
- **Fix**: This is expected in dev. The frontend shows "Generating..." spinner and polls. Smoke tests wait for sync completion.

### 7. NFT Auto-Minting
- **Problem**: E2E test for manual mint says "no unminted collectibles".
- **Reason**: `GenerateCollectibleJob` auto-mints when user has a bound wallet. This is correct behavior.

### 8. Contract Settings Sync
- **Problem**: After Anvil restart, the contract address in DB may be stale.
- **Fix**: `init-chain` reads `contract.env` and pushes settings to DB after contract bootstrap.

## Feature Verification Matrix

| Feature | API Coverage | Playwright Smoke Coverage |
|---------|--------------|---------------------------|
| Daily Check-in | PASS | PASS |
| Blind Box Balance | PASS | PASS |
| Blind Box Open (PoW + AIGC + IPFS) | PASS | PASS |
| Collectibles Gallery | PASS | PASS |
| Collectible Detail Modal | PASS | PASS |
| Showcase on Posts | PASS | PASS |
| Web3 Wallet Binding | PASS | PASS |
| NFT Minting (auto/manual endpoint) | PASS | PASS |
| P2P Trade Create / Accept | PASS | PASS (browse flow smoke only) |
| Multi-user Support | PASS | PASS |

## Playwright Artifacts

- Playwright Test stores traces, screenshots, and videos under `$DEVENV_STATE/playwright/test-results`.
- The HTML report is written to `$DEVENV_STATE/playwright/html-report`.
- The persistent shared browser profile lives under `$DEVENV_STATE/playwright-profile`.
- Playwright MCP session output is written to `$DEVENV_STATE/playwright-mcp-output`.
- Playwright MCP reuses the shared browser profile under `$DEVENV_STATE/playwright-profile` so headed automation sees the same MetaMask extension state.
- Repository `scripts/playwright/mcp-cli/*.cjs` files are optional CLI wrappers around the running MCP server, not a separate browser automation stack.
