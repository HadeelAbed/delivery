# 02 — Plan (M5 Sprint 7: Offline-Sync E2E Journey)

## Goal
One new E2E test file proving the offline-driver journey end-to-end through real routes: web setup → go offline → queued batch → reconnect via **bearer-token** `POST /api/sync` → idempotent replay → stale/conflict handling → downstream convergence (notifications, admin report). Zero app-code changes in the approved scope.

## Approved scope
**NEW `tests/Feature/E2E/OfflineSyncJourneyTest.php`** — 3 tests:

| # | Test | Journey / contract covered (spec anchor) |
|---|---|---|
| T1 | `test_offline_journey_queued_batch_syncs_with_bearer_token` | seed merchant+product+online driver+location → customer cart→checkout (cod) → merchant accept→ready (auto `AssignOrderJob`, sync queue) → driver **goes offline** (`POST driver.toggle-online`) → queued batch `[{j-1, out_for_delivery, at t1}, {j-2, delivered, at t2}]` → `POST /api/sync` **Bearer token** → 200, ack `processed`×2, order `delivered`, outbox rows (`user_id`=driver) → **replay identical batch** → `already_processed`×2, still exactly 2 rows, order unchanged (REQ-11 no-duplicates) → notifications `OutForDelivery`→customer, `OrderDelivered`→customer+merchant (events fire off API path) → admin sales report includes the order (US-70 convergence) |
| T2 | `test_stale_offline_action_cannot_regress_delivered_order` | order driven to `delivered` **via a sync batch** `[assigned, out_for_delivery, delivered]` from `ready_for_pickup` (transition map legal; documents that pure-sync path creates no `deliveries` row — §3 gap evidence) → stale action `{fresh-uuid, out_for_delivery, at now-5min}` → 200 with `conflicts[0].reason=invalid_transition`, `from=delivered` → order **stays** `delivered` (server state retained) → **no outbox row** for the stale uuid (REQ-11 conflict rule) |
| T3 | `test_mixed_batch_reports_partial_conflict_and_keeps_server_state` | order `ready_for_pickup` → batch `[{m-1, delivered (illegal now)}, {m-2, assigned (legal)}]` → response has ack `processed` for m-2 **and** conflict `invalid_transition` for m-1 in one payload → order ends `assigned`, exactly 1 outbox row (m-2) (SPEC: conflict reported + server state retained; existing partial-batch behavior asserted as-is) |

## Explicitly NOT in scope
- **No changes to `SyncService`, `SyncController`, routes, models, views, or any Sprint 3–6 files.**
- No weakening/modification of any existing test (`OfflineSyncTest`, `EndToEndFlowTest`, etc. stay untouched).
- Delivery-row decision 1 (§3 gap) was verified empirically (temporary probe, then removed) and reported as a new discovery. Owner approved the service-layer fix **during the Sprint 7 review** — implemented as **Sprint 7b** in `app/Services/SyncService.php` (`mirrorDeliveryState`). Sprint 7a itself stayed test-only.
- No notifications beyond asserting existing fanout; no new business rules.

## Verification plan
1. New file green: `php artisan test --filter=OfflineSyncJourneyTest` → 3/3.


---

## Sprint 7b (post-approval) — scope update (2026-10-07)

| Item | Sprint 7a (as planned) | Sprint 7b (owner-approved) | Sprint 7b (decision-only) |
|---|---|---|---|
| SyncService delivery mirroring (`out_for_delivery`/`delivered`/`failed`) | planned review item **P2** | **approved & implemented** | — |
| `deliveries` row auto-creation on sync `assigned` | recorded as discovery + proposed fix (owner approval required) | **NOT implemented — audit only** | **Decision 2 audit written (01-analysis.md)**; implementation gated on explicit approval |

Changes this sub-sprint: `app\Services\SyncService.php` (+mirrorDeliveryState, Sprint 7b); `tests/Feature/E2E/OfflineSyncJourneyTest.php` + docs (Sprint 7a). Doc-only changes: 01 (+decision outcome), 02 (+7a/7b scope), 06 (issue reclassified), 03 + 07 (adjusted to reflect the implemented 7b) — 

2. Full suite: expect **81 passed, 0 failed** (78 + 3), assertions ≥ 254.
3. `pint --test` on the new test file (only touched PHP file).
4. Report triage: new vs pre-existing vs environment (env: MySQL-down `migrate:status` caveat from Sprint 6 still applies; suite unaffected).

## Execution order
1. ✅ 01-analysis.md → 2. ✅ 02-plan.md → 3. Write test file (incl. temporary §3 probe assertion) → 4. Run & record probe evidence, remove probe → 5. Full suite + pint → 6. Docs 03–07 reflecting actual outcomes → 7. Final report with approval flags.
