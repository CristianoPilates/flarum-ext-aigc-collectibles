# Barter Composer Redesign — Design Review Plan

**Review scope:** BarterComposerPanel, BarterThreadPanel, BarterProposalCard, and their surrounding interaction patterns within Flarum's MessageComposer.

**Rating: 5/10** — Functional but generic. The grid-of-thumbnails approach works but misses the emotional weight of trading real digital possessions.

## What a 10 looks like for THIS plan

The composer should feel like opening a physical trade binder, not filling out a spreadsheet. Every asset card should have visual presence. The "give / get" asymmetry should be immediately readable. The thread panel should feel like a negotiation record, not a bug report.

---

## 1. Information Architecture

### 1.1 Current state

The composer is a flat two-column grid. Every section (your collectibles, your blind boxes, their collectibles, their blind boxes) competes for equal visual weight. A user with 3 collectibles and 20 blind boxes sees the same grid density as someone with the reverse.

### 1.2 Design decisions needed

- **Sort order within each column**: By rarity (legendary first) or by recency? Currently API returns in unspecified order.
- **Grouping strategy**: Keep collectibles and blind boxes separate within each column, or merge into a single pool per side?
- **Empty side treatment**: "No assets" is shown as gray placeholder text. What does the user do when they have nothing to offer?
- **Max visible items**: If a user has 50 collectibles, does the grid scroll? Truncate? Paginate?

### 1.3 Decision needed

Should the two sides always show equal total items, or each show exactly what they have? (Current: unequal, which makes comparison harder.)

---

## 2. Visual Hierarchy

### 2.1 Current state

The header (kicker + title + enable button) uses the same visual weight as the asset grid. The column headers ("YOUR OFFER" / "THEIR OFFER") are tiny uppercase labels. The asset thumbnails are 100px squares — too small to appreciate an image, too large to show many.

### 2.2 Design decisions needed

- **Primary anchor**: What does the user see first when the panel opens? The header or the asset grid? Currently equal weight.
- **"Give / Get" contrast**: The asymmetry is labeled but not visually emphasized. A user should immediately sense "what I put in vs what I get out."
- **Rarity as visual signal**: Rarity badges exist (Common/Rare/Epic/Legendary) but are small text. Should legendary items get larger cards, glow effects, or special positioning?
- **"YOUR OFFER" vs "THEIR OFFER" labels**: Currently uppercase small text. Should these be redesigned as section headers with more personality?

### 2.3 Reference: The BlindBoxCard

The existing `BlindBoxCard` has strong visual hierarchy (shell, seal overlay, glow effects, kicker, type name, budget). The `BarterAssetCard` in the composer is flat by comparison. **Recommendation**: Align the composer card's visual language with the `BlindBoxCard` pattern — use the same spacing scale, the same type of glow for legendary items.

---

## 3. Asset Card Design

### 3.1 Current state

Thumbnail grid (100px minmax) with IPFS image or blind box SVG. Check badge in top-right corner. Name + meta line below. Hover: border darkens + shadow.

### 3.2 Design decisions needed

- **Card size**: 100px is too small for IPFS images to be recognizable. Should be 120-140px minimum for collectibles to show meaningful detail.
- **Selected state**: Blue border + light blue background. Does this adequately communicate "selected"? Consider a checkmark with count summary ("3 selected") rather than per-card checks for better UX with many items.
- **Collectible vs blind box visual distinction**: Currently both use the same card shell. Should blind box cards retain the SVG + glow treatment from `BlindBoxCard`?
- **"No assets" empty state**: Gray text placeholder. Should this guide the user (e.g., "Get blind boxes by daily check-in")?
- **Long names / 47-char names**: The name truncates with ellipsis. Is there room to show the full name on hover?

### 3.3 Recommendation

Use a two-tier selection UX:
1. Tap/click to select (current per-card check)
2. Show a running summary above the grid: "3 collectibles, 2 blind boxes selected" so the user doesn't have to scan every card.

---

## 4. Interaction Patterns

### 4.1 Current state

- Click card to toggle selection
- Refresh button reloads assets from API
- Enable/disable toggle collapses/expands the panel
- Submit sends the message + creates the proposal

### 4.2 Design decisions needed

- **Confirmation before sending**: When the user clicks send with assets selected, is there a confirmation step? Currently it goes straight to submission.
- **Counteroffer UX**: When revising a proposal (counter), the current selections are pre-filled. Should there be a visual diff showing "I removed X, added Y"?
- **Loading state**: Spinner while assets load. Is there a skeleton/shimmer state?
- **Undo/reset**: Can the user clear all selections at once? Currently not obvious (has to disable + re-enable).

### 4.3 Recommendation

Add a "Clear selections" button that appears only when selections exist. Add a confirmation summary: "You'll give 2 collectibles for 1 blind box" before send.

---

## 5. BarterThreadPanel — Design

