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
| `devenv up -d` | Start long-running services (MySQL, IPFS, Anvil, AkashGen API, Flarum, Webpack watch, Playwright MCP) |
| `status` | Probe service health from inside the devenv shell |
| `urls` | Print the local URLs for forum, IPFS, chain, API, and MCP |
| `run` | Wait for services, deploy/verify the NFT contract, and sync Flarum settings |
| `ready` / `bootstrap` | Alias for `run` |
| `seed-demo` | Create/reset demo users with passwords |
| `pw-test` / `e2e` | Playwright Test smoke suite driven by the Nix-provided `playwright` CLI |
| `pw-headed` | Run the same Playwright specs headed |
| `pw-codegen` | Run Playwright codegen against the local forum |
| `pw-doctor` | Verify the Nix-provided Playwright runtime and config |
| `verify` | Full verification (bootstrap + seed + API smoke + E2E) |
| `e2e-clean` | Clear Playwright test and MCP artifacts under `$DEVENV_STATE` |

## Key Pitfalls & Solutions

### 1. DB Separation (Critical)
- **Problem**: `flarum-site/config.php` used `flarum_test` as its database. Integration tests (`flarum/testing`) calls `dropAllTables()` on the test DB, destroying demo data.
- **Fix**: Changed demo site to use `flarum` DB. Integration tests use `flarum_aigc_collectibles_test`. Three separate databases in `devenv.nix`.

### 2. AIGC API Goes Down
- **Problem**: The Go-based AIGC API (`akashgen-api-go`) exits when idle or on error, causing 500s on blind box open.
- **Fix**: `devenv up` keeps the service under process management, and `verify` should be run only after the stack is healthy.

### 3. Flarum Login in Playwright
- **Problem**: Setting `flarum_remember` cookie via `document.cookie` doesn't work — Flarum needs it set at the browser context level.
- **Fix**: Use `context.addCookies()` for API token approach, or click through the actual login modal UI.

### 4. Playwright Browser Path in Nix
- **Problem**: Built-in Playwright MCP tool tries to mkdir inside read-only Nix store.
- **Fix**: Use nixpkgs-provided `playwright` / `mcp-server-playwright`, which already wrap `@playwright/test`, set `NODE_PATH`, export `PLAYWRIGHT_BROWSERS_PATH`, and keep MCP pinned to the same Playwright runtime version.

### 5. Mock MetaMask Wallet
- **Problem**: No real MetaMask extension in headless Chromium.
- **Fix**: Inject `window.ethereum` mock via `context.addInitScript()` before page loads. Mock handles `eth_requestAccounts`, `personal_sign` (delegates to `cast wallet sign`). See `tests/e2e/support/playwright-mcp-init-script.js`.

### 6. Sync Queue + AIGC Timing
- **Problem**: With `queue.driver = sync`, the blind box open endpoint blocks until AIGC + IPFS + NFT minting all complete (~5-20s).
- **Fix**: This is expected in dev. The frontend shows "Generating..." spinner and polls. Test waits 8s for sync completion.

### 7. NFT Auto-Minting
- **Problem**: E2E test for manual mint says "no unminted collectibles".
- **Reason**: `GenerateCollectibleJob` auto-mints when user has a bound wallet. This is correct behavior.

### 8. Contract Settings Sync
- **Problem**: After Anvil restart, the contract address in DB may be stale.
- **Fix**: `run` reads `contract.env` and pushes settings to DB after contract bootstrap.

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
- Playwright MCP session output is written to `$DEVENV_STATE/playwright-mcp-output`.
- The persistent MCP browser profile lives under `$DEVENV_STATE/playwright-mcp-profile`.
