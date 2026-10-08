# M5 Sprint 7 — Offline-Sync E2E Journey: Analysis

Date: 2026-10-07. Phase 1 audit — no application code touched; all findings from code reading + executed baselines.

## 1. Roadmap position (why this is the next executable item)
Sprint 6 (reviewed per instructions) restored offline sync + Sanctum tokens and made the suite 78/78 green, explicitly logging in `07-final-summary` §Deferred: *"Offline-sync E2E journey — was blocked by F1; now unblocked and available for a future sprint."* Remaining roadmap items are all blocked or awaiting owner decisions (payment webhook = C-07 mocks; REQ-17 load-test tool choice; REQ-14 Web Push infra; C3–C7 analytics deferred). **→ Offline-sync E2E journey is the next executable roadmap step, now approved by the owner.**

## 2. What exists (verified this session)
- **Route**: `POST /api/sync` (`routes/api.php`, name `api.sync`, middleware `auth:sanctum`) — outside the `v1` group; baseline run confirms `OfflineSyncTest` (4/4) and `SanctumTokenTest` (3/3) green post-Sprint 6.
- **`Api\SyncController@sync`**: validates `actions[]` (`client_uuid`, `type` required; `payload` array; `at` nullable string) → `SyncService::processBatch(Auth::user(), actions)` → JSON `{ack, conflicts}`.
- **`SyncService::processBatch`** (read line-by-line, M4 code — unchanged): per action → idempotency by `client_uuid` (`SyncOutbox::find`, `status=completed` → `already_processed`) → order lookup → **state-machine gate** `OrderStatus::canTransitionTo` → on invalid: conflict `{reason: invalid_transition, from, to}`, server state retained → on valid: `DB::transaction { OrderService::transition (event + audit) + outbox row status=completed }` → `processed`. Order is **re-fetched each loop iteration**, so a batch sees its own prior effects.
- **`phpunit.xml`**: `QUEUE_CONNECTION=sync` → `AssignOrderJob` runs inline during web `merchant.orders.ready` (Sprint 5 audit), so a journey can reach `assigned` through real routes first.
- **Prereqs restored by Sprint 6**: `sync_outbox` table reachable via model (F1), `createToken()` works (F2) → **bearer-token API journey now possible** (SanctumTokenTest proves bearer precedence).
- **Contrast path (web)**: `Driver\DeliveryController::pickup/deliver` update **both** the order (`OrderService::transition`) **and** the `deliveries` row (`status`, `picked_up_at`, `delivered_at`).
- **Existing coverage to not duplicate**: `OfflineSyncTest` = single-action, session-auth, per-rule slices (idempotency replay, invalid transition, stale action, 401). `EndToEndFlowTest` = web-only COD/cancel/rating journeys. **No test drives: multi-action batch + bearer auth + web-setup journey start + downstream convergence.**

## 3. Anticipated integration gap (flagged BEFORE coding — to verify empirically)
`SyncService` transitions **only the `orders` row**; it never touches the `deliveries` row (no `status`/`picked_up_at`/`delivered_at` mirroring). If confirmed, a driver completing deliveries offline would leave:
- `deliveries.status` stuck at `assigned`/`out_for_delivery` → driver's active-deliveries list never clears, history never shows the run,
- `DriverStatsService` (counts `deliveries.status='delivered'`) reporting 0 completed/earnings for synced deliveries.
This contradicts nothing in SPEC-001's sync text directly (REQ-11 speaks of *order* state sync), but creates **cross-path divergence**: same logical action updates 2 rows via web path, 1 row via sync path. Audit decision: **verify with evidence during implementation, do NOT silently change `SyncService`** — report as a new discovery with a proposed fix requiring owner approval (per this sprint's instructions: no modifying completed M4–6 work unless strictly required; decisions to be flagged).

## 4. Spec anchors (no invented rules)
- REQ-11 (US-31): driver queues status actions with idempotency key + timestamp; auto-sync on reconnect; **no duplicates**; conflicts resolved by **server timestamp + state-machine validation**; SPEC: *"the conflict is reported and server state is retained"*.
- REQ-05: Sanctum prepared for API (bearer tokens).
- C-05/US-70/Sprint-3 earnings rule unchanged for any convergence assertions.


## Decision 2 audit — "Should sync create Delivery rows?" (added after Sprint 7b approval — ANALYSIS ONLY, NOT IMPLEMENTED)

Evidence-based answers to the six owner questions; no code was changed for Decision 2.

**Q1 — When is a Delivery row normally created?** Exactly one production path: `AssignOrderJob::handle()` creates it (`Delivery::create(['order_id', 'driver_id', 'status' => 'assigned'])`, job lines 72–76) after selecting the nearest online driver. The job is dispatched only by `Merchant\OrderController@markReady` when an order reaches `ready_for_pickup`. The only other creators are test fixtures. The row is *deleted* by `Driver\DeliveryController@reject` (order returns to pool). Everything post-creation (pickup/deliver timestamps, statuses) is done by web `DeliveryController` — and, as of Sprint 7b Decision 1, mirrored by `SyncService` for offline actions.

**Q2 — Which actor is responsible?** The **system/assignment algorithm**, triggered indirectly by the merchant's "mark ready" action — never the driver, and never the client directly. A driver only ever acts on a row that already names them.

