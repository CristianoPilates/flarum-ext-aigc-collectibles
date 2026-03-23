# Collectibles & Blindbox Decoupling — Design Plan

Don't worry about the front-end code, don't overthink it too much.
Now is the refactoring stage.
My idea is to link the src/Api/Resource/ to src/Command/ to src/Provider/ to src/Service/Contracts/ and then to src/Service/ and finally to src/Model/. After that, use tests/integration/api/ as a guardrail to prevent drift.

Agile development, just make sure this feature's integration test passes for me.
Believe me, right now, only the src/Api/Resource/Web3AccountResource.php route is one that satisfies me,
the rest of the features are still "half-finished", and we will completely refactor them in the future. If you want to refer to the pattern, just read CLAUDE.md directly.

## Why Decouple

In the original design, a blind box was just an integer counter on the check-in record (`blindboxes`), and the `collectibles` table carried fields that conceptually belong to the blind box phase. This causes three problems:

- Blind boxes have no lifecycle — their state cannot be tracked
- The collectibles table is polluted with fields that belong to the pre-collectible stage
- The intended two-step interaction (appraise → open) is impossible to implement

## Schema Changes

### `collectibles` — Streamline

```diff
- user_id              → owner_id (rename)
- original_user_id     (drop — low value, reconstructable from event logs)
- name                 (drop — no LLM naming, low value)
- generation_params    (drop — seed belongs to blindbox, not collectible)
+ metadata_cid         IPFS CID pointing to ERC-721 standard metadata JSON
```

`aigc_prompt`, `rarity`, and `times_traded` are kept as denormalized fields for fast queries and frontend display.

`metadata_cid` resolves to a JSON document following the ERC-721 metadata standard:

```json
{
  "name": "Collectible #1042",
  "description": "AI-generated collectible",
  "image": "ipfs://QmImageCID",
  "external_url": "https://yourforum.com/collectibles/1042",
  "attributes": [
    { "trait_type": "Rarity", "value": "Epic" },
    {
      "trait_type": "AIGC Prompt",
      "value": "cyberpunk city, neon rain, oil painting"
    },
    { "trait_type": "Budget", "display_type": "number", "value": 100 }
  ]
}
```

### `blindboxes` — New Table

| Column           | Description                                                     |
| ---------------- | --------------------------------------------------------------- |
| `id`             | Primary key                                                     |
| `user_id`        | Owner (FK → users)                                              |
| `type`           | Box type; determines which phrase pool categories are available |
| `seed`           | Random hex seed used for PoW                                    |
| `status`         | `unappraised` → `appraised` → `opened`                          |
| `budget`         | Quantified PoW result; nullable, populated after appraisal      |
| `collectible_id` | FK → collectibles; nullable, populated after opening            |

### `phrase_pools` — New Table

Each row is a drawable prompt fragment, tagged with a `category` and a `cost`.

| Column      | Description                                            |
| ----------- | ------------------------------------------------------ |
| `id`        | Primary key                                            |
| `category`  | e.g. `subject`, `style`, `mood`, `detail`, `modifier`  |
| `phrase`    | e.g. `oil painting`, `cyberpunk city`, `ethereal glow` |
| `cost`      | Budget points consumed when this phrase is drawn       |
| `is_active` | Soft toggle                                            |

### `blindbox_draw_rules` — New Table

Many-to-many rule table. Defines which `pool_category` values a given `blindbox_type` may draw from, and whether at least one phrase from that category is required.

| Column          | Description                                              |
| --------------- | -------------------------------------------------------- |
| `id`            | Primary key                                              |
| `blindbox_type` | e.g. `checkin_reward`                                    |
| `pool_category` | e.g. `subject`                                           |
| `required`      | Whether the category must contribute at least one phrase |

## Core Mechanism

```
Check-in → Create Blindbox(unappraised, seed = random)
                │
     User "Appraise" → Frontend PoW (seed, 10s time limit)
                │       Backend validates hash
                │       Leading zeros → budget → rarity
                │       Blindbox(appraised, budget = N)
                │
     User "Open"     → Backend queries draw_rules for allowed categories
                       Spends budget to randomly purchase phrases from phrase_pools
                       Assembles prompt → calls AIGC service → generates image
                       Uploads to IPFS → creates Collectible
                       Blindbox(opened, collectible_id = X)
```

## Implementation Plan

### Phase 1 — Foundation: Schema + Models

```
1a. New migration: create_blindboxes_table
1b. New migration: create_phrase_pools_table
1c. New migration: create_blindbox_draw_rules_table
1d. Alter migration: collectibles (drop columns, rename user_id, add metadata_cid)
1e. New Model: Blindbox.php
1f. New Model: PhrasePool.php
1g. New Model: BlindboxDrawRule.php
1h. Update Model: Collectible.php (reflect column changes)
```

### Phase 2 — Wire into Check-in Flow

```
Update CheckinService.php:
  Before: $record->blindboxes += 1
  After:  Create Blindbox instance (type=checkin_reward, seed=random, status=unappraised)
```

### Phase 3 — Blindbox Operation API _(deferred)_

```
POST /blindboxes/{id}/appraise
POST /blindboxes/{id}/open
```

### Phase 4 — AIGC + IPFS Integration MVP _(deferred)_
