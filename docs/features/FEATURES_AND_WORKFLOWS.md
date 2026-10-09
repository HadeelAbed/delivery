# Features & Workflows

- **Audit commit:** `1490268` · **Date:** 2026-10-09
- Each workflow lists its key routes, services/models/jobs, events, and tests. "Implemented + automated-test verified" means code exists and PHPUnit covers it; "live verified" is stated only where real external services were exercised (none, currently).

Legend: **[IT]** implemented + automated-test verified · **[LV]** live-verified (none yet) · **[P]** pending external credentials/config.

## 1. Customer registration, login, profile, deletion (REQ-01, REQ-18)

- **Routes:** `POST /register`, `POST /login`, `POST /logout`, `GET|PUT /profile`, `POST /account/deactivate` (`routes/web.php`).
- **Flow:** `RegisterController` (forced role, no admin) → `RegisterRequest` validates `role in:customer,merchant,driver` → user created `pending` (merchant/driver) or `active` (customer). `LoginController` rate-limits (`LOGIN_RATE_LIMIT`) and blocks `deactivated`. Deletion → `DeactivateController` (atomic deactivation + anonymization, see [Security §3](../security/SECURITY_OVERVIEW.md)).
- **Models:** `User` (`UserRole`, `UserStatus`).
- **Tests:** `CustomerRegistrationTest`, `AccountDeactivationTest` (13). **[IT]**

## 2. Merchant registration, approval, profile, catalog (REQ-02, REQ-06)

- **Routes:** merchant dashboard, profile, `merchant.categories.*`, `merchant.products.*` (CRUD + delete).
- **Approval:** admin `admin.merchants.*` approve/reject writes `AuditLog`; `approved` middleware blocks `pending` merchants.
- **Services/Models:** `Merchant`, `Category`, `Product` + `ProductPolicy`/`CategoryPolicy`.
- **Tests:** `ApprovalGateTest`, `CatalogTest`, `AdminLockdownTest`. **[IT]**

## 3. Driver profile, approval, assignment, delivery, earnings (REQ-03, REQ-10)

- **Routes:** `driver.toggle-online`, `driver.orders.*` (accept/pickup/deliver/fail), `driver.history`, stats.
- **Assignment:** `AssignOrderJob` picks nearest online driver, creates a `Delivery` row (`driver_id`, `status=assigned`) directly — never via sync. `DeliveryPolicy::manage` gates driver actions on ownership.
- **Services/Models:** `Delivery`, `DriverProfile`, `DriverStatsService`.
- **Tests:** `ApprovalGateTest`, `AssignmentTest`, `DeliveryFlowTest`, `DriverStatsTest`. **[IT]**

## 4. Cart, checkout, order creation (REQ-07)

- **Routes:** `customer.cart.add`, `customer.checkout.place` (`CartController`, `CheckoutController`).
- **Flow:** cart → `OrderService::place` creates `Order` (+ items) + `Payment` (via `PaymentService`/gateway) → status `pending` → merchant intake.
- **Tests:** `OrderFlowTest`, `EndToEndFlowTest`. **[IT]**

## 5. Order lifecycle & state transitions (REQ-08)

- **State machine:** `app/Enums/OrderStatus.php` (`canTransitionTo`) is the single source of truth for valid status hops. `OrderService::transition` runs the hop inside a `DB::transaction`, fires `OrderStatusChanged`, writes an audit row, and (Sprint 7b) mirrors delivery state via `SyncService::mirrorDeliveryState`.
- **Listeners:** `SendOrderNotifications` fans out notifications to customer/merchant/driver per event.
- **Tests:** `OrderStateMachineTest` (unit), `OrderHandlingTest`. **[IT]**

## 6. Merchant intake: accept / reject / ready (REQ-09)

- **Routes:** `merchant.orders.accept|reject|ready`. Reject requires a reason; `ready` fires `OrderStatusChanged` → `AssignOrderJob` (sync queue in tests) assigns nearest driver.
- **Tests:** `OrderHandlingTest`, `EndToEndFlowTest`. **[IT]**

## 7. Offline synchronization (REQ-11)

- **Route:** `POST /api/sync` (`auth:sanctum`), `SyncController` → `SyncService::processBatch`.
- **Flow:** validate `actions[]` → per action: idempotency by `client_uuid` (`sync_outbox`) → order lookup → state-machine gate → **authorization gate** (`authorizeDeliveryAction`, driver-ownership + no `assigned`) → transaction { `OrderService::transition` + outbox row } → `{ack, conflicts[]}`.
- **Security:** see [Security §6](../security/SECURITY_OVERVIEW.md); fix in `1490268`.
- **Tests:** `Sync/OfflineSyncTest`, `Sync/SyncAuthorizationTest` (9), `E2E/OfflineSyncJourneyTest`. **[IT]**
