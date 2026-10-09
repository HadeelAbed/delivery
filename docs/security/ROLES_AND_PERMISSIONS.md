# Roles & Permissions Matrix

- **Audit date:** 2026-10-09 · **Commit:** `1490268`
- **Roles (verified in `app/Enums/UserRole.php`):** `customer`, `merchant`, `driver`, `admin`. Cast on `User.role`.
- **Account status (`app/Enums/UserStatus.php`):** `pending`, `active`, `rejected`, `deactivated`. Approval gates rely on `status`.
- **Enforcement is always server-side.** A hidden Blade button is *not* treated as enforcement. "Conditional" means the check depends on ownership/status at runtime.

Legend: **M** = middleware (`role:`/`approved`), **P** = policy, **G** = gate, **C** = controller, **S** = service, **R** = route definition.

## 1. Auth: register / login / logout / deactivate

| Action | Customer | Merchant | Driver | Admin | Enforcement point | Route | Test |
|---|---|---|---|---|---|---|---|
| Register (public) | ✅ (customer only) | ✅ (merchant; `status=pending`) | ✅ (driver; `status=pending`) | ❌ cannot self-register | C `RegisterController` + `RegisterRequest` forces role; `admin` not an allowed public role | `register` | `AdminLockdownTest` |
| Login | ✅ | ✅ (only if `active`) | ✅ (only if `active`) | ✅ | C `LoginController` (blocks `pending`/`rejected`/`deactivated`) + `login` rate-limiter | `login` | `LoginRateLimitConfigTest`, `AccountDeactivationTest` |
| Logout | ✅ | ✅ | ✅ | ✅ | C `LoginController::destroy` | `logout` | suite |
| Deactivate + anonymize own account | ✅ | ✅ | ✅ | ✅ | S `DeactivateController` (atomic transaction, revokes tokens, audit) | `account.deactivate` | `AccountDeactivationTest` (13) |
| Change own role | ❌ | ❌ | ❌ | ❌ (even admin via this form) | C `RegisterController`/profile never accept `role`/`status` as user input | — | `AdminLockdownTest` |

## 2. Profile & personal information

| Action | Enforcement | Route | Test |
|---|---|---|---|
| View/edit own profile | C `ProfileController` scoped to `Auth::user()` | `profile.edit` / `profile.update` | suite |
| Edit another user's profile | ❌ | — (no route accepts arbitrary user_id for profile) | — (no route exists) |

## 3. Merchant & driver approval

| Action | Customer | Merchant | Driver | Admin | Enforcement | Route | Test |
|---|---|---|---|---|---|---|---|
| Request approval (register) | — | ✅ (auto pending) | ✅ (auto pending) | — | C `RegisterController` | `register` | `ApprovalGateTest` |
| Approve/reject with reason | ❌ | ❌ | ❌ | ✅ | M `role:admin` + `ApprovalController` writes audit | `admin.merchants.*`, `admin.drivers.*` | `DashboardTest` |
| Access restricted area while `pending`/`rejected` | ❌ | ❌ | ❌ | ✅ (active) | M `approved` (`RequireApproved`) | — | `ApprovalGateTest` |

## 4. Catalog / categories / products

| Action | Customer | Merchant | Driver | Admin | Enforcement | Test |
|---|---|---|---|---|---|---|
| Browse catalog | ✅ | ✅ | ✅ | ✅ | public routes | `CatalogTest` |
| Create/update/delete own categories & products | ❌ | ✅ (own only, P) | ❌ | ❌ | M `role:merchant,approved` + P `ProductPolicy`/`CategoryPolicy` (owner scope) | `CatalogTest` |
| Edit another merchant's product | ❌ | ❌ | ❌ | ❌ | P `ProductPolicy::update` = owner only | `CatalogTest` |

## 5. Cart / checkout / order creation

| Action | Enforcement | Test |
|---|---|---|
| Add to cart, checkout (COD/digital) | M `role:customer,approved`; C `CheckoutController` → `OrderService` | `OrderFlowTest`, `EndToEndFlowTest` |
| Merchant/driver/admin placing customer orders | ❌ (role middleware) | `RbacRouteTest` |

## 6. Viewing orders / history / tracking

| Action | Enforcement | Test |
|---|---|---|
| Customer views own orders | P `OrderPolicy::view` (customer ownership) | `EndToEndFlowTest` |
| Customer views another customer's order (web) | ❌ (P `OrderPolicy::view`) | `OrderFlowTest` |
| Customer views another customer's order (API) | ❌ (`Api\OrderController` scoped by `OrderPolicy::view`) | `SanctumTokenTest` |
| Merchant views own orders | ❌ others (P owner scope) | `OrderHandlingTest` |
| Customer live tracking of own order (`out_for_delivery` only) | P `OrderPolicy` owner + status gate; `TrackingService` privacy (≤10s freshness, no cross-customer location) | `TrackingPrivacyTest`, `TrackingMapPageTest` |


## 7. Accept / reject / prepare / ready (merchant intake)

