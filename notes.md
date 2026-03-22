**Integration test files created (5 files, 35+ test cases):**

| File                           | Chain                                                   | Tests                                                                                                                                                                                     |
| ------------------------------ | ------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `CheckinChainTest.php`         | POST /api/checkin-records/checkin                       | 5 tests: guest blocked, successful checkin, duplicate checkin, reward setting, last_checkin_at update                                                                                     |
| `OpenBlindBoxChainTest.php`    | POST /api/collectibles/generate                         | 5 tests: guest blocked, successful generation, insufficient boxes, original_user_id correctness, multiple opens                                                                           |
| `BindWalletChainTest.php`      | POST /api/web3-accounts/nonce + POST /api/web3-accounts | 6 tests: guest blocked, nonce request, invalid address, full bind flow, existing wallet rejection, address reuse                                                                          |
| `MintCollectibleChainTest.php` | POST /api/collectibles/{id}/mint                        | 5 tests: guest blocked, successful mint, already minted, no wallet, wrong owner                                                                                                           |
| `TradeChainTest.php`           | POST/DELETE /api/trades + accept/reject                 | 12 tests: create (guest, success, self-trade, insufficient), accept (success, wrong user, cancels others), reject (success, wrong user), cancel (success, wrong user), full e2e lifecycle |

**Also fixed:**

- Added missing `POST /api/collectibles/{id}/mint` endpoint to `CollectibleResource.php` (the MintCollectible command/handler existed but had no route)
- Added `RetrievesAuthorizedUsers` trait to all test classes

**To run integration tests**, you need a MySQL database:

```bash
DB_HOST=localhost DB_DATABASE=flarum_test DB_USERNAME=root DB_PASSWORD=... php tests/integration/setup.php
php vendor/bin/phpunit -c tests/phpunit.integration.xml --testdox
```

---

## TDD Learning Plan (Project-Specific)

You already have a solid base (integration chains + unit service tests). The best next step is to train **strict Red → Green → Refactor** on one vertical slice at a time.

### 0) Working Agreement (always follow)

1. **Red**: write one failing test first.
2. **Green**: write the smallest production code to pass.
3. **Refactor**: clean names/duplication while keeping tests green.
4. Never add new behavior without a failing test.
5. Keep each cycle small (5–20 minutes).

### 1) 2-Week Training Roadmap

#### Week 1 — Stabilize fundamentals (Service-level TDD)

- Day 1: `CheckinServiceTest` (happy path + duplicate checkin + reward setting)
- Day 2: `BlindBoxServiceTest` (spend edge cases, insufficient balance, atomic decrement)
- Day 3: `TradeServiceTest` (self-trade reject, insufficient boxes, ownership transfer)
- Day 4: `AIGCServiceTest` and `IPFSServiceTest` (timeouts, retryable failures)
- Day 5: Refactor day (extract helpers, improve test naming, remove duplication)

#### Week 2 — API chain TDD (Integration-level)

- Day 1: `CheckinChainTest` (auth, idempotency, response schema)
- Day 2: `OpenBlindBoxChainTest` (202 flow, failure refund, event dispatch side effects)
- Day 3: `BindWalletChainTest` (nonce lifecycle + signature verification)
- Day 4: `MintCollectibleChainTest` + `TradeChainTest`
- Day 5: Refactor + add missing regression tests from bugs found in week

### 2) Immediate Focus: `BindWalletChainTest.php`

Use this file as your first strict TDD kata. Add these tests **one by one**:

1. `nonce_cannot_be_reused_after_successful_bind`
2. `bind_fails_when_nonce_is_expired_or_missing`
3. `bind_fails_when_nonce_address_mismatch`
4. `bind_fails_when_signature_verification_fails`
5. `nonce_is_case_insensitive_for_address_comparison`

Suggested cycle:

- Write first failing test.
- Run only this class.
- Make minimal code change.
- Re-run until green.
- Refactor test setup (extract request builders/helpers).

### 3) Definition of Done (for each new test)

A task is done only if:

- Test fails first for the expected reason.
- Test passes with minimal implementation.
- Existing suite stays green.
- Error message is user-meaningful (`422` validation payloads clear).
- No logic moved into resource endpoint closures (keep CQRS boundaries).

### 4) Daily Practice Template (30–60 min)

1. Pick **one** tiny behavior.
2. Write failing test.
3. Make it pass.
4. Refactor.
5. Commit with message style:
   - `test: add failing case for nonce replay`
   - `feat: prevent nonce replay by consuming cache key`
   - `refactor: extract wallet request factory in tests`

### 5) Progress Tracker

- [ ] I can keep Red-Green-Refactor loops under 20 minutes.
- [ ] I avoid adding behavior without tests.
- [ ] I can design tests from business rules (not implementation details).
- [ ] I can refactor safely with confidence from test coverage.
- [ ] I have at least 3 regression tests from real bugs.

### 6) Recommended Next Step (today)

Start with: **`nonce_cannot_be_reused_after_successful_bind`** in `tests/integration/api/BindWalletChainTest.php`.
It gives immediate value (security + correctness) and is perfect TDD practice.
