# 06 — Tests (M5 Sprint 7)

## Tests added: 1 new file — `tests/Feature/E2E/OfflineSyncJourneyTest.php` (3 tests, 39 assertions)
| Test | Asserts |
|---|---|
| `test_offline_journey_queued_batch_syncs_with_bearer_token` | 200 + ack `processed`×2; order `delivered`; `sync_outbox` rows (uuid, user_id, completed); **replay** → `already_processed`×2 + exactly 1 row/uuid + state unchanged; notifications via API path; admin report convergence |
| `test_stale_offline_action_cannot_regress_delivered_order` | 3-action batch in one request reaches `delivered`; stale action → `invalid_transition` (`from=delivered`), server state retained, **0** outbox rows for the stale uuid; documents no-`deliveries`-row behavior on pure-sync assignment |
| `test_mixed_batch_reports_partial_conflict_and_keeps_server_state` | one payload contains `conflicts` (m-1) + `ack` (m-2); order ends `assigned`; outbox contains only m-2 |

## Tests modified/weakened: **NONE**
No existing test file was touched (verified: only Sprint 7 docs + the new test file were written this session). `OfflineSyncTest`, `EndToEndFlowTest`, and all M1–M4 suites run unchanged and green.

## Verification runs
| Run | Result |
|---|---|
| `--filter=OfflineSyncJourneyTest` (with probe) | 3 passed; probe evidence captured (see 03) |
| `--filter=OfflineSyncJourneyTest` (final, probe removed) | **3 passed (39 assertions)** |
| Full suite run #1 | **81 passed, 0 failed (293 assertions)** |
| Full suite run #2 | **81 passed, 0 failed (293 assertions)** |
| `pint` on new file | FIXED line endings → `pint --test tests/Feature/E2E` **PASS (2 files)** |

## Failure triage
- **New Sprint 7 failures: NONE.**
- **Pre-existing failures: NONE** (0/0 since Sprint 6 — remains green).
- **Environment issues:** none affecting tests. (Carry-over note: `.env` MySQL server still unavailable in this session for `artisan migrate` — tests run on sqlite `:memory:`, unaffected; documented in Sprint 6.)

## Coverage deltas
- REQ-11 now has **E2E coverage**: multi-action batches, bearer-token auth, replay idempotency, stale conflict, partial-batch semantics — complementing `OfflineSyncTest`'s single-action slices.
- REQ-05: token-authenticated sync exercised outside the `SanctumTokenTest` slice.
- Previously-untested combination: web-setup → offline → API-sync → web-report convergence (cross-path).

## Newly discovered issue (documented, then resolved)
Delivery-row divergence on sync-delivered orders (probe evidence in 03): `deliveries.status` remained `assigned`; `DriverStatsService` reported `completed:0/earnings:0` for offline-completed work. The divergence is a **real, approved service-layer fix (Sprint 7b)**: `mirrorDeliveryState()` in `SyncService` updates the existing `deliveries` row when present, so `DriverStatsService` now reports completed work correctly for synced deliveries. No test asserts this as desirable behavior (7b approval covered the service layer, not a test-driven change).