| Action | Enforcement | Test |
|---|---|---|
| Accept / reject(reason) / ready | M `role:merchant,approved` + P `OrderPolicy::manage` (own merchant's orders) | `OrderHandlingTest` |
| Act on another merchant's order | ❌ (P) | `OrderHandlingTest` |

## 8. Driver availability / assignment / pickup / delivery / failure / history

| Action | Enforcement | Test |
|---|---|---|
| Toggle online/offline | M `role:driver,approved` | `DeliveryFlowTest` |
| Receive assignment | S `AssignOrderJob` (nearest online+active; creates `Delivery`) | `AssignmentTest` |
| Pickup / deliver / fail (web) | M `role:driver,approved` + P `DeliveryPolicy::manage` (`driver_id == user`) | `DeliveryFlowTest` |
| Act on a delivery assigned to another driver (web) | ❌ (P `DeliveryPolicy::manage`) | `DeliveryFlowTest` |
| View own earnings/history | M `role:driver` + C `DriverStatsService` scoped to driver | `DriverStatsTest` |

## 9. Offline sync / idempotency / conflict

| Action | Enforcement | Test |
|---|---|---|
| Unauthenticated sync | ❌ 401 (`auth:sanctum`) | `OfflineSyncTest::test_sync_requires_authentication` |
| Assigned driver: permitted delivery transitions | ✅ | `SyncAuthorizationTest::test_assigned_driver_can_sync_permitted_transition` |
| Different driver on same order | ❌ conflict (`Delivery.driver_id != user`) | `SyncAuthorizationTest::test_other_driver_cannot_change_order` |
| Customer/merchant delivery transition via sync | ❌ conflict (role gate) | `SyncAuthorizationTest::test_customer_cannot_*`, `test_merchant_cannot_*` |
| Client sets `assigned` via sync | ❌ conflict (assignment is server-only) | `SyncAuthorizationTest::test_client_cannot_assign_via_sync` |
| Unassigned order via sync | ❌ conflict (no `Delivery`) | `SyncAuthorizationTest::test_unassigned_order_cannot_be_modified` |
| Mixed authorized+unauthorized batch | each action gated independently; unauthorized cannot bypass | `SyncAuthorizationTest::test_mixed_batch_cannot_bypass_authorization` |
| Missing / malformed `order_id` | safe conflict, no mutation | `SyncAuthorizationTest::test_missing_order_id`, `test_malformed_order_id` |

> **Security fix at `1490268`:** `SyncService::authorizeDeliveryAction()` enforces role + ownership **after** the state-machine gate and **before** the transaction. Ordering preserves prior `invalid_transition` behavior. See `security/SECURITY_OVERVIEW.md`.

## 10. Payments / callbacks / status

| Action | Enforcement | Test |
|---|---|---|
| Customer checkout payment | C `PaymentService::resolve` → gateway | `CodTest`, `GatewayCallbackTest` |
| Gateway callback (`POST /api/payments/callback/{gateway}`) | S `PaymentService::handleCallback` — signature/verification before marking paid; idempotent via unique `reference_id` | `PaymentWebhookTest`, `GatewayCallbackTest` |
| Mark payment success without valid server-side verification | ❌ (verification in service) | `PaymentWebhookTest` |

## 11. Ratings / reviews

| Action | Enforcement | Test |
|---|---|---|
| Rate a completed order (1–5) | S `RatingService`: delivered-only, owner-only, one-per-order | E2E + owner-scope tests |
| Rate another user's order / non-delivered | ❌ | `RatingService` tests |

## 12. Favorites

| Action | Enforcement | Test |
|---|---|---|
| Add/remove favorite merchant (own) | M `role:customer` + C scoped to `Auth::user()` (`MerchantFavorite`) | suite |

## 13. Notifications & Web Push subscriptions

| Action | Enforcement | Test |
|---|---|---|
| Receive in-app notifications | S `SendOrderNotifications` (`database` channel) | `NotificationFanoutTest` |
| Subscribe/unsubscribe Web Push | M `auth` + C `PushSubscriptionController` scoped to `Auth::id()`; `throttle:30,1`; validated payload | `WebPushTest` |
| Subscribe/delete another user's subscription | ❌ (owner-scoped endpoints) | `WebPushTest::test_cannot_unsubscribe_others` |

## 14. Dashboards / reports / analytics

| Action | Enforcement | Test |
|---|---|---|
| Role dashboards | M `role:*` per dashboard | `DashboardTest` |
| Admin sales report | M `role:admin` | `ReportingTest` |
| Analytics C3–C7 | **Deferred** (Sprint 4) — not implemented | — |

## 15. User management / approvals / audit logs / admin

| Action | Enforcement | Test |
|---|---|---|
| List/filter users & orders | M `role:admin` | `DashboardTest` |
| Approve/reject users | M `role:admin` + audit write | `DashboardTest` |
| View audit logs | S audit written by services; admin oversight surfaces | `AccountDeactivationTest`, approval tests |

## 16. API access / Sanctum tokens / scopes

| Action | Enforcement | Test |
|---|---|---|
| Create token, call `/api/v1/orders` | `auth:sanctum`; results scoped by `OrderPolicy::view` | `SanctumTokenTest` |
| Use revoked token (after deactivation) | ❌ 401 (tokens revoked on deactivation) | `AccountDeactivationTest` |
| Deactivated user login / token use | ❌ (login blocked + tokens revoked) | `AccountDeactivationTest` |

## Summary of enforcement coverage

- Every restricted area is gated by middleware `role`/`approved` **and/or** a server-side policy/service check. No restriction relies solely on UI.
- IDOR surfaces audited: order view (web+API) — scoped by `OrderPolicy::view`; sync — scoped by ownership gate; profile — scoped to `Auth::user()`; push subscriptions — scoped to `Auth::id()`.
- See `security/SECURITY_OVERVIEW.md` §Special checks for the item-by-item verdicts.
