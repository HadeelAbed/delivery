# M5 Sprint 6 — Green Build (Test Suite Stabilization): Analysis

Date: 2026-10-07. Phase 1 audit — application code untouched; all findings verified by execution.

## 1. Sprint 6 scope derivation from the roadmap (audit method)
Reviewed `PLAN-001`, `SPEC-001` exit criteria, and Sprint 4/5 docs:

| Roadmap item | State after Sprint 5 | Sprint 6? |
|---|---|---|
| PLAN-001 remaining: admin dashboard refinements | done (Sprint 4) | no |
| PLAN-001 remaining: end-to-end flow tests | done (Sprint 5) | no |
| PLAN-001 remaining: payment webhook for production | **blocked** — `config/payment.php` defines callback URLs, no route exists, gateways are mocks per decision C-07 (no production credentials in repo) | no → deferred |
| SPEC-001 exit criterion 1: **"All §2 tests green under `php artisan test`"** | **NOT met — 5 failing** (3 OfflineSyncTest + 2 SanctumTokenTest), unchanged since M4 | **YES — Sprint 6 scope** |
| SPEC exit criterion 2 (every REQ ≥1 test) | met by existing suites (audit per REQ not re-run here) | no |
| SPEC exit criterion 4: load-test script (REQ-17) | absent from repo; requires choosing a tool (k6/artillery) → tooling decision for owner | no → flagged |
| REQ-14 Web Push | not implemented (database/in-app notifications only) → external infra (VAPID) decision | no → flagged |
| C3–C7 analytics | owner-deferred in Sprint 4 | no |

**Sprint 6 approved scope = the last executable roadmap gate: fix the 5 pre-existing failures so the full suite is green.**

## 2. Failure inventory (current, verified by running them)
`Tests: 5 failed, 73 passed (239 assertions)`

| # | Test | Error (verbatim from run) |
|---|---|---|
| 1 | OfflineSyncTest > queued action syncs without duplicate | `no such table: sync_outboxes ... select * from "sync_outboxes" where "sync_outboxes"."id" = u-1` |
| 2 | OfflineSyncTest > out of order action is rejected | same table error |
| 3 | OfflineSyncTest > stale action loses to newer server state | same table error |
| 4 | SanctumTokenTest > token scopes results to owner | `no such table: personal_access_tokens` on `createToken` |
| 5 | SanctumTokenTest > shared service layer is used | same token table error |

(`OfflineSyncTest > test_sync_requires_authentication` passes — it 401s before touching DB. `SanctumTokenTest > test_api_requires_token` passes — 401 path needs no table.)

## 3. Root causes (verified, not assumed)
### RC-1 — table-name mismatch (causes #1–#3)
- Migration `0001_01_01_000015_create_sync_outbox_table.php` creates **`sync_outbox`** (singular).
- `App\Models\SyncOutbox` declares no `$table` → Laravel pluralizes → queries **`sync_outboxes`** (plural) → table never exists.
- Grep of `app/`: only `SyncService` touches the model (`SyncOutbox::find` line 35, `SyncOutbox::create` line 69); no `DB::table(...)` hardcodes either name. Tests never hardcode the table (they use the model) — verified by grep of `tests/`.
- Logic check of `SyncService::processBatch` against test expectations: test1 `ready_for_pickup→assigned` valid → `processed`, replay → `already_processed`, 1 row ✓; test2 `ready_for_pickup→delivered` invalid → `invalid_transition` conflict, order unchanged ✓; test3 `delivered→out_for_delivery` invalid → conflict, stays `delivered` ✓. **No service logic changes needed — only the table name blocks these tests.**

### RC-2 — never-published Sanctum migration (causes #4–#5)
- `laravel/sanctum v4.3.3` installed; `User` uses `HasApiTokens`; `SanctumServiceProvider` exists; API routes per REQ-05.
- Vendor migration `vendor/laravel/sanctum/database/migrations/2019_12_14_000001_create_personal_access_tokens_table.php` exists, but it was **never published** to `database/migrations` (verified: 19 migrations listed, none creates `personal_access_tokens`) → `createToken()` fails in any environment, including production, not just tests.
- `Api\OrderController@show` uses `authorize('view', $order)` and `OrderPolicy::view` exists → no further latent bug expected (will verify by running).

## 4. Already covered / not to duplicate
- All 5 failing tests already exist and encode the spec'd behavior (REQ-11 offline sync US scenarios; REQ-05 token scoping). Fix = make environment/schema match what tests and spec assume. No new tests needed beyond verification runs; optionally add a schema assertion.
- Sprints 3–5 deliverables (driver stats, admin analytics, E2E journeys, rating repair) re-verified green in full run — untouched.

## 5. Proposed fixes (minimal, backward compatible)
| # | Fix | Files | Schema impact |
|---|---|---|---|
| F1 | Add `protected $table = 'sync_outbox';` to `SyncOutbox` (model follows the actual migrated table) | `app/Models/SyncOutbox.php` (1 line) | **none** |
| F2 | Publish Sanctum's standard migration verbatim (copy of vendor file, unchanged) | `database/migrations/2019_12_14_000001_create_personal_access_tokens_table.php` (**new file**) | creates `personal_access_tokens` — the standard Sanctum table that was always intended; **flagged for owner visibility** (new migration file, but not an invented schema: byte-for-byte vendor content) |

Alternative considered for F1 (renaming the migration's table to `sync_outboxes`) — rejected: would change schema for any existing database and require a data migration; the model-side fix preserves every existing DB.

## 6. Risks
- F2 is a migration file creation → report explicitly under "decisions requiring approval"; no other schema touched.
- If any of the 5 tests fails for an additional latent reason after the table fix, that becomes a new discovered defect → document and evaluate separately (do not silently change test expectations).

## 7. Verification targets
- Full suite: **78 passed, 0 failed** (73 + 5), assertions ≥ 239.
- `pint --test` on touched PHP files.
- Distinction maintained: new failures vs pre-existing vs environment.
