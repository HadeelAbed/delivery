# 04 — Database (M5 Sprint 7)

## Changes: **NONE** (no migrations created, altered, or dropped; no columns touched).

Tables exercised by the new tests (read/write through existing code paths only):
| Table | Usage in Sprint 7 tests |
|---|---|
| `sync_outbox` | rows created by `SyncService` (`id`=client_uuid, `user_id`=syncing driver, `status=completed`); counted for idempotency + conflict assertions (model table fix from Sprint 6 F1) |
| `orders` | state-machine progression via sync batches |
| `deliveries` | read-only observation: T1 row exists (created by `AssignOrderJob`) — status stays `assigned` after sync-delivery (gap evidence, see 03/07); T2 asserts that no row is created on the pure-sync path |
| `notifications` | fanout rows from events fired inside sync transactions |
| `personal_access_tokens` | created by `$driver->createToken()` (Sprint 6 F2) for bearer auth |
| `users` / `order_items` / `payments` | standard journey setup |

Sprint 6's approved Sanctum migration kept as-is; no further schema additions.
