# 04 — Database (M5 Sprint 6)

## Changes: exactly one new migration file, no alterations to any existing migration or table.

| Change | Table | Kind |
|---|---|---|
| NEW `2019_12_14_000001_create_personal_access_tokens_table.php` | `personal_access_tokens` | standard Sanctum table (verbatim vendor content), previously never published |

- No columns added/removed/renamed anywhere else.
- **No rename of `sync_outbox`**: the schema was already correct (uuid pk, FK user_id, type, payload, client_timestamp, status, result) — the *model's* assumed name was wrong; fixed model-side (03-implementation) so existing databases remain valid with zero schema migration.
- `down()` provided (drops `personal_access_tokens`); migration runs cleanly under `RefreshDatabase` (proven by the 78-test suite migrating it on every test).

## KPI/derived data doctrine
Unchanged — Sprint 6 persists nothing new beyond Sanctum's own table.
