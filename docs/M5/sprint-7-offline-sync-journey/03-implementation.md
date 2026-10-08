# 03 — Implementation (M5 Sprint 7: Offline-Sync E2E Journey)

## Approved-scope outcome: test-only sprint (as planned in 02)
**No application code was modified.** The audit's prediction (01-analysis §3) was verified empirically with a temporary probe and reported as a decision item rather than silently fixed — per this sprint's instruction not to modify completed M4–6 work without approval.

## New files
1. **`tests/Feature/E2E/OfflineSyncJourneyTest.php`** — 3 tests, 39 assertions (final, probe removed):
   - `test_offline_journey_queued_batch_syncs_with_bearer_token` — full journey: web cart→checkout→accept→ready (inline `AssignOrderJob`) → driver `POST driver.toggle-online` offline → queued 2-action batch → `POST /api/sync` with **Sanctum bearer token** → `processed`×2, order delivered, 2 `sync_outbox` rows (`user_id`=driver) → **replay** → `already_processed`×2, exactly 1 row per uuid → notifications (`OutForDelivery`→customer, `OrderDelivered`→customer+merchant) → admin sales report shows the order (US-70 convergence).
   - `test_stale_offline_action_cannot_regress_delivered_order` — one-request **3-action batch** `ready→assigned→out_for_delivery→delivered` (re-fetch-per-loop semantics proven) then stale action → `conflicts[0].reason=invalid_transition, from=delivered`, order unchanged, **no outbox row** for the stale uuid. Also documents observed behavior: pure-sync assignment creates no `deliveries` row (payload carries only `order_id`).
   - `test_mixed_batch_reports_partial_conflict_and_keeps_server_state` — batch `[delivered (illegal), assigned (legal)]` → single response with both `conflicts` and `ack`; server advanced only to `assigned`; outbox holds only the legal action's uuid (SPEC: "conflict reported, server state retained").
2. `docs/M5/sprint-7-offline-sync-journey/01…07` documentation set.

## Probe protocol (temporary code, removed after evidence capture)
During T1's first run, two `fwrite(STDERR, …)` lines inspected the `deliveries` row and `DriverStatsService` after sync. **Observed output (evidence):**
```
PROBE delivery_status=assigned picked_up=no delivered_at=no
PROBE stats={"completed":0,"active":1,"earnings":0,"rating":null,"per_order":10,"currency":"ILS"}
```
→ Confirms §3 gap: order `delivered` via sync, but `deliveries` row frozen at `assigned` → driver's active list never clears, history never shows the run, earnings undercount. Probe lines (and the now-unused `Delivery` import) were removed; final file contains no probe code (verified: `grep -i probe` empty).

## Modified files: **none** (application code, existing tests, routes, views, models, services all untouched — Sprint 3–6 deliverables re-verified green in the full run).

## Pint
`pint` run on the new file (line-ending normalization); `pint --test tests/Feature/E2E` → PASS (2 files).

## Sprint 7b (post-approval — implemented)
Decision 1 (delivery-row state mirroring on sync-delivered orders) was approved by the owner during the Sprint 7 review and implemented as a service-layer change after the test-only 7a sprint:

- **New:** `app/Services/SyncService.php` — `mirrorDeliveryState()`: mirrors the driver's existing `deliveries` row when present inside the sync success transaction — `out_for_delivery` → `status` + `picked_up_at`; `delivered` → `status` + `delivered_at`; `failed` → `status`. An existing row is always updated; nothing is created; no migrations, routes, views, tests or existing code touched.
- **Verification:** full suite **81 passed, 0 failed (293 assertions)** — two consecutive green runs; `pint --test tests/Feature/E2E` PASS; no existing test modified.
- **Classification:** Sprint 7a = test-only (no app code); Sprint 7b = the `SyncService` service-layer fix. Both are committed as separate M5 commits (4 and 5) per the approved grouping.
