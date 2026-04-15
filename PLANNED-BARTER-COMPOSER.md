# Barter Composer — Design Review & Redesign Plan

**Status:** Locked after engineering review
**Branch:** `release/post-refactor-consolidation`
**Date:** 2026-04-15
**Engineering Review:** Complete — 4 decisions resolved, 4 issues fixed, 7 tests planned

---

## Root Cause Analysis: The Two Bugs in the Screenshot

### Bug 1: "对方出价" is blank

**Root cause:** `BarterComposerPanel.tsx:147` references an undefined variable `fields` inside `getOriginalProposalItems()`:

```tsx
// Line 147 — fields is NOT in scope here
const target = proposals.find((p: any) => String(p.id?.()) === String(fields.barterReplacesProposalId()));
```

This is a JavaScript ReferenceError. But more fundamentally, even if fixed, "their offer" appearing in the composer is by design. The `theirs` side renders `payload.theirs.*` from the `barterAssets` API response. If that side is blank, the reason is one of:

1. **Data root cause:** The `barterAssets` endpoint returns an empty `theirs` bucket for this dialog — the counterparty genuinely has no assets, or the API filter/authorization is wrong.
2. **Filtering root cause:** The endpoint may be filtering out assets the current user doesn't have permission to see (e.g., blind boxes in `unappraised` state that shouldn't be traded yet).
3. **UX root cause:** Even if the counterparty has 0 assets, the UI should show "对方暂无资产可交易" (empty state CTA), not a blank column.

