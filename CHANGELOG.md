# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Fixed

- **Blind box tabs empty in barter overlay** — `BarterConfigOverlay` rendered `blindboxes` (with lowercase 'b') but the backend API and all other code used `blindBoxes` (camelCase). The `getActiveBucket()` and `renderGrid()` methods returned an empty array for blind box tabs. Fixed by using the correct `blindBoxes` property name consistently.
- **Blind box tabs show collectible rarity filters** — the overlay used collectible rarity filter for all tabs. Blind boxes have `status` (appraised/unappraised), not `rarity`. Now shows correct status filter pills and sorts by budget descending for blind box tabs.
- **Barter assets API 422 error** — `GET /barter-assets` returned "Thread ID is required" in the private message composer. Frontend sends snake_case parameter names (`thread_id`, `counterparty_user_id`) but the backend only read camelCase. Now accepts both formats for backward compatibility.

## [1.1.0]

### Added

- **Barter composer redesign** — composer now shows selection summary card instead of full asset grid. "编辑协商" button opens full overlay for asset selection.
- **Barter config overlay** — pick assets in a clean full-screen overlay. Four tabs separate your collectibles, your blind boxes, theirs collectibles, and theirs blind boxes. Search, rarity filters, and pagination keep things navigable even with many assets.
- **Collectible inline rename** — tap the pencil icon in a collectible's detail modal to rename it. No extra pages or dialogs.
- **Barter draft persistence** — your asset selections survive page refreshes. Draft auto-saves every 500ms.
- **Unnamed collectibles banner** — if you have collectibles without names, the overlay reminds you to name them before proposing.

### Changed

- **Barter composer UX** — moved from inline dual-column asset grid to summary card + overlay pattern for cleaner composer UI.
- **CollectibleDetailModal** — added inline rename capability with validation and error handling.

### Fixed

- **Transaction safety for barter state changes** — `rejectProposal()` and `cancelProposal()` now wrap state mutations in database transactions with row-level locking, matching the pattern used in `acceptProposal()`.

### Tests Added

- **CollectibleNameTest** — integration tests for PATCH `/collectibles/{id}` name updates with validation.
- **CollectibleTest** — unit tests for `Collectible::setName()` validation logic.

## [1.0.0] — 2026-04-14

### Added

- **Barter proposals in private message threads** — proposals are now created in the context of a Flarum Direct Message thread, enabling multi-revision negotiation, accept/reject/cancel flows, and settlement.
- **Multi-asset barter** — users can propose combinations of collectibles and blind boxes on both sides of a trade.
- **Barter composer** — inline asset picker with selection state, validation, and proposal card preview.
- **Barter composer thumbnail cards** — asset selector uses visual thumbnail cards instead of text+checkbox. Collectibles show IPFS images, blind boxes show SVG icons. Click-to-toggle with check badge.
- **Barter thread panel** — negotiation history displayed in PM thread sidebar.
- **Barter settlement** — assets are transferred on acceptance via `BarterSettlementService`.
- **Locale management** — `zh-hans.yml` as canonical locale source with `zh-Hans` as alias, plus scripts for locale status checking.
- **Blind box balance dictionary** — blind boxes grouped by type with real-time count, expandable to show individual cards.
- **Blind box SVG illustrations** — balance dictionary entry and card face render `blindbox-unappraised.svg` / `blindbox-appraised.svg` from `assets/images/`.
- **Collectible context in private messages** — collectible details accessible within PM threads.
- **Showcase CTA** — private messaging flow with collectible showcase integration.
- **MCP validation scripts** — `mcp-validate-barter-composer.cjs`, `mcp-validate-barter-history.cjs`, `validate-showcase-context.cjs`, `validate-showcase-cta-click.cjs`.
- **Default favicon** — `DefaultFavicon` frontend component.

### Fixed

- **Blind box SVG 404** — SVGs were in `resources/images/` but Flarum's `Extension::hasAssets()` checks `$ext/assets/`. Moved SVGs to `assets/images/` so `assets:publish` copies them correctly.
- **Dead `STATUS_OPENED` UI code removed** — `BlindBoxCard` had an unreachable "Opened" indicator that can never appear (blind boxes are destroyed after open; only `unappraised`/`appraised` exist). `blindBoxSvgUrl()` had a dead `type` parameter removed.
- **Barter composer textarea / send button blocked** — `BarterComposerPanel` was mounted as a `<li>` inside `MessageComposer`'s `headerItems` flex column, pushing the `TextEditor` off-screen. Replaced the `headerItems` hook with a `view()` override that injects the panel as a proper `<div class="MessageComposer-barter">` between the header row and the text editor.
- **Missing `Flarum\User\User` import in `BlindBox` model** — resolved static analysis error.
- **Showcase panel regression** — `$getShowcase()` now falls back to direct DB query when the `showcaseCollectible` relation is not eager-loaded (e.g. post author via `PostResource`).

### Refactored

- **Legacy `Trade` model retired** — `TradeService`, `TradePolicy`, `TradeCommand` chain, `TradeChainTest`, `TradeRepository`, `TradePanel.tsx`, and `TradeRequestModal.tsx` removed entirely.
- **Locale source unified** — `zh-hans.yml` as canonical source, `zh-Hans` as alias.
- **Forum bootstrap split** — installers extracted for clarity.
- **Barter proposal card** — extracted into standalone `BarterProposalCard` component.
- **Static analysis tightened** — translation text handling improved throughout.

### Changed

- **Showcase panel** — now displays in post/reply sidebar for users with a configured showcase collectible.
- **Trade → Barter terminology** — all user-facing and internal references updated from "trade" to "barter".

### Tests Added

- **Barter validation coverage** — tests for multi-asset proposal creation and validation.
- **Checkin integration coverage** — modernized test metadata and coverage.
- **Service coverage expansion** — broader test coverage for `CollectibleProofService`, `CheckinService`, and `IPFSService`.

### Known Issues (Post-Release)

- `CollectibleEventResource` should have a scope/policy restricting access to authenticated users — tracked separately.
- `BarterSettlementService` should be called within the same transaction as `acceptProposal()` to prevent split-brain state on partial failure — tracked separately.
- `BlindBoxService::appraise()` PoW difficulty is client-supplied without a minimum threshold floor — tracked separately.

[Unreleased]: https://github.com/CristianoPilates/flarum-ext-aigc-collectibles/compare/main...release/post-refactor-consolidation
[1.1.0]: https://github.com/CristianoPilates/flarum-ext-aigc-collectibles/releases/tag/v1.1.0
[1.0.0]: https://github.com/CristianoPilates/flarum-ext-aigc-collectibles/releases/tag/v1.0.0
