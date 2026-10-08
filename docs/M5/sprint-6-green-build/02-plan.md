# 02 — Plan (M5 Sprint 6: Green Build)

## Goal
SPEC-001 exit criterion 1 — full suite green under `php artisan test`. Fix the two verified root causes behind the 5 pre-existing failures. Nothing else.

## Scope (from 01-analysis §5)
### F1 — SyncOutbox table name (fixes OfflineSyncTest ×3)
- `app/Models/SyncOutbox.php`: add `protected $table = 'sync_outbox';`
- Data source of truth: migration `0001_01_01_000015` (creates `sync_outbox` with uuid pk — matches model's string/non-incrementing key config).
- Business rule: none changed — pure bug fix; `SyncService`, `SyncController`, routes, tests untouched.
- DB: none (model follows existing schema).
- Expected effect: `SyncOutbox::find/create` hit the real table → test1 processed/already_processed, test2+3 conflict reasons per `OrderStatus::canTransitionTo` (logic already correct).

### F2 — Publish Sanctum migration (fixes SanctumTokenTest ×2)
- New file `database/migrations/2019_12_14_000001_create_personal_access_tokens_table.php` = **verbatim copy** of `vendor/laravel/sanctum/database/migrations/2019_12_14_000001_create_personal_access_tokens_table.php` (sanctum v4.3.3).
- This is the standard `personal_access_tokens` table Sanctum requires (`HasApiTokens::createToken`); its absence also breaks production token creation — publishing it restores the intended REQ-05 design.
- **Flagged for owner**: this adds one migration file; content is unmodified vendor code, not an invented schema.
- Business rule: none. Code outside migrations untouched.

### Explicitly NOT in scope
- No changes to tests (any post-fix failure = newly discovered defect → stop, document, report).
- No changes to Sprints 3–5 files, sync logic, API controllers, routes, views.
- No payment webhook route (blocked: C-07 mocks/credentials), no load-test script (tooling decision), no Web Push (infra decision), no C3–C7 analytics (owner-deferred).
- No offline-sync E2E journey (was blocked by RC-1; now unblocked for a future sprint — note only).

## Verification plan
1. Targeted: `php artisan test --filter="OfflineSyncTest|SanctumTokenTest"` → expect 7/7 pass.
2. Full suite → expect **78 passed / 0 failed / ≥239 assertions**.
3. `php vendor/bin/pint --test` on `app/Models/SyncOutbox.php` + the new migration file.
4. Report triage: new Sprint 6 failures vs pre-existing vs environment (sqlite `:memory:` class issues should be **gone** once F1/F2 land — they were schema bugs, not environment quirks; the report must re-classify them accordingly).

## Execution order
1. ✅ 01-analysis.md
2. ✅ 02-plan.md (this file)
3. F1 (model line) → targeted sync tests
4. F2 (publish migration) → targeted sanctum tests
5. Full suite + pint
6. Docs 03–07 reflecting actual outcomes only
