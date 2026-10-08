# PLAN-001 — Implementation Plan for SPEC-001

| Field | Value |
|-------|-------|
| Spec | `docs/ai-sdlc/specs/SPEC-001.md` (21 REQs, 10 stories, 8 conflicts — all resolved) |
| Plan mode | Plan only — smallest change that satisfies the spec |
| Baseline | Laravel 12 skeleton (fresh): users/cache/jobs migrations, default routes, `tests/Feature|Unit/ExampleTest.php` |
| Intent status | **approved** — decisions C-02…C-08 folded into intent; SPEC-001 baselined |
| Queue config | **Reuse existing**: `QUEUE_CONNECTION=database`, jobs table migration already present |
| Filament | **None exists in repo** — architect rule "reuse Filament exporters" has nothing to reuse; admin = Blade pages (intent L78 forbids React, no Filament requirement in intent). Surfaced as assumption A-01. |

**Governing rule (architect.md):** every milestone below has a **Rollback note** and a **Test list mapped to SPEC-001 acceptance criteria**.

---

## Confirmed Decisions (owner-approved — were planning assumptions A-01…A-07)

| ID | Decision | Conflict resolved |
|----|----------|-------------------|
| D-01 | Admin dashboard built with Blade (no Filament/React) | C-08 |
| D-02 | Single states: `merchant_accepted`, `preparing`, `ready_for_pickup`, `assigned`, `out_for_delivery`, `delivered`, `failed`, `cancelled`; merchant rejection → `cancelled`; target "within 3 seconds" | C-03 |
| D-03 | OTP deferred in V1; phone stored for contact only | C-02 |
| D-04 | Fixed delivery fee + fixed driver earnings per order, configurable in `config/delivery.php` | C-04 |
| D-05 | Assign the **nearest available driver first** | C-05 |
| D-06 | Ratings: 1–5 integers, separate `merchant_score`/`driver_score`, **not anonymous** | C-06 |
| D-07 | **Mock Payment Drivers** behind a replaceable `PaymentGateway` interface; real Jawwal/PalPay integrate later without core changes | C-07 |
| D-08 | No fixed approval SLA in V1; manual Admin approval | C-08 |

---

## Package decisions (minimal additions)

| Need | Decision (smallest change) |
|------|----------------------------|
| API auth (REQ-20) | **Add** `laravel/sanctum` only |
| Web Push (REQ-14) | **Add** `minishlink/web-vapid` (tiny, no framework coupling); channel behind `PushChannel` interface |
| SMS (REQ-14) | **No package** — `SmsChannel` interface + `FakeSms` driver (A-03) |
| Google Maps (REQ-13) | **No SDK** — Laravel HTTP client for Directions/Geocoding; JS via official `<script>` include in Blade |
| Broadcast/realtime (REQ-17) | **No websocket package in V1** — events dispatched (`ShouldBroadcast`-ready) + polling endpoints at 5–10s (intent allows "broadcast with polling fallback", intent L146) |
| Queue | **Reuse** `QUEUE_CONNECTION=database` config as-is; Horizon/Redis deferred (budget A-rule) |
| Filament | **Not added** (A-01) |

Total new packages: **2** (sanctum, web-vapid).

---

## Milestones

### M1 — Foundation: schema + auth + RBAC (intent W1)

**Build (smallest increments):**
1. Migrations: add `role`, `status` to `users`; new tables `merchants`, `products` (+`categories`), `orders`, `order_items`, `deliveries`, `payments`, `driver_locations`, `ratings`, `notifications`, `audit_logs`, `sync_outbox` (columns per SPEC-001 §1/REQ-08, DB table intent L375–386).
2. `RoleMiddleware` (`customer|merchant|driver|admin`) + Gates per REQ-05; admin blocked from public registration (forced-role on `RegisterController`, US-03).
3. `AdminSeeder` (single admin via env password).
4. Approval-gate: `status=active|pending|rejected` + `approve/reject` admin endpoints writing `audit_logs`.
5. Blade auth screens + role layouts skeleton (`resources/views/layouts/{customer,merchant,driver,admin}.blade.php`).

**Rollback note:** migrations are additive-only in M1 except `users` column adds → `php artisan migrate:rollback --step=N` reverts cleanly; delete `RoleMiddleware` registration in `bootstrap/app.php`; no data migration. If abandoning, revert single merge commit `m1-foundation`.

