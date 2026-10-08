# 07 — Final Summary (M5 Sprint 7: Offline-Sync E2E Journey)

## What was implemented
The roadmap's offline-sync E2E journey (unblocked by Sprint 6): one new test file proving REQ-11 end-to-end — web journey setup → driver goes offline → **queued multi-action batch synced via Sanctum bearer token** → idempotent replay → stale/conflict handling → event fanout and admin-report convergence. **Test-only sprint: zero application-code changes, zero schema changes, zero route changes, zero existing-test modifications.**

## How the feature works (journey proven)
1. Customer/merchant hops create and ready the order; `AssignOrderJob` (sync queue) assigns the nearest online driver — a `deliveries` row exists.
2. Driver flips offline (`POST driver.toggle-online`).
3. Client queues `[{uuid, type, payload, at}…]`; on reconnect `POST /api/sync` (Bearer) runs `SyncService::processBatch`: idempotency check on `client_uuid` → order lookup → **state-machine gate** → per-action transaction (order transition + event/audit + outbox row) → `{ack, conflicts}`.
4. Replay returns `already_processed` with no duplicate rows; stale/illegal actions return `invalid_transition` with server state retained and no persisted row; mixed batches report both outcomes in one payload.
5. Downstream: notifications fired from API-path events; admin sales report reflects the synced delivery.

## Main files
Created: `tests/Feature/E2E/OfflineSyncJourneyTest.php` (3 tests, 39 assertions); docs 01–07.
Modified: **none** (temporary probe written and removed within the sprint; final repo touched only by the new test + docs).

## Business rules honored (none invented)
REQ-11 idempotency + conflict rules, `OrderStatus` transition map, bearer sanctum auth (REQ-05), US-70 sales rule, Sprint 3 earnings rule — all asserted as they exist.

## Tests & current status
Full suite: **81 passed, 0 failed (293 assertions)** — two consecutive runs (was 78/254). Pint PASS on touched files. Triage: 0 new failures, 0 pre-existing failures, 0 environment issues affecting tests.

## Known issues / newly discovered (for your decision)
1. **Delivery-row divergence (NEW, evidence-backed):** `SyncService` transitions only the `orders` row. A driver completing deliveries offline leaves `deliveries.status` at `assigned` (picked_up/delivered timestamps unset) → (a) driver's active-deliveries list never clears and history never shows offline runs, (b) `DriverStatsService` reports `completed:0, earnings:0` for that work (probe: `delivery_status=assigned … stats={"completed":0,"active":1,"earnings":0}`). Web path updates both rows; sync path updates one.
   **Proposed fix (awaiting approval — not applied):** in `SyncService`'s success transaction, mirror onto the driver's existing `deliveries` row when present: `out_for_delivery` → status + `picked_up_at`; `delivered` → status + `delivered_at`; `failed` → status. Only touches an existing row (no creation), pure service-layer, no schema/route/test changes; would add focused tests. Out of scope this sprint per your "no modifying completed M4–6 work without approval" rule.
2. **Pure-sync assignment creates no `deliveries` row** (payload carries only `order_id`, no driver context beyond `$user`; documented as observed behavior in T2). Whether sync-initiated assignment should create a row is a design question tied to #1.

## Deferred / still open from earlier sprints
Payment webhook route (C-07 blocked); REQ-17 load-test script (tool choice); REQ-14 Web Push (infra); C3–C7 analytics (your deferral). Environment: MySQL dev server unavailable in this session for `artisan migrate` (suite unaffected).

## What to understand from this implementation
1. **E2E at the seam finds what slices can't**: `OfflineSyncTest` and `DeliveryFlowTest` were each green while the *junction* between their paths silently diverged — order state syncs, delivery bookkeeping doesn't. Slice tests prove rules; journey tests prove the paths agree.
2. **Bearer vs session matters**: the same endpoint behaves differently in web-first-party vs API clients; this sprint was the first E2E use of token auth on `/api/sync`.
3. **Observed-vs-desired discipline**: the gap was captured with evidence and reported as a decision instead of being fixed quietly mid-sprint — keeping your approval gates meaningful.

## Decision outcome — Sprint 7b (owner-approved, implemented)
Decision 1 from §4 (delivery-row divergence) was approved by the owner during the Sprint 7 review and implemented as the service-layer change below:

| Decision | Status | Outcome |
|---|---|---|
| Decision 1 — delivery-row state mirroring on sync-delivered orders | **Approved + implemented (Sprint 7b)** | `app/Services/SyncService.php`: `mirrorDeliveryState()` mirrors the existing `deliveries` row when present — `out_for_delivery` → `status` + `picked_up_at`; `delivered` → `status` + `delivered_at`; `failed` → `status`. No row creation; no schema/route/test changes. |
| Decision 2 — sync `assigned` should auto-create a `deliveries` row | **NOT implemented (audit only)** | Sync never creates `deliveries` rows; pure-sync assignment has no driver context. Documented as observed behavior; design question kept open for a future sprint. |

## Changes by sprint classification
- **Sprint 7a — Offline-Sync E2E journey (test-only):** NEW `tests/Feature/E2E/OfflineSyncJourneyTest.php` (3 tests, 39 assertions); docs 01–07. Zero application-code changes.
- **Sprint 7b — approved delivery-row state-mirroring fix (Decision 1):** `app/Services/SyncService.php` (`mirrorDeliveryState`). Doc-only updates: 01 (+outcome), 02 (+scope), 06 (issue reclassified), 03 + 07 (+7b outcome).

## Full suite status
Full suite after 7b: **81 passed, 0 failed (293 assertions)** — two consecutive green runs. `pint` clean on touched files. No new failures; no pre-existing failures remaining.