**The real fix is architectural:** The "their offer" column should be populated from a separate data source (the counterparty's actual holdings, loaded asynchronously). If it's blank because the API returned nothing, the empty state must guide the user.

### Bug 2: "我方出价" list is truncated

**Root cause:** Layout overflow, not a rendering bug. The `.BarterComposerPanel` is a CSS Grid container. The `.BarterComposerPanel-grid` is `display: grid; grid-template-columns: repeat(2, minmax(0, 1fr));`. Inside each column, `.BarterComposerPanel-column` contains `.BarterAssetCardGrid` which is `display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));`.

The issue: `.BarterComposerPanel` has no `max-height` constraint, but its parent `.MessageComposer-barter` sets `flex: 0 0 100%; width: 100%` without constraining height. The Flarum composer itself has a limited vertical space budget (~40vh on most screens). As cards stack, they overflow the viewport and the browser's default overflow clips them. There is no `overflow-y: auto` on the panel, so users can't scroll to see the rest.

**Immediate fix (in current architecture):** Add `overflow-y: auto; max-height: 50vh;` to `.BarterComposerPanel-column`. But this only papers over the symptom — the fundamental problem is that a 4-section grid of thumbnails doesn't belong in a message composer.

### The Diagnosis in One Sentence

The composer is not the right place for asset browsing. These two bugs are symptoms of the same root cause: **trying to do a full asset-selection workflow inside a message composer pane.**

---

## Step 0: Design Scope Assessment

**UI Scope:** Very high — new full-screen overlay, new tab navigation, new summary card, new modal/overlay entry point, composer integration changes.

**Existing patterns to leverage:**
- Flarum's `Modal` component infrastructure for the full-screen overlay
- Existing `BarterAssetCard` component (redesign card size, but keep the thumb/info/checkmark pattern)
- Existing `BarterComposerPanel` shell (replace inline grid with summary card + "Edit" trigger)
- Flarum's `listItems` helper for header construction
- Existing `BarterProposalCard` in thread panel (no change needed here)

**No DESIGN.md exists.** Project uses Flarum's default design language + custom LESS. Proceeding with Flarum conventions.

**Prior design reviews:** One prior review (Section 1-10 of this file, locked). This review supersedes it entirely.

---

## Phase 0 (Prerequisite — must do first): Add `name` column to collectibles

- Migration: `2026_04_15_000008_add_name_to_collectibles.php` (nullable string, max 100 chars)
- Model: `Collectible::getNameAttribute()` — return `$value` if not null/empty, else fallback to `'Collectible #{$id}'`
- **Rationale:** Humans cannot search or identify assets by numeric ID. The barter config overlay requires a real, owner-assignable name field. This is a prerequisite for the entire redesign.
- Model mass assignment: `$fillable` not used in this project, so no change needed
- No existing code writes `name` to Collectible — safe to add as nullable

## Step 1: New Information Architecture

### The Core Insight

Separation of concerns:

| Concern | Where it lives |
|---------|---------------|
| Composing a message to the counterparty | Message Composer |
| Browsing, filtering, paginating my assets | Full-screen Config Overlay |
| Browsing, filtering, paginating counterparty assets | Full-screen Config Overlay |
| Reviewing what I've configured | Composer summary card |
| Adjusting a draft | Back to Full-screen Config Overlay |

The composer should know **nothing** about asset rendering. It should only display:
- A summary: "你出 2C 1B，换对方 1C"
- A thumbnail strip of selected asset names
- An "编辑协商" button that opens the config overlay
- The message text area

### Proposed Architecture

```
Message Composer
├── [HEADER] To: {recipient}
├── [BARTER SUMMARY CARD]          ← NEW: replaces the inline grid
│   ├── "协商条件已配置" kicker
│   ├── Give ↔ Get summary (names + counts)
│   └── "编辑协商" button
├── [TEXT EDITOR]                  ← unchanged
└── [SUBMIT]

Full-Screen Config Overlay (Modal / m.draw())
├── [OVERLAY HEADER]
│   ├── Title: "配置协商"
│   ├── Live counter: "我出 2 · 对方出 1"
│   └── Close (×)
├── [SUMMARY ROW]                  ← always visible, updates live
├── [TAB BAR]
│   ├── 我的藏品
│   ├── 我的盲盒
│   ├── 对方藏品
│   └── 对方盲盒
├── [FILTER BAR]
│   ├── Search input
│   └── Rarity filter pills (全部/普通/稀有/史诗/传说)
├── [ASSET GRID]
│   ├── Paginated (12 per page)
│   └── Cards with checkboxes
└── [OVERLAY FOOTER]
    ├── Cancel
    └── 确认选择 (applies and closes)
```

**Key decisions:**

1. **Overlay type:** Flarum `Modal` (not a route). Uses `m.draw()` with a full-viewport overlay. Maintains all Flarum state. Avoids route complexity.

2. **Data persistence during overlay:** Selections are stored in `composer.fields.barterMySelections` / `barterTheirSelections` (already implemented). Opening the overlay does NOT reset these. Closing the overlay just hides it — the data survives.

3. **Cross-tab state:** Tab state (active tab) is local component state, not persisted. This is fine — users navigate tabs each time they open.

4. **When no assets:** Each tab shows an empty state CTA:
   - "我的藏品/盲盒" tabs: "每日签到可获得盲盒，开始交换吧"
   - "对方藏品/盲盒" tabs: "对方暂无资产可交易"

5. **Back button / escape:** ESC closes the overlay. Browser back does NOT close (no route change).

---

## Step 2: Visual Hierarchy

### Composer Summary Card (new)

Replaces the entire `.BarterComposerPanel-grid` with a compact card:

```
┌─────────────────────────────────────────────┐
│ 🎴 协商条件已配置          [✏️ 编辑协商]    │
├─────────────────────────────────────────────┤
│  我方出: Cosmic Fox #42      对方出:         │
│          Neon City #17    ←  blank strip →  │
│          (共 2 藏品)                        │
├─────────────────────────────────────────────┤
│ ✨ "两张稀有换你那张传说，你看如何？"  ← user's message text preview │
└─────────────────────────────────────────────┘
```

- Background: `#F8FAFC` with `#E2E8F0` border (subtle, not competing with text editor)
- "编辑协商" button: text-only, right-aligned, `#3B82F6` — tertiary action
- Asset names: truncated at 20 chars with ellipsis, stacked
- "无协商条件" state: "🎴 还未配置协商条件 [+ 配置协商]" — ghost card style

### Full-Screen Overlay

- Background: `rgba(15, 23, 42, 0.7)` backdrop + white overlay panel
- Overlay panel: `max-width: 800px`, centered, `border-radius: 20px`
- Header: dark gradient `#1E293B` → `#334155` (same as BarterThreadPanel)
- Tab bar: pill-style tabs, active tab has white background + shadow
- Filter bar: search input + rarity filter buttons (toggleable pills)
- Asset grid: `repeat(auto-fill, minmax(90px, 1fr))` — slightly smaller than current 100px to fit 4 columns
- Footer: sticky bottom bar with Cancel + "确认选择" (primary button)

### Asset Card Redesign

**Images are required — no icon fallbacks in production:**
- Collectibles: Real IPFS image via `gatewayUrl(asset.ipfsCid)` — if CID is null/missing, show a generated placeholder (CSS gradient + initials)
- Blind boxes: Real SVG via `blindBoxSvgUrl()` — already implemented, this continues to work

- Card size: 90px (tighter grid, more visible at once)
- Selected: `#3B82F6` border + `#EFF6FF` background + blue checkmark circle
- Rarity border-color: match existing `.rarity-*` variables (legendary: `#fcd34d`, epic: `#c4b5fd`, rare: `#93c5fd`, common: `#d1d5db`)
- Hover: `transform: translateY(-1px)` + shadow (subtle lift)
- Long names: `text-overflow: ellipsis` at 12px/2 lines. Full name in `title` attribute.
- Thumb: 100% width, aspect-ratio 1:1, `object-fit: cover`

---

## Step 3: Interaction Patterns

### Opening the Config Overlay

**Trigger:** "配置协商" button on composer summary card  
**Animation:** Overlay fades in (200ms ease-out). Tab 0 ("我的藏品") is active by default.  
**Focus:** First filter input gets focus.

### Tab Navigation

- Click tab → switches active tab
- Active tab shows badge with count of selected items in that tab
- Tab content lazy-loads (all tabs' data already fetched via `barterAssets` API — just filter the array)

### Asset Selection

- Click card → toggle selection (debounced 100ms, already implemented)
- Selected count per tab shown in tab badge
- Live summary row at top of overlay updates in real time

### Search / Filter

- Search input: filters by `name` (case-insensitive substring match on the owner's custom name, NOT the numeric ID). This is why the `name` column is a prerequisite — humans cannot search by `#42`.
- Rarity pills: toggle filter. "全部" clears other filters. Multiple rarity pills can be active simultaneously.
- Filters apply immediately (no debounce needed for filter, but filter function is debounced 150ms for perf)

### Pagination

- 12 items per page
- Previous/Next buttons + page indicator
- Current page resets to 1 when search query changes
- Scroll position resets to top on page change

### Closing the Overlay

- **Confirm:** Click "确认选择" → overlay hides → composer summary card redraws with new selections
- **Cancel:** Click × button or ESC → overlay hides → selections are kept (NOT reset, they were already live-updating)
- **Discard:** If user wants to reset: "清除选择" text button inside overlay (before closing)

### Counter-offer Flow

When revising an existing proposal:
- Overlay opens with previous selections pre-checked
- Counter diff shown in overlay header: "第 N 版 → 修改中"
- Tab bar shows the current proposal's items highlighted differently
- Same confirm/cancel flow

---

## Step 4: Accessibility

- Overlay: `role="dialog"`, `aria-modal="true"`, `aria-labelledby` pointing to overlay title
- Tab bar: `role="tablist"`, each tab `role="tab"`, content panels `role="tabpanel"`
- Asset cards: `role="checkbox"`, `aria-checked`, `aria-label` with full asset name + rarity
- Filter input: `aria-label="搜索资产名称"`
- Live summary: `aria-live="polite"` region
- ESC key closes overlay
- Focus trap inside overlay (Tab cycles within overlay content)
- Focus returns to "编辑协商" button when overlay closes

---

## Step 5: Edge Cases

| Edge Case | Behavior |
|-----------|----------|
| Counterparty has 0 assets | Show "对方暂无资产可交易" empty state in their tab |
| User has 0 assets | Show "每日签到可获得盲盒..." CTA in their tab |
| 100+ assets in one tab | Pagination (12/page). Show total count in tab label |
| User closes overlay without confirming | Selections survive (live-updating). No data loss. |
| User refreshes page mid-config | Draft is lost (current behavior matches existing `hasBarterDraft`). Acceptable for v1. |
| Rapid toggle on multiple cards | Debounced 100ms (existing). No changes needed. |
| Long asset names | Truncated with ellipsis. Full name in `title` attribute. |
| Network error loading assets | Error shown in composer panel (existing). Overlay won't open if assets fail to load. |

---

## Step 6: Mobile / Responsive

- Overlay: Full-screen on mobile (`width: 100%; height: 100%; border-radius: 0`)
- Tab bar: Horizontally scrollable on mobile (2 tabs visible + scroll)
- Filter bar: Search input full-width, filter pills wrap
- Asset grid: `repeat(auto-fill, minmax(80px, 1fr))` on mobile
- Composer summary card: Stack give/get on mobile (1 column)
- Sticky footer: always visible on mobile

---

## Step 7: Implementation Priority

### Phase 0 (Prerequisite): `name` column migration
1. Migration: `2026_04_15_000008_add_name_to_collectibles.php`
2. Model: `Collectible::getNameAttribute()` — return `$value` if set, fallback `'Collectible #{$id}'`

### Phase 1: Fix current bugs (minimal, safe)
3. Fix `getOriginalProposalItems()` — remove undefined `fields` reference  
4. Add `overflow-y: auto` to `.BarterComposerPanel-column`  
5. Show empty state CTA when `payload.theirs.*` is empty

### Phase 2: New overlay + composer summary (full redesign)
6. Create `BarterConfigOverlay.tsx` (Modal-based)
7. Implement tab bar with all 4 asset categories
8. Implement filter bar (search by name substring + rarity)
9. Implement pagination (12/page)
10. Replace composer panel grid with summary card
11. Wire "编辑协商" → opens overlay
12. Live-updating summary row in overlay
13. ESC key + focus trap in overlay
14. Accessibility: all ARIA roles
15. Real images: collectibles use `gatewayUrl(ipfsCid)`, blind boxes use SVG

### Phase 3: Polish
16. Counter-offer diff in overlay header
17. Mobile responsive refinements
18. localStorage draft persistence
19. Integration tests for new flows

---

## Summary: 16 Design Decisions for the New Architecture

| # | Decision | Choice |
|---|----------|--------|
| 0 | Collectible name | Nullable `name` DB column, accessor returns it or fallback `#id` |
| 1 | Overlay type | Flarum Modal (not route) |
| 2 | Overlay container | max-width 800px, centered, rounded |
| 3 | Tab count | 4 tabs (我的藏品, 我的盲盒, 对方藏品, 对方盲盒) |
| 4 | Card size | 90px (tighter than current 100px) |
| 5 | Items per page | 12 |
| 6 | Filter approach | Search (name substring, humans use names not IDs) + rarity pills |
| 7 | Composer replacement | Summary card with "编辑协商" button |
| 8 | Summary card content | Give/Get with truncated names + count |
| 9 | Empty state | Guided CTA per tab type |
| 10 | Close behavior | ESC / × / Cancel — selections survive |
| 11 | Live updates | Summary row in overlay updates as user selects |
| 12 | Counter-offer | Diff shown in overlay header |
| 13 | Accessibility | Full ARIA roles, focus trap, aria-live |
| 14 | Mobile overlay | Full-screen, border-radius 0 |
| 15 | Images | Real IPFS for collectibles, SVG for blind boxes — NO icon fallbacks |

---

## Wireframes

Generated: `.context/barter-config-wireframe.html`

The wireframe shows two screens:
1. **Full-screen config overlay** — My Assets tab, with tab bar, filter pills, paginated asset grid, live summary card at top
2. **Composer return view** — Summary card replaces the grid, with "编辑协商" edit button and a thread panel showing existing negotiation history

Open the HTML file in a browser to interact with the prototype (tabs and card selection are functional).

## GSTACK REVIEW REPORT

| Review | Trigger | Why | Runs | Status | Findings |
|--------|---------|-----|------|--------|----------|
| CEO Review | `/plan-ceo-review` | Scope & strategy | 0 | — | — |
| Codex Review | `/codex review` | Independent 2nd opinion | 0 | — | — |
| Eng Review | `/plan-eng-review` | Architecture & tests (required) | 1 | PASS | 4 decisions, 3 fixes, 7 tests planned |
| Design Review | `/plan-design-review` | UI/UX gaps | 1 | PASS | Full overlay, 15 decisions locked |
| DX Review | `/plan-devex-review` | Developer experience gaps | 0 | — | — |

**VERDICT:** Eng review complete. All issues resolved. Implementation plan locked.

---

## Engineering Review Decisions

### Scope (Step 0)

**Confirmed reusable artifacts:**
- `Schema\Str::make('name')` in CollectibleResource — already readable via API
- `Endpoint\Update::make()->authenticated()` — `name` is already writable
- `BarterComposerPanel` utility functions — all reusable in overlay
- `displayCollectibleName()` in frontend — already handles name vs fallback
- `BarterAssetFormatter::collectible()` — already serializes `name`
- `Modal` base class pattern from `CollectibleDetailModal.tsx`

**4 decisions resolved:**

| Decision | Choice |
|---|---|
| Collectible naming UX | Inline edit in `CollectibleDetailModal` (pencil icon → inline input → save) |
| Overlay state sharing | Pass `composer` via attrs (`<BarterConfigOverlay composer={this.composer} />`) |
| Old collectibles (name = NULL) | Batch prompt in overlay: "N 件未命名藏品待处理" banner + quick-name submodal |
| Name ownership check | Fix in `updating()` hook (matches existing `isShowcase` pattern) |

### Architecture (Section 1)

**[FIXED] `name` serialization null vs fallback:**
- `BarterAssetFormatter` uses `$collectible->name` → triggers accessor → always returns string
- But `CollectibleResource` uses raw DB value for `/api/collectibles`
- **Fix:** Add `->get(fn ($model) => $model->name)` to `Schema\Str::make('name')` in `CollectibleResource.php:104`

### Code Quality (Section 2)

**[FIXED in Phase 1] `fields` ReferenceError** at `BarterComposerPanel.tsx:147`:
- `getOriginalProposalItems()` references `fields` which is not in scope
- **Fix:** Add `const fields = ensureBarterComposerFields(this.attrs.composer);` locally

**[Phase 1 CSS] Overflow truncation:**
- `.BarterComposerPanel-column` has no `overflow-y` or `max-height`
- **Fix:** Add `overflow-y: auto; max-height: 50vh;` to LESS `.BarterComposerPanel-column`

**[Deferred to Phase 2] P2/P3 in existing composer code:**
- `renderPreSendSummary()` shows `theirBlindBoxes` instead of `yourBlindBoxes` — Phase 2 replaces this
- Missing `theirCollectibles` from pre-send message — Phase 2 replaces this

### Tests (Section 3)

| # | Test | Type | Coverage |
|---|---|---|---|
| 1 | `Collectible::getNameAttribute()` null case | Unit | New |
| 2 | `Collectible::getNameAttribute()` with value | Unit | New |
| 3 | Owner can update name via API | Integration | New |
| 4 | Non-owner CANNOT update name via API | Integration | New |
| 5 | `displayCollectibleName()` null / value / legacy fallback | Unit | New |
| 6 | `BarterProposalChainTest` fixtures with `name` column | Integration | Modify existing |
| 7 | Search filter by name substring | Unit | New |

Frontend overlay interactions → headed Playwright.

### Performance (Section 4)

- `/barter-assets` returns all assets in one shot — acceptable for current scale, paginate in v2
- Overlay pagination is client-side on already-fetched array — <10ms, fine
- localStorage persistence: debounce writes to 500ms on every toggle

---

## Implementation Order

```
Phase 0 (Prerequisite):
  [ ] Migration: 2026_04_15_000008_add_name_to_collectibles.php
  [ ] Model: Collectible::getNameAttribute() with $value param
  [ ] Resource: name ->get() in CollectibleResource
  [ ] Resource: updating() ownership hook for name
  [ ] Tests 1-5, 7 (unit tests)
  [ ] Test 6 (integration fixture update)

Phase 1 (Bug fixes — quick wins):
  [ ] Fix fields ReferenceError in getOriginalProposalItems()
  [ ] Fix CSS overflow on .BarterComposerPanel-column
  [ ] Run full test suite

Phase 2 (Full redesign — overlay + composer summary):
  [ ] Create BarterConfigOverlay.tsx (Flarum Modal)
  [ ] Implement 4-tab bar + filter bar + pagination
  [ ] Replace composer panel grid with summary card
  [ ] Wire "编辑协商" → opens overlay
  [ ] ESC + focus trap + aria roles
  [ ] Real images (IPFS for collectibles, SVG for blind boxes)
  [ ] Batch naming prompt for old collectibles (NULL names)
  [ ] localStorage persistence (debounced 500ms)
  [ ] Headed Playwright tests

Phase 3 (Polish):
  [ ] Counter-offer diff in overlay header
  [ ] Mobile responsive refinements
  [ ] Integration tests for new flows
```
