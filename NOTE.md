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

## Scripts Created

| Script | Purpose |
|--------|---------|
| `scripts/dev-start.sh` | Start all services (IPFS, Anvil, AIGC, Flarum, Webpack) |
| `scripts/seed-demo.php` | Create/reset demo users with passwords |
| `scripts/ensure-contract.sh` | Deploy NFT contract to Anvil |
| `scripts/e2e-test.cjs` | Playwright E2E test suite (10 tests) |
| `scripts/verify-all.sh` | Full restart verification (services + seed + API smoke + E2E) |

## Key Pitfalls & Solutions

### 1. DB Separation (Critical)
- **Problem**: `flarum-site/config.php` used `flarum_test` as its database. Integration tests (`flarum/testing`) calls `dropAllTables()` on the test DB, destroying demo data.
- **Fix**: Changed demo site to use `flarum` DB. Integration tests use `flarum_aigc_collectibles_test`. Three separate databases in `devenv.nix`.

### 2. AIGC API Goes Down
- **Problem**: The Go-based AIGC API (`akashgen-api-go`) exits when idle or on error, causing 500s on blind box open.
- **Fix**: `dev-start.sh` checks and restarts it. The E2E test should be run with all services verified first.

### 3. Flarum Login in Playwright
- **Problem**: Setting `flarum_remember` cookie via `document.cookie` doesn't work — Flarum needs it set at the browser context level.
- **Fix**: Use `context.addCookies()` for API token approach, or click through the actual login modal UI.

### 4. Playwright Browser Path in Nix
- **Problem**: Built-in Playwright MCP tool tries to mkdir inside read-only Nix store.
- **Fix**: Use Playwright directly via `require('../js/node_modules/playwright')` with `executablePath` from `PLAYWRIGHT_LAUNCH_OPTIONS_EXECUTABLE_PATH`.

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
- **Fix**: `dev-start.sh` reads `contract.env` and pushes settings to DB on every start.

## Feature Verification Matrix

| Feature | API Test | UI Test | Screenshot |
|---------|----------|---------|------------|
| Daily Check-in | PASS | PASS | `03-checkin-done.png` |
| Blind Box Balance | PASS | PASS | Header shows count |
| Blind Box Open (PoW + AIGC + IPFS) | PASS | PASS | `04a-blindbox-modal.png`, `04b-blindbox-result.png` |
| Collectibles Gallery | PASS | PASS | `05-user-collectibles-page.png` |
| Collectible Detail Modal | PASS | PASS | `06-collectible-detail.png` |
| Rarity Filtering | — | PASS | Filter buttons visible in gallery |
| Showcase on Posts | PASS | PASS | `08-post-with-badge.png` |
| Web3 Wallet Binding | PASS | PASS | `07-wallet-connector.png` |
| NFT Minting (auto) | PASS | PASS | Token IDs visible on cards |
| P2P Trade Create | PASS | — | API verified via curl |
| P2P Trade Accept | PASS | — | API verified via curl |
| Trade Panel UI | — | PASS | Visible in `07-wallet-connector.png` |
| Multi-user Support | PASS | PASS | `10b-admin-collectibles-as-buyer.png` |

## Screenshot Evidence

All screenshots in `screenshots/` directory:
- `01-homepage-guest.png` — Forum homepage as guest
- `02-logged-in-admin.png` — Logged in, header shows Check In + blind box count
- `03-checkin-done.png` — Check-in completed, button shows "Checked In"
- `04a-blindbox-modal.png` — Blind box modal with balance and open button
- `04b-blindbox-result.png` — Generation in progress (spinner + "Generating...")
- `05-user-collectibles-page.png` — Full user profile with collectibles, trades, wallet
- `06-collectible-detail.png` — Detail modal with image, rarity, token ID, IPFS link
- `07-wallet-connector.png` — Wallet bound, showing address and disconnect button
- `08-post-with-badge.png` — Post with showcase collectible badge next to username
- `10a-buyer-logged-in.png` — Buyer session with own blind box count
- `10b-admin-collectibles-as-buyer.png` — Buyer viewing admin's collectibles (no sidebar)
- `10c-buyer-own-page.png` — Buyer's own collectibles page
