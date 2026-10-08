# 03 — Implementation (M5 Sprint 6: Green Build)

Two minimal fixes; nothing else in `app/`, `routes/`, `resources/`, or `tests/` was touched.

## New files
1. **`database/migrations/2019_12_14_000001_create_personal_access_tokens_table.php`** (F2)
   - Verbatim Sanctum (v4.3.3) migration content copied from `vendor/laravel/sanctum/database/migrations/…` — verified content-identical ignoring line endings (863/863 chars), then normalized by project Pint style (line endings + anonymous-class formatting).
   - Creates `personal_access_tokens` (id, morphs tokenable, name, token unique, abilities, last_used_at, expires_at indexed, timestamps).
   - Why: `HasApiTokens::createToken()` was unusable everywhere (production included) because the table was never published. Restores REQ-05's "Sanctum prepared for API" design.
   - **Owner visibility flag**: this adds one migration file — it is vendor-standard code, not an invented schema.

## Modified files
1. **`app/Models/SyncOutbox.php`** (F1) — added `protected $table = 'sync_outbox';` with a comment referencing the migration.
   - Why: migration `0001_01_01_000015` creates singular `sync_outbox`; the model's implicit pluralized name (`sync_outboxes`) pointed at a table that never existed → every `SyncOutbox::find/create` in `SyncService` threw → the 3 OfflineSyncTest failures.
   - Model-side (not migration-side) fix chosen to keep the schema untouched for any existing database; the model's string/non-incrementing uuid key config already matches the migration's `uuid('id')->primary()`.

## Deleted files: none (Sprint 5's probe was already removed in Sprint 5).

## Explicitly unchanged (verified)
- `SyncService`, `SyncController`, `Api\OrderController`, sync/API routes, all tests, all views, everything from Sprints 3–5.
- `SyncService` logic was audited line-by-line against the 3 failing test expectations before fixing — no logic change was needed (conflict/ack behavior was already spec-correct; only the table name blocked it).

## Pint
- `pint --test` passes on both touched files (the migration was Pint-normalized after copy).