**Test list → SPEC-001:**
- `Auth/CustomerRegistrationTest` → US-01 ACs (valid, duplicate, phone)
- `Admin/ApprovalGateTest` → US-02 ACs (pending block, approve unlock, reject reason, driver block)
- `Admin/AdminLockdownTest` → US-03 ACs (role forced, seeder, audit row)
- `Security/RbacRouteTest` → NFR ACs (403 cross-role, rate-limit 429, Hash::check)

### M2 — Catalog + cart/checkout + order lifecycle (intent W2)

**Build:**
1. `OrderService::place/transition` — sole state-machine authority (REQ-08); transitions table validating A-02 graph; every transition dispatches `OrderStatusChanged` event + writes `audit_logs`.
2. Merchant catalog CRUD (profile fields L43, categories/items L44) + coverage gating on public browse.
3. Cart in session → checkout (address + method) → order creation; notification to merchant queued (3s AC = Queue::fake dispatch assertion).
4. Customer order history + browse pages.

**Rollback note:** all new tables; `OrderService` unused outside M2 controllers → revert commit `m2-catalog-lifecycle`, `migrate:rollback` drops tables; cart is session-only (no data residue).

**Test list → SPEC-001:**
- `Customer/OrderFlowTest` → US-10 place-order AC (totals, pending state, merchant notification queued ≤3s), reject-blocks-assignment AC
- `Merchant/CatalogTest` → US-20 create/update/availability/coverage ACs
- `Merchant/OrderHandlingTest` → US-20 accept-prep-time, ReadyForPickup notify-driver, audit ACs
- `Unit/OrderStateMachineTest` → REQ-08 valid/invalid transition matrix (A-02)

### M3 — Driver flow + assignment + tracking + notifications fanout (intent W3)

**Build:**
1. Driver profile/verification + Online/Offline toggle.
2. `AssignJob` (queued, reuses M1 queue): nearest-online driver (A-05); reject returns order to pool.
3. Pickup → `out_for_delivery`; deliver → `delivered` (status names A-02).
4. `TrackingService`: `driver_locations` ingest endpoint (5–10s) + customer polling endpoint with freshness ≤10s; privacy rules REQ-13 (active-only, owner-only).
5. `NotificationFanout` listener on `OrderStatusChanged`: in-app rows always; `PushChannel` (web-vapid); `FakeSms` allowlist; `Mail` receipts (REQ-14).
6. Google Maps Blade partial: pin customer/merchant, driver marker, Directions ETA via HTTP client (REQ-13).

**Rollback note:** notification/tracking are listeners & endpoints on existing tables → remove listener registrations to disable without schema loss; `driver_locations` is append-only (safe to drop last); revert commit `m3-driver-tracking`. Maps partial removed by deleting one Blade include.

**Test list → SPEC-001:**
- `Driver/DeliveryFlowTest` → US-30 all ACs (assignment payload, pickup activation, delivered stops tracking, offline no assignment, reject releases)
- `Tracking/TrackingPrivacyTest` → US-50 ACs (fresh position, delivered hides, third-party 403)
- `Notification/NotificationFanoutTest` → US-60 ACs (role fanout matrix, SMS allowlist, no cross-role)
- `Unit/AssignmentTest` → A-05 nearest-driver selection

### M4 — Payments + offline sync + admin dashboard/reporting (intent W4)

**Build:**
1. `PaymentGateway` interface → `CodDriver` (no-op, `pending_cod`), `JawwalDriver`, `PalpayDriver` (mock+sandbox behind config; verify on server; store `status`+`reference_id` only; idempotency = unique index on `payments.reference_id` + replay guard) (REQ-12, US-40).
2. Offline: Service Worker (cache shell + assigned-delivery payload) + IndexedDB queue; `POST /api/sync` batch endpoint with idempotency key (`sync_outbox`), server-timestamp + state-machine conflict resolution returning `ack/conflicts[]` (REQ-11, US-31).
3. Admin Blade pages: order/user filters, sales report query (delivered totals by day) (REQ-15/16, US-70).
4. Install `laravel/sanctum`; `api/v1` routes sharing M2/M3 services (REQ-20).

**Rollback note:** payments/sync are isolated modules behind interfaces → config flag `PAYMENTS_DRIVER=mock` reverts behavior without deploy; `POST /api/sync` route removal disables offline path (client falls back to online-only); unique index on `payments` is additive → rollback drops it. Revert commits `m4-payments-sync`, `m4-admin`.

