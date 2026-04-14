# Barter Composer Redesign — Design Review Plan

**Review scope:** BarterComposerPanel, BarterThreadPanel, BarterProposalCard, and their surrounding interaction patterns within Flarum's MessageComposer.

**Rating: 5/10** — Functional but generic. The grid-of-thumbnails approach works but misses the emotional weight of trading real digital possessions.

## What a 10 looks like for THIS plan

The composer should feel like opening a physical trade binder, not filling out a spreadsheet. Every asset card should have visual presence. The "give / get" asymmetry should be immediately readable. The thread panel should feel like a negotiation record, not a bug report.

---

## 1. Information Architecture

### 1.1 Current state

The composer is a flat two-column grid. Every section (your collectibles, your blind boxes, their collectibles, their blind boxes) competes for equal visual weight. A user with 3 collectibles and 20 blind boxes sees the same grid density as someone with the reverse.

### 1.2 ~~Design decisions needed~~ — LOCKED

- **Sort order**: `rarity first, legendary on top`. API stays unmodified; frontend sorts by rarity tier before rendering.
- **Grouping strategy**: Keep collectibles and blind boxes separate within each column. No change to current structure.
- **Empty side**: Add CTA in empty state. Show placeholder text with guidance (e.g., "每日签到可获得盲盒，开始交换吧").
- **Max visible items**: No pagination for v1. Grid scrolls naturally with `auto-fill`.
- **Equal vs exact display**: Each side shows exactly what it has. No equalization.

### 1.3 Decision needed

~~Should the two sides always show equal total items, or each show exactly what they have?~~ **LOCKED: Each side shows exactly what it has.**

---

## 2. Visual Hierarchy

### 2.1 Current state

The header (kicker + title + enable button) uses the same visual weight as the asset grid. The column headers ("YOUR OFFER" / "THEIR OFFER") are tiny uppercase labels. The asset thumbnails are 100px squares — too small to appreciate an image, too large to show many.

### 2.2 ~~Design decisions needed~~ — LOCKED

- **Primary anchor**: Header + enable button. No change. Asset grid appears after user opts in.
- **Give/Get contrast**: Keep current two-column layout. Add running summary above grid (see Section 3).
- **Rarity as visual signal**: Keep flat cards with border-color distinction only. No glow effects in composer. Glow stays in `BlindBoxCard` / detail / showcase context.
- **Section labels**: Keep "YOUR OFFER" / "THEIR OFFER" uppercase labels. Minor styling polish (slightly larger font, more letter-spacing).

---

## 3. Asset Card Design

### 3.1 Current state

Thumbnail grid (100px minmax) with IPFS image or blind box SVG. Check badge in top-right corner. Name + meta line below. Hover: border darkens + shadow.

### 3.2 ~~Design decisions needed~~ — LOCKED

- **Card size**: Keep 100px. Trade-off accepted: density wins over image detail at this stage.
- **Selected state**: Per-card checkmark stays. Add running summary above the asset grid: "已选：3 藏品，2 盲盒".
- **Collectible vs blind box distinction**: Same card shell. Collectibles show IPFS image, blind boxes show SVG. No additional visual differentiation.
- **"No assets" empty state**: Add CTA text guiding user to check-in. e.g., "每日签到可获得盲盒，开始交换吧".
- **Long names**: Truncate with ellipsis at 12px/2 lines. Tooltip on hover for full name (native `title` attribute).

### 3.3 Recommendation (implementation note)

Add a `<BarterSelectionSummary>` component above the asset grid, visible only when `counts.yours > 0 || counts.theirs > 0`. Shows: "已选：{N} 藏品，{N} 盲盒".

---

## 4. Interaction Patterns

### 4.1 Current state

- Click card to toggle selection
- Refresh button reloads assets from API
- Enable/disable toggle collapses/expands the panel
- Submit sends the message + creates the proposal

### 4.2 ~~Design decisions needed~~ — LOCKED

- **Confirmation before send**: Add pre-send summary row in the composer header. Shows: "你将给出 {N} 藏品，换取 {N} 盲盒". User sees this before submitting. No modal.
- **Counteroffer UX**: When revising a proposal, show change diff in composer header: "原：2 藏品 1 盲盒 → 现在：3 藏品 1 盲盒". Show added items in green, removed in red.
- **Loading state**: Keep current spinner text. No skeleton/shimmer for v1.
- **Reset/Clear selections**: Add a "清除选择" text button, visible only when selections exist. Replaces the current enable/disable toggle as the way to clear.

---

## 5. BarterThreadPanel — Design

### 5.1 Current state

Horizontal scrolling card list in the message stream. Cards show: status badge, revision number, proposer meta, two-column asset preview, action buttons.

### 5.2 ~~Design decisions needed~~ — LOCKED

