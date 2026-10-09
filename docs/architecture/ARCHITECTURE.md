# Architecture — Gaza Delivery Marketplace

- **Audit date:** 2026-10-09 · **Commit:** `1490268`
- **Framework:** Laravel 11 (PHP), Blade server-rendered UI, SQLite in dev/test, Laravel Sanctum for the REST surface.
- **Distinction:** this document records **implemented** behavior at the documented commit. Anything labelled *future* is a plan, not shipped code.

## 1. Application layers

```
HTTP request
  ├─ routes/web.php  (session auth, Blade UI)  ─────────┐
  ├─ routes/api.php  (Sanctum bearer, JSON REST)  ──────┤
  │                                                       ▼
  │                                            Http\Controller\*\  (thin: validate, authorize, delegate)
  │                                                       │
  │   ── cross-cutting ────────────────────────────────────┤
  │      middleware: role → approved (web); auth:sanctum (api)
  │      policies: Order/Delivery/Product/CategoryPolicy
  ▼                                                       ▼
Service layer (app/Services)  ← single source of business logic
  OrderService, PaymentService, SyncService, TrackingService,
  RatingService, DriverStatsService, AdminAnalyticsService,
  Maps\GoogleMapsProvider, Push\WebPushService
      │                        │                    │
      ▼                        ▼                    ▼
  Eloquent models         Events / Jobs        Contracts (interfaces)
  OrderStatusChanged  →   AssignOrderJob     PaymentGateway, MapProvider
  SendOrderNotifications
```

**Design rule (verified):** web controllers and API controllers both delegate to the **same service methods** (REQ-20 shared service layer). Business rules live in services/policies, never in Blade or controllers.

## 2. Models (`app/Models`)

`User`, `Merchant`, `Category`, `Product`, `Order`, `OrderItem`, `Delivery`, `Payment`, `DriverLocation`, `DriverProfile`, `Rating`, `MerchantFavorite`, `PushSubscription`, `SyncOutbox`, `AuditLog`.

Key relationships:
- `User` → `merchantProfile` (Merchant), `driverProfile` (DriverProfile), `merchantOrders`, `customerOrders`, `deliveries`, `merchantFavorites`, Sanctum `tokens`.
- `Order` → `customer_id` (User), `merchant_id` (Merchant), `items` (OrderItem→Product), `delivery` (Delivery, hasOne), `payment` (Payment).
- `Merchant` → `user_id` (User), `categories` → `products`.
- `Delivery` → `order_id` (Order), `driver_id` (User).
- Enums cast on `User`: `role` → `UserRole` (`customer|merchant|driver|admin`), `status` → `UserStatus` (`pending|active|rejected|deactivated`).

## 3. Services (business logic)

| Service | Responsibility |
|---|---|
| `OrderService::transition(Order, string)` | Central state machine; fires `OrderStatusChanged`, writes audit, wraps changes in a transaction. Used by **both** web controllers and `SyncService`. |
| `SyncService::processBatch(User, actions)` | Offline batch processing: idempotency (`client_uuid`), state-machine gate, **driver-ownership authorization** (see §7), per-action transaction, `mirrorDeliveryState`. |
| `Payment\PaymentService` | `resolve(method)` → gateway, `process(order)` → charge, `handleCallback(method, data)` → verify + update. |
| `Maps\GoogleMapsProvider` | Server-side Directions/ETA via HTTP client; result cache; graceful `null` on any failure/missing key. |
| `Push\WebPushService` | VAPID push via `minishlink/web-push`; no-op when unconfigured; prunes expired subscriptions. |
| `TrackingService` | Driver location ingest (`recordLocation`), `latestForOrder`, `isLocationFresh` (≤10s). |
| `RatingService` | Post-delivery rating rules (delivered-only, owner-only, one-per-order). |
| `DriverStatsService` | Driver earnings/history aggregation. |
| `AdminAnalyticsService` | Admin dashboards/reports. |


## 4. Controllers (`app/Http/Controllers`)