**Test list → SPEC-001:**
- `Payment/CodTest` → US-40 COD AC
- `Payment/GatewayCallbackTest` → US-40 paid+schema-no-PAN, duplicate idempotent, bad signature rejected
- `Sync/OfflineSyncTest` → US-31 all ACs (no-dup on replay, out-of-order conflict, stale loses)
- `Admin/DashboardTest` → US-70 filter/approve-log ACs
- `Admin/ReportingTest` → US-70 sales report totals AC (REQ-16)
- `Api/SanctumTokenTest` → NFR ACs (401 no token, scope to owner, shared `OrderService` spy)

### M5 — Hardening + NFR verification + UAT (intent W5)

**Build/verify (no new features):**
1. Pint clean, seeders/factories for demo data, `.env.example` finalize (HTTPS/secure-cookie flags, rate-limit config).
2. Load script (k6/artillery) for REQ-17: order placement propagation ≤3s, 5–10s ping cadence, concurrency smoke — CI-nightly (outside PHPUnit per SPEC-001 §2 note).
3. Security pass: CSRF on all forms, rate-limits, least-privilege policy audit, audit-log completeness, data deletion/deactivation endpoint (REQ-18).
4. Sandbox callbacks for Jawwal/PalPay when C-07 answered; OTP when C-02 answered.

**Rollback note:** M5 is verification-first; config/hardening changes are reversible via `.env` diff (rate-limit numbers, cookie flags); load tests touch nothing; any failing hardening fix lands as normal commit on top of M4 (no structural rollback needed).

**Test list → SPEC-001:**
- Full suite `php artisan test` (all §2 PHPUnit ACs green = Exit Criteria 1)
- `Security/*` NFR scenarios (429, 403, CSRF)
- REQ-17: load-script report artifact (Exit Criteria 4)

---

## Dependency graph

```text
M1 (schema/auth/RBAC)
 └─> M2 (OrderService/lifecycle)        ← core; blocks everything
      ├─> M3 (driver/tracking/notifs)
      │    └─> M4 (payments/sync/admin)
      └─> M5 (hardening) after M4
```
Sanctum install sits in M4 (API layer is last by design — smallest V1 risk).

## Test summary matrix (SPEC-001 AC → plan test)

| Spec story/AC group | Milestone | Test file |
|---|---|---|
| US-01 registration | M1 | Feature/Auth/CustomerRegistrationTest |
| US-02 approval gate | M1 | Feature/Admin/ApprovalGateTest |
| US-03 admin lockdown | M1 | Feature/Admin/AdminLockdownTest |
| US-10 customer flow | M2 (+M3 map part) | Feature/Customer/OrderFlowTest, RatingTest |
| US-20 merchant catalog/orders | M2 | Feature/Merchant/CatalogTest, OrderHandlingTest |
| US-30 driver flow | M3 | Feature/Driver/DeliveryFlowTest |
| US-31 offline sync | M4 | Feature/Sync/OfflineSyncTest |
| US-40 payments | M4 | Feature/Payment/CodTest, GatewayCallbackTest |
| US-50 tracking privacy | M3 | Feature/Tracking/TrackingPrivacyTest |
| US-60 notifications | M3 | Feature/Notification/NotificationFanoutTest |
| US-70 admin/reporting | M4 | Feature/Admin/DashboardTest, ReportingTest |
| REQ-08 state machine | M2 | Unit/OrderStateMachineTest |
| REQ-17 performance | M5 | load script (not PHPUnit) |
| NFR security/API | M1/M4/M5 | Feature/Security/*, Api/SanctumTokenTest |

## Execution Status

- ~~**C-01:** intent still `draft` — SPEC-001 not baselined~~ → **RESOLVED**: intent approved, SPEC-001 baselined.
- C-02…C-08 → **RESOLVED** as decisions D-02…D-08 above.
- **M1 — Foundation: ✅ COMPLETE** (18 tests green, Pint clean). Schema, RBAC, auth, approval gate, admin lockdown, rate limiting, role dashboards, Blade views.
- **M2 — Catalog + cart/checkout + order lifecycle: ✅ COMPLETE** (36 tests green, Pint clean). OrderService state machine, merchant catalog CRUD, customer browse/cart/checkout, merchant order handling, notifications, audit logs.
- **M3 — Driver flow + assignment + tracking + notifications: ✅ COMPLETE** (52 tests green, Pint clean). AssignOrderJob (nearest driver), driver delivery flow, tracking service, notification fanout, online/offline toggle.
- Next: **M4 — Payments + offline sync + admin dashboard/reporting**.