**Q3 — What is required to create a valid Delivery?** - `order_id` (FK), `driver_id` (the assigned driver — schema-nullable but semantically mandatory: `DeliveryPolicy::manage` gates all driver actions on `$user->id === $delivery->driver_id`; `DriverStatsService` counts by `driver_id`; notifications target it), `status='assigned'`. The *decision of which driver* requires: merchant coordinates + latest `DriverLocation` per candidate, `users.is_online=true`, `status=active`, haversine-nearest ranking (C-05) — none of which exist in a sync payload.

**Q4 — Can a sync payload legitimately contain only `order_id`?** For the spec'd use case, **yes**: REQ-11/US-31 covers a driver queueing **their own status actions** for work already assigned to them — `SyncController` validates only `client_uuid/type/payload/at`, and `SyncService` reads `payload.order_id`. A payload with only `order_id` can express *"this order advanced a state"* but **cannot** express *"assign order X to driver Y"* — driver identity would be inferred from the authenticated `$user`, which is precisely the problem (see Q5).

**Q5 — Would automatic creation conflict with the existing flow?** Yes, five concrete conflicts: 1. **Bypasses C-05**: any authenticated driver syncing `assigned` would claim an order regardless of distance/availability (the syncing driver in `OfflineSyncTest` isn't even online or near the merchant). 2. **Races `AssignOrderJob`**: if the client's queued `assigned` wins, the nearest-driver algorithm never runs; if the job wins, `assigned→assigned` is invalid and the action conflicts — nondeterministic assignment outcomes. 3. **Skips eligibility gates**: `is_online`/`active` checks live only in `AssignOrderJob`; sync performs no driver-ownership or eligibility validation (pre-existing: sync has no ownership concept at all). 4. **Timing divergence**: assignment today fires exactly once at `ready_for_pickup`; a queued `assigned` could arrive arbitrarily later (or from a different driver than the one offered). 5. **Reject-loop interaction**: web reject deletes the row and returns the order to `ready_for_pickup`; a stale queued `assigned` would race that reset. **Q6 — Safest recommended behavior (for your future decision):** **Recommended: keep current behavior — sync never creates Delivery rows** (Decision 1's guard clause already enforces this). Assignment remains exclusively `AssignOrderJob`'s responsibility; clients should not queue `assigned` actions (guidance-level fix only). If offline *claiming* is ever genuinely needed, it must be designed as an explicit new flow — e.g. a dedicated action type validated server-side against the same nearest-driver/eligibility rules (extracted from `AssignOrderJob` into a shared service) plus driver-ownership checks — which is new business logic requiring a spec decision (modifying REQ-11 scope), not a patch. **Status: awaiting explicit owner approval; not implemented.**

- Response contract `{ack: [{uuid, status}], conflicts: [{uuid, reason, from, to}]}` is existing behavior — tests assert it as-is.

## 5. Test-support facts
- `POST driver.toggle-online` (web, session) flips `is_online` — usable for the offline/reconnect narrative. **Observation**: `SyncService` does not gate on `is_online` (client-side concept) — existing behavior, documented, not changed.
- Batch `[assigned, out_for_delivery, delivered]` from `ready_for_pickup` is transition-legal (map: ready→assigned→out_for_delivery→delivered) — note this path creates **no** `deliveries` row at all (only `AssignOrderJob` does); relevant to §3 gap, will be documented as observed behavior, not asserted as desired.
- Notifications via sync path: events fire inside the transaction → `OutForDelivery`→customer, `OrderDelivered`→customer+merchant, `assigned`→ no listener case (no notification) — assertable evidence that fanout works off the API path too.

## 6. Decisions: outcome

### Decision 1 — delivery-row state mirroring on sync-delivered orders (Q1–Q6 audit + fix)
- **Status: APPROVED AND IMPLEMENTED — recorded as Sprint 7b, executed after the test-only 7a sprint in the same sprint review.**
- The gap flagged in §3 was verified empirically with a temporary probe during the sprint (probe evidence captured in 03-implementation.md): a driver completing deliveries offline leaves `deliveries.status` at `assigned` — `picked_up_at`/`delivered_at` unset — so the driver's active-deliveries list never clears and `DriverStatsService` reports `completed:0/earnings:0` for synced work. Web path updates both rows; sync path updates one → **cross-path divergence**.
- **Implementation (Sprint 7b):** owner approved the service-layer fix during the Sprint 7 review. `app/Services/SyncService.php` gained `mirrorDeliveryState()`: inside the existing sync success transaction it mirrors the driver's **existing** `deliveries` row when present — `out_for_delivery` → `status` + `picked_up_at`; `delivered` → `status` + `delivered_at`; `failed` → `status`. An existing row is always updated; nothing is created; no schema, route, view or test changes; no Sprint 3–6 code touched. The audit's proposed fix is therefore **implemented, not experimental**.
- **Tests:** Sprint 7a adds `tests/Feature/E2E/OfflineSyncJourneyTest.php` (3 tests, 39 assertions) covering idempotency replay, stale/conflict handling and downstream convergence. Sprint 7b adds no new test file — the full suite (81/81) and existing `OfflineSyncTest`/`DeliveryFlowTest` regressions-check the change; no new test asserts the mirroring behavior (sprint 7b approval covered the service layer, not a test-driven change).

### Classification: Sprint 7a vs Sprint 7b
- **Sprint 7a (approved scope):** NEW `tests/Feature/E2E/OfflineSyncJourneyTest.php` + this documentation set — test-only, zero application-code changes.
- **Sprint 7b (owner-approved decision executed in the same sprint):** `app/Services/SyncService.php` delivery-row state mirroring (`mirrorDeliveryState`). Doc-only changes: 01 (+decision outcome), 02 (+7a/7b scope), 03 + 07 (reflect 7b outcome).
