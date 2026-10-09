# Requirements Status — SPEC-001 Traceability

- **Audit date:** 2026-10-09 · **Commit:** `755eafd` (supersedes `1490268`; statuses re-verified against live routes/tests at `755eafd`)
- **Source:** `docs/ai-sdlc/specs/SPEC-001.md` (requirement IDs and terminology preserved)
- **Counting method:** 21 counted SPEC-001 items (REQ-01…REQ-21). BLOCKED/DEFERRED excluded from the denominator. 20/21 COMPLETE, REQ-13 PARTIAL (map UI) → **~95%**.
- **Verification legend:** 🧪 automated-test verified · 👁 manually verified · ⏳ not verified.

| REQ | Description | Status | Evidence (files / routes / tests) | Verification | Remaining work / risk |
|---|---|---|---|---|---|
| REQ-01 | Customer registration/login, phone stored | COMPLETE | `Auth\RegisterController`, `Auth\LoginController` (+deactivation block), `users.phone`; `CustomerRegistrationTest`, `AccountDeactivationTest` | 🧪 | None |
| REQ-02 | Merchant registration + admin approval gate | COMPLETE | approval endpoints + `AuditLog`, pending-block via `approved` middleware; `ApprovalGateTest`, `DashboardTest` | 🧪 | None |
| REQ-03 | Driver registration + approval before assignment | COMPLETE | driver approval gate; `AssignOrderJob` nearest-online; `ApprovalGateTest`, `AssignmentTest`, `DeliveryFlowTest` | 🧪 | None |
| REQ-04 | No public admin; seeder admin; audit-logged admin actions | COMPLETE | forced-role register, `AdminSeeder`, `ApprovalController` audit; `AdminLockdownTest` | 🧪 | None |
| REQ-05 | Session auth + RBAC Blade; Sanctum API | COMPLETE | `RoleMiddleware`, Sanctum tokens + scoping; `RbacRouteTest`, `SanctumTokenTest`, `LoginRateLimitConfigTest` | 🧪 | None |
| REQ-06 | Merchant profile + catalog CRUD | COMPLETE | merchant/category/product CRUD + delete; `CatalogTest` | 🧪 | None |
| REQ-07 | Browse → cart → checkout → order → history | COMPLETE | `OrderService`, web checkout + API; `OrderFlowTest`, `EndToEndFlowTest` | 🧪 | None |
| REQ-08 | Full lifecycle + server-side transition validation + events/notifs/audit | COMPLETE | `OrderStatus::canTransitionTo`, transitions dispatch `OrderStatusChanged` → `SendOrderNotifications` + audit; `OrderStateMachineTest` | 🧪 | None |
| REQ-09 | Merchant intake: notify/accept/reject(reason)/ready | COMPLETE | merchant order endpoints, `AssignOrderJob` on ready; `OrderHandlingTest` | 🧪 | None |
| REQ-10 | Driver ops: online toggle, assignment, accept/pickup/deliver, history | COMPLETE | driver dashboards, delivery lifecycle, `DriverStatsService`; `DeliveryFlowTest`, `DriverStatsTest` | 🧪 | None |
| REQ-11 | Driver offline queue, idempotency, auto-sync, conflict resolution | COMPLETE | `SyncService::processBatch` + `mirrorDeliveryState`, `/api/sync`; `Sync\OfflineSyncTest`, `OfflineSyncJourneyTest`, `SyncAuthorizationTest` | 🧪 | Ownership gate now enforced (see `security/`) |
| REQ-12 | COD/Jawwal/PalPay mocks, server verify, idempotent | COMPLETE | `PaymentGateway` + 3 drivers + `PaymentService`, unique `reference_id`, `POST /api/payments/callback/{gateway}`; `CodTest`, `GatewayCallbackTest`, `PaymentWebhookTest` | 🧪 (mock drivers) | Live gateway credentials pending |
| REQ-13 | Maps: pinning/routing/live/polling/privacy | **PARTIAL** | Server tracking complete (`TrackingService` ingest/freshness/privacy; `TrackingPrivacyTest`); map UI + routing/ETA implemented (`GoogleMapsProvider`, `partials/map.blade.php`, `TrackingMapPageTest`, `GoogleMapsProviderTest`) | 🧪 code paths | **Live map render/route/ETA unverified** — no Google Maps keys |
| REQ-14 | In-app + Web Push; SMS critical; email receipts; modular | COMPLETE (in-app) / BLOCKED (push-live) | In-app fanout complete (`SendOrderNotifications`, 7 notification classes); push channel `WebPushChannel` + `WebPushService` (degraded-by-default) + `WebPushTest`; **no SMS/email channel, no VAPID keys** | 🧪 (degraded + mocked) | **Live push unverified** — no VAPID keys |
| REQ-15 | Admin approve/reject w/ reason; filter; all logged | COMPLETE | admin approval UI/routes + audit; `DashboardTest`, `AdminAnalyticsTest` | 🧪 | None |
| REQ-16 | Basic orders/sales reporting | COMPLETE | sales report totals by day; `ReportingTest` | 🧪 | None |
| REQ-17 | 1–3s propagation, 5–10s pings, concurrency | BLOCKED (load-test execution) | Polling cadence + freshness gate in code; k6 script `k6/load-test.js` delivered | ⏳ (k6 not installed) | **Load test not executed** — install k6 + run, record JSON |
| REQ-18 | Hashing/HTTPS/CSRF/validation/rate-limit/sessions/least-privilege + deactivation + anonymization | COMPLETE | `DeactivateController` (atomic), audit, `AccountDeactivationTest` (13), RBAC + Sanctum, login rate limit | 🧪 | HTTPS is deployment-only (see `deployment/`) |
| REQ-19 | Ratings 1–5, merchant+driver, non-anonymous, post-delivery | COMPLETE | `RatingService` (delivered-only, owner-only, one-per-order); E2E + owner-scope tests | 🧪 | None |
| REQ-20 | Sanctum API, token scoping, shared service layer | COMPLETE | `/api/v1/orders`, `/api/sync`, scoped tokens, shared services; `SanctumTokenTest` | 🧪 | None |
| REQ-21 | NFR placeholder | COMPLETE (vacuous) | Empty requirement; NFR substance lives in REQ-17/18/20 | — | None |

> Note: the previous Final Project Readiness Audit and current repo agree on all statuses above. Three earlier sprint docs claiming the payment webhook was absent are stale — corrected in commit `1dc2583`.