### 5.1 Current state

Horizontal scrolling card list in the message stream. Cards show: status badge, revision number, proposer meta, two-column asset preview, action buttons.

### 5.2 Design decisions needed

- **Panel position**: Currently in the stream alongside messages. Should it be in a sidebar (like Flarum tags) instead? This would give it more visual prominence.
- **Horizontal scroll**: Standard for this pattern, but on mobile it can be disorienting. Is there a better layout for mobile?
- **Completed vs active proposals**: Completed proposals get a green border. Should active proposals (negotiating) get a different treatment to draw attention?
- **"发起协商" button**: This button opens the composer. Is it clear this creates a proposal, not a message? The label might confuse users into thinking they're just chatting.

### 5.3 Recommendation

Rename "发起协商" (Start Negotiation) to something clearer like "发起交换" (Start Exchange) or "我要交换" (I want to exchange). "协商" (negotiate) sounds adversarial.

---

## 6. BarterProposalCard

### 6.1 Current state

Compact card with: status badge, revision, proposer, two-column asset list, action buttons (Accept / Reject / Counter / Cancel).

### 6.2 Design decisions needed

- **"Accept" is the primary action**: It should be visually dominant. Currently all actions are equal-weight buttons.
- **"Counter" (还价)**: This is a key interaction. Should it be more prominent? Currently same size as Cancel.
- **Completed proposal display**: After acceptance, does the card stay in the panel? Currently yes, with reduced opacity.
- **What if a proposal is superseded**: The card shows "已被第 N 版替代" (superseded by revision N). Should superseded cards be collapsible or visually demoted further?

### 6.3 Recommendation

Use button hierarchy: "Accept" = primary (filled blue), "Counter" = secondary (outlined), "Cancel / Reject" = tertiary (text only).

---

## 7. Accessibility

### 7.1 Current gaps

- Cards use `role="checkbox"` with `tabIndex={0}` and keyboard handler. This is good but incomplete.
- `aria-checked` is set but no `aria-label` describing what is selected.
- Error messages are text only — no `role="alert"` or `aria-live` region.
- No focus trap within the expanded panel.
- Color contrast on status badges: check background `#DBEAFE` on `#F8FAFC` — should verify.

### 7.2 Design decisions needed

- Should the panel trap focus when expanded, or allow natural tab flow?
- Is there a screen reader announcement when selection count changes?

---

## 8. Mobile / Responsive

### 8.1 Current state

Mobile breakpoint exists at 768px: grid goes to 1 column, thread panel cards go to 78vw. But the composer is inside Flarum's MessageComposer which may not be mobile-optimized.

### 8.2 Design decisions needed

- Does the composer panel push the text editor off-screen on mobile?
- Should the two-column layout stack on mobile (yours on top, theirs below)?
- Are tap targets (44px minimum) met on mobile?

---

## 9. Edge Cases

### 9.1 Design decisions needed

- **47-char collectible name**: Truncates with ellipsis. Where does the full name appear? Tooltip? Expand?
- **Zero collectibles, many blind boxes**: The "YOUR OFFER" column could be all blind boxes. Is the visual variety sufficient?
- **Network failure during load**: Shows error text. Should there be a retry button inline?
- **Rapid toggle**: User clicks rapidly. No debounce. Could cause race conditions.
- **Proposal with no items on one side**: Should this be prevented at the UI level before submit? Currently only backend validation.

---

## 10. Summary of Design Decisions Needed

From the review above, here are the concrete decisions to lock down before implementation:

1. **Card size**: 100px or 120-140px for composer asset thumbnails?
2. **Selection UX**: Per-card checkmarks or running summary counter?
3. **Visual richness**: Should composer cards match `BlindBoxCard` glow/seal treatment?
4. **Panel placement**: Stay in stream or move to sidebar?
5. **Button hierarchy**: Primary/secondary/tertiary for Accept/Counter/Cancel?
6. **"发起协商" label**: Keep, or rename?
7. **Sort order**: Rarity-first or recency-first?
8. **Mobile layout**: Stack columns or keep current approach?
9. **Confirmation step**: Show summary before send, or go straight?
10. **Accessibility**: Focus trap + aria-live for selection changes?

---

## Appendix: Current LESS Variables Reference

Used in the design system:
- `@rarity-common: #9CA3AF` / bg: `#F3F4F6` / border: `#D1D5DB`
- `@rarity-rare: #3B82F6` / bg: `#EFF6FF` / border: `#93C5FD`
- `@rarity-epic: #8B5CF6` / bg: `#F5F3FF` / border: `#C4B5FD`
- `@rarity-legendary: #F59E0B` / bg: `#FFFBEB` / border: `#FCD34D`

Composer container: 14px padding, 16px border-radius, gradient background `#FFFFFF` → `#F8FAFC`.

Selected state: `#2563EB` border, `#DBEAFE` background.