- **Panel position**: Keep in message stream, horizontal scroll. This is the established pattern. No sidebar for v1.
- **Mobile horizontal scroll**: Stack to 1 column on mobile. Current breakpoint approach is sufficient.
- **Completed vs active proposals**: Keep current green border treatment for completed. No additional visual emphasis for v1.
- **"发起协商" button label**: Keep. Description is accurate enough.

---

## 6. BarterProposalCard

### 6.1 Current state

Compact card with: status badge, revision, proposer, two-column asset list, action buttons (Accept / Reject / Counter / Cancel).

### 6.2 ~~Design decisions needed~~ — LOCKED

- **Action button hierarchy**: Accept = blue filled (primary), Counter = blue outlined (secondary), Cancel/Reject = text-only (tertiary). Use existing `.Button--primary` for Accept, `.Button` outlined for Counter, `Button--text` for Cancel/Reject.
- **Superseded proposals**: Keep current "已被第 N 版替代" note. Collapsing is out of scope for v1.
- **Counter button prominence**: Same visual treatment as Accept at the card level. Hierarchy is enough.

---

## 7. Accessibility

### 7.1 Current gaps

- Cards use `role="checkbox"` with `tabIndex={0}` and keyboard handler. This is good but incomplete.
- `aria-checked` is set but no `aria-label` describing what is selected.
- Error messages are text only — no `role="alert"` or `aria-live` region.
- No focus trap within the expanded panel.
- Color contrast on status badges: check background `#DBEAFE` on `#F8FAFC` — should verify.

### 7.2 ~~Design decisions needed~~ — LOCKED

- **Focus trap**: Implement focus trap when panel is expanded. Trap Tab navigation within the panel.
- **aria-live**: Add `aria-live="polite"` region that announces selection count changes: "{N} 件已选".
- **Error announcements**: Use `role="alert"` for validation error messages.
- **Focus management**: When panel expands, focus moves to the first asset card.

---

## 8. Mobile / Responsive

### 8.1 ~~Design decisions needed~~ — LOCKED

- **Mobile layout**: Stack to 1 column (your on top, theirs below). Current approach is sufficient for v1.
- **Tap targets**: Ensure 44px minimum touch target for all interactive elements.
- **Composer on mobile**: Accept that the composer is compressed on mobile. No special panel treatment.

---

## 9. Edge Cases

### 9.1 ~~Design decisions needed~~ — LOCKED

- **Rapid toggle**: Add debounce (100ms) to selection toggle to prevent race conditions.
- **Backend-only validation as guard**: Keep backend validation for "no items on one side" — no UI-level blocking beyond guidance.
- **Network failure on load**: Keep current error text. Retry via refresh button. No inline retry for v1.

---

## 10. Summary of Design Decisions — LOCKED

| # | Decision | Choice |
|---|----------|--------|
| 1 | Card size | Keep 100px |
| 2 | Selection UX | Per-card check + running summary |
| 3 | Rarity glow | Flat, border-color only |
| 4 | Panel placement | Message stream, horizontal scroll |
| 5 | Button hierarchy | Primary / Secondary / Tertiary |
| 6 | "发起协商" label | Keep |
| 7 | Sort order | Rarity first, legendary on top |
| 8 | Mobile layout | Stack to 1 column |
| 9 | Confirmation step | Pre-send summary row |
| 10 | Accessibility | Focus trap + aria-live |

### Implementation checklist

- [ ] Add `<BarterSelectionSummary>` component above asset grid
- [ ] Sort assets by rarity before rendering in each column
- [ ] Add "清除选择" text button (visible when selections > 0)
- [ ] Add pre-send summary row in header ("你将给出 N 藏品，换取 N 盲盒")
- [ ] Add counter diff display (added in green, removed in red)
- [ ] Add CTA text in empty side placeholder
- [ ] Add `aria-live="polite"` region for selection count announcements
- [ ] Add `role="alert"` to validation error messages
- [ ] Implement focus trap when panel expands
- [ ] Add 100ms debounce to toggle selection
- [ ] Apply button hierarchy to `BarterProposalCard` actions
- [ ] Style polish: section labels slightly larger font
- [ ] Update LESS variables for new components

---

## Appendix: Current LESS Variables Reference

Used in the design system:
- `@rarity-common: #9CA3AF` / bg: `#F3F4F6` / border: `#D1D5DB`
- `@rarity-rare: #3B82F6` / bg: `#EFF6FF` / border: `#93C5FD`
- `@rarity-epic: #8B5CF6` / bg: `#F5F3FF` / border: `#C4B5FD`
- `@rarity-legendary: #F59E0B` / bg: `#FFFBEB` / border: `#FCD34D`

Composer container: 14px padding, 16px border-radius, gradient background `#FFFFFF` → `#F8FAFC`.

Selected state: `#2563EB` border, `#DBEAFE` background.
Selected check: `#2563EB` circle with white checkmark icon.