| Area | Controllers |
|---|---|
| Auth | `Auth\RegisterController`, `Auth\LoginController`, `Auth\DeactivateController` (web session auth) |
| Customer | `Customer\{CartController, CheckoutController, OrderController, TrackingController, MerchantFavoriteController, ProfileController}` |
| Merchant | `Merchant\{ProfileController, CategoryController, ProductController, OrderController}` |
| Driver | `Driver\{LocationController, DeliveryController, ProfileController, DashboardController}` |
| Admin | `Admin\{DashboardController, MerchantApprovalController, DriverApprovalController, OrderController, ReportController}` |
| API (`Api\`) | `Api\{OrderController, SyncController, PaymentCallbackController}` |

Controllers are intentionally thin: validate input, check authorization (policy/gate/middleware), delegate to a service, return a view or JSON response. No business rules in controllers or Blade.

## 5. Events / Listeners (`app/Events`, `app/Listeners`)

- `OrderStatusChanged` — dispatched by `OrderService::transition` on **every** status change (web and sync paths).
- `SendOrderNotifications` — listener that fans out the role-appropriate notification (in-app `database` channel always; `WebPushChannel` additively) to the customer/merchant/driver per transition.

## 6. Jobs (`app/Jobs`)

- `AssignOrderJob` — on `ready_for_pickup`, selects the nearest **online + active** driver, creates the `Delivery` row (`status=assigned`), sets the order to `assigned`. Runs inline in dev/test (`QUEUE_CONNECTION=sync`). **This is the only assignment path — it never goes through `/api/sync`.**

## 7. Policies, Middleware, Gates

- **Policies** (`app/Policies`): `OrderPolicy` (`view`/`manage` scoped to customer/merchant ownership), `DeliveryPolicy` (`manage` = `$user->id === $delivery->driver_id`), `ProductPolicy`, `CategoryPolicy`.
- **Middleware** (`app/Http/Middleware`, registered in `bootstrap/app.php`): `role:customer|merchant|driver|admin` (`RoleMiddleware`), `approved` (`RequireApproved` — blocks unapproved merchants/drivers from restricted areas), plus framework `auth` / `auth:sanctum`.
- **Rate limiting:** `login` limiter defined in `AppServiceProvider`; route-level `throttle` applied to login and `/push/subscribe|unsubscribe`.

## 8. Database (`database/migrations`)

Tables: `users`, `merchants`, `driver_profiles`, `categories`, `products`, `orders`, `order_items`, `deliveries`, `payments`, `driver_locations`, `ratings`, `merchant_favorites`, `push_subscriptions`, `sync_outbox` (uuid PK), `audit_logs`, plus framework tables (`personal_access_tokens`, `cache`, `jobs`, `password_reset_tokens`, `notifications`, `sessions`). Dev/test use SQLite; `RefreshDatabase` in tests.

## 9. Routes

- **Web** (`routes/web.php`, session auth + `role`/`approved` middleware): auth routes, per-role dashboards, catalog/cart/checkout, order views, tracking page, merchant CRUD, driver availability/delivery actions, admin approvals/filters/reports, push subscribe/unsubscribe.
- **API** (`routes/api.php`, `auth:sanctum`):
  - `GET /api/v1/orders`, `GET /api/v1/orders/{order}` — scoped by `OrderPolicy::view`.
  - `POST /api/sync` — offline batch sync (ownership-authorized, see §3).
  - `POST /api/payments/callback/{gateway}` — payment webhook (signature-verified in `PaymentService::handleCallback`).

## 10. Request flow (example: a driver marks an order delivered, offline then reconnected)

```
Driver app queues {client_uuid, type: delivered, payload:{order_id}} while offline
  → reconnect → POST /api/sync (Bearer) → Api\SyncController@sync (validate)
  → SyncService::processBatch:
       idempotency (client_uuid) → order lookup → OrderStatus::canTransitionTo gate
       → authorizeDeliveryAction (driver role + Delivery.driver_id == user->id)   ← security gate
       → DB::transaction { OrderService::transition → OrderStatusChanged
                            → SendOrderNotifications (db + push)
                            → audit log ; mirrorDeliveryState ; sync_outbox row }
  → {ack:[{status:processed}], conflicts:[]}
```

## 11. Implemented vs. future

| Implemented at `1490268` | Future / not built |
|---|---|
| Web + Sanctum API, RBAC, approval gates | Native mobile apps |
| Order state machine + events/notifications | Analytics C3–C7 |
| COD + mock Jawwal/PalPay (server-verified, replay-safe) | Live gateway credentials |
| Offline sync with driver-ownership authorization | Web Push live delivery (needs VAPID) |
| In-app notifications + Web Push channel (degraded-by-default) | Google Maps live render/route/ETA (needs keys) |
| Google Maps tracking UI (degraded-by-default) | k6 execution (binary not installed) |
| REQ-18 account deactivation + personal-data anonymization | Loyalty, AI, inventory, chat, subscriptions |

