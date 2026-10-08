# SPEC-001 — Order Delivery Platform (V1)

| Field | Value |
|-------|-------|
| Source intent | `.opencode/intent/order-delivery.md` |
| Intent status at spec time | `approved` (C-01 resolved — owner approval) |
| Spec status | Approved — baselined for execution (PLAN-001 M1 in progress) |
| Scope | V1 MVP only; deferred items listed in intent §6.2 are out of scope |
| Verifiability rule | Every acceptance criterion below is written to be verifiable by an automated test (PHPUnit feature/unit test unless noted) |

> Trace notation: `(L###)` = line number in `.opencode/intent/order-delivery.md`.

---

## 1. Requirements Traceability

| ID | Requirement | Intent trace |
|----|-------------|--------------|
| REQ-01 | Customer registration/login: email + password, phone stored | L88, L182, US-01 L215–222 |
| REQ-02 | Merchant registration with business info; Admin approval gate before operating | L41–42, L89, L183, US-02 L224–234 |
| REQ-03 | Driver registration with personal/vehicle/verification docs; Admin approval gate before assignments | L57–58, L90, L186, US-02 note L234 |
| REQ-04 | Admin not publicly registrable; created by secure seeder/command; admin actions audit-logged | L91, L140, US-03 L236–245 |
| REQ-05 | Session-based auth + RBAC for Blade web; Sanctum prepared for API | L92–93, L135–141 |
| REQ-06 | Merchant profile & catalog (name, logo, location, hours, coverage; categories/items with price, description, image, availability) with updates | L43–45, L183, US-20 L265–277 |
| REQ-07 | Customer browse → cart → checkout → order placement → order history | L25–32, L184, US-10 L249–261 |
| REQ-08 | Full order lifecycle: Pending → Accepted/Rejected → Preparing → ReadyForPickup → Assigned → PickedUp/OutForDelivery → Delivered/Failed/Cancelled; server-side transition validation; events + notifications + audit per transition | L185, L349–353 |
| REQ-09 | Merchant order intake: notification, review, accept (+prep time) / reject (reason), mark ready for pickup | L46–50, US-20 L271–276 |
| REQ-10 | Driver ops: Online availability, assignment with merchant/customer locations + order summary + earnings estimate, accept, pickup confirm, delivery confirm, dashboard history | L60–69, L186, US-30 L281–291 |
| REQ-11 | Offline: driver queues status actions locally with idempotency key + timestamp; auto-sync on reconnect; no duplicates; conflicts resolved by server timestamp + state-machine validation | L95–102, L111, L190, US-31 L293–301 |
| REQ-12 | Payments V1: COD, Jawwal Pay, PalPay; modular gateway interface; server-side verification; store status + reference ID only; no double-charge | L154–157, L187, L128–131, US-40 L305–315 |
| REQ-13 | Google Maps: pinning, routing, live driver position 5–10s, auto-stop on completion, privacy-scoped sharing (only active delivery customer) | L161–164, L188, US-50 L319–327 |
| REQ-14 | Notifications: in-app + Web Push for status changes; SMS only for OTP/confirmation/failed delivery; email for receipts; role-specific; modular for mobile push | L168–173, L189, US-60 L329–335 |
| REQ-15 | Admin dashboard: approve/reject merchants & drivers with reason; view/filter orders & users; all admin actions logged | L191, US-70 L339–347 |
| REQ-16 | Basic reporting: orders and sales | L192 |
| REQ-17 | Performance: order/status reflected within 1–3s; driver pings every 5–10s; concurrent orders without degradation | L107–112, L145 |
| REQ-18 | Security: hashing, HTTPS, CSRF, validation/sanitization, rate limiting, secure sessions, least-privilege data exposure, data deletion/deactivation, security logs | L118–124, L140–141, L148 |
| REQ-19 | Customer rating of merchant + delivery service after completion | L37, US-10 L260 |
| REQ-20 | API-ready backend: shared services between web controllers and `api/v1` (Sanctum, versioned) | L71, L77, L390 |
| REQ-21 | Single responsive Blade web app with role-based dashboards (customer/merchant/driver/admin), no React in V1 | L75–76, L78 |

Out of scope (intent §6.2, L196–204): mobile apps, advanced analytics/BI, loyalty/coupons/rewards, AI recommendations, advanced inventory, live-chat, subscriptions, batching/optimization, extended customer/merchant offline.

---

## 2. User Stories & Acceptance Criteria (Gherkin)

All ACs are automated-testable. Test names are suggestions mapped 1:1.

### US-01 — Customer registration & login (REQ-01, REQ-05)

```gherkin
Feature: Customer registration
  Scenario: Valid customer registration
    Given I am a guest on "/register"
    When I POST "name", "email" (unique), "password" (min 8), "phone" with role=customer
    Then the response status is 200/302
    And a users row exists with role="customer" and status="active"
    And the password column is NOT the plaintext value (hashed verified by Hash::check)
    And the phone column equals the submitted phone

  Scenario: Duplicate email rejected
    Given a customer exists with email "c@test.com"
    When I POST registration with email "c@test.com"
    Then validation fails with error on "email"

  Scenario: Invalid phone rejected
    When I POST registration with phone "abc"
    Then validation fails with error on "phone"
```
Test: `tests/Feature/Auth/CustomerRegistrationTest.php` (`test_valid_registration`, `test_duplicate_email_rejected`, `test_invalid_phone_rejected`)

### US-02 — Merchant/Driver approval gate (REQ-02, REQ-03, REQ-15)

```gherkin
Feature: Approval gate
  Scenario: Merchant blocked before approval
    Given a merchant account exists with status="pending"
    When the merchant requests the merchant dashboard route
    Then the response body contains "Pending Approval"
    And the merchant cannot see any incoming order

  Scenario: Admin approval unlocks merchant
    Given a merchant account with status="pending"
    When an admin approves the merchant with reason "docs ok"
    Then merchant status becomes "approved"
    And an audit_logs row exists with actor=admin, action="approve_merchant"
    And the merchant receives an approval notification

  Scenario: Rejection records reason
    When an admin rejects the merchant with reason "missing docs"
    Then merchant status becomes="rejected"
    And the stored reason equals "missing docs"

  Scenario: Driver blocked before approval (same pattern)
    Given a driver account with status="pending"
    Then the driver receives no delivery assignment
```
Test: `tests/Feature/Admin/ApprovalGateTest.php`

### US-03 — Admin not publicly registrable (REQ-04)

```gherkin
Feature: Admin registration lock
  Scenario: Public registration cannot set admin role
    When I POST /register including role="admin"
    Then the created user role is NOT "admin" (forced to customer or request rejected)

  Scenario: Admin created via seeder only
    Given no admin exists
    When I run "php artisan db:seed --class=AdminSeeder"
    Then exactly one admin user exists

  Scenario: Admin action audit
    Given a logged-in admin
    When the admin approves a merchant
    Then an audit_logs row exists with actor_id=admin, action="approve_merchant", entity="merchant"
```
Test: `tests/Feature/Admin/AdminLockdownTest.php`

### US-10 — Customer browse → order → track → rate (REQ-07, REQ-08, REQ-19)

```gherkin
Feature: Customer order flow
  Scenario: Place order
    Given I am a logged-in customer with a delivery address
    And an approved merchant exists inside my coverage area with an available product priced 10.00
    When I add 2 units to cart and checkout with payment method "COD"
    Then an orders row exists with status="Pending", total = items 20.00 + delivery fee
    And order_items rows = 2 (qty 2, product price 10.00)
    And the merchant receives an in-app notification within 3 seconds (queued + dispatched check)

  Scenario: Merchant rejected order cannot proceed
    Given an order with status="Pending"
    When the merchant rejects it with a reason
    Then the order status becomes "Cancelled" (or "Rejected" — see C-03)
    And no driver assignment can be created for it

  Scenario: Live tracking after pickup
    Given an order with status="OutForDelivery"
    And a driver_locations row refreshed at t=now
    When the customer requests the tracking endpoint
    Then the response includes driver lat/lng with timestamp age <= 10 seconds

  Scenario: Rate after delivery
    Given an order with status="Delivered"
    When the customer submits rating for merchant=4, delivery=5
    Then a ratings row exists with order_id, merchant_score=4, driver_score=5

  Scenario: Cannot rate before delivery
    Given an order with status="Preparing"
    When the customer submits a rating
    Then the request is rejected (422/403)
```
Test: `tests/Feature/Customer/OrderFlowTest.php`, `RatingTest.php`

### US-20 — Merchant catalog & order handling (REQ-06, REQ-09)

```gherkin
Feature: Merchant catalog
  Scenario: Create product
    Given an approved merchant
    When I create category "Pizza" and product "Margherita" price 12.50 with image
    Then the product belongs to merchant and appears in the public catalog for covered areas

  Scenario: Update availability hides product
    When the merchant sets product available=false
    Then the product is not addable to cart (checkout returns validation error)

Feature: Merchant order handling
  Scenario: Accept with prep time
    Given an order status="Pending"
    When the merchant accepts with prep_time_minutes=20
    Then order status="MerchantAccepted" (→ "Preparing" — see C-03)
    And an audit_logs row exists for the transition

  Scenario: Mark ready notifies assigned driver
    Given an order status="Preparing" with an assigned driver
    When the merchant marks ReadyForPickup
    Then order status="ReadyForPickup"
    And the assigned driver receives a notification

  Scenario: Coverage gating
    Given merchant coverage does not include customer location
    Then the merchant's products are not visible to that customer
```
Test: `tests/Feature/Merchant/CatalogTest.php`, `OrderHandlingTest.php`

### US-30 — Driver assignment to completion (REQ-10)

```gherkin
Feature: Driver delivery flow
  Scenario: Online driver receives assignment
    Given an approved driver with status="online" at a location near the merchant
    And an order status="ReadyForPickup"
    When the assignment job runs
    Then the driver sees merchant location, customer location, order summary, earnings estimate

  Scenario: Pickup transitions to OutForDelivery and activates customer map
    Given an order status="Assigned" with driver
    When the driver confirms pickup
    Then order status="PickedUp" (→ "OutForDelivery" — see C-03)
    And live location sharing for this order becomes active

  Scenario: Delivery completes order and stops tracking
    Given an order status="OutForDelivery"
    When the driver confirms delivery
    Then order status="Delivered"
    And location sharing for the order becomes inactive
    And the customer receives a delivery-confirmation notification

  Scenario: Offline driver cannot receive new assignments
    Given driver status="offline"
    Then no assignment is created for this driver

  Scenario: Reject assignment releases it
    Given a driver assigned an order
    When the driver rejects the assignment
    Then the order returns to unassigned pool and remains "ReadyForPickup"
```
Test: `tests/Feature/Driver/DeliveryFlowTest.php`

### US-31 — Offline queue & sync (REQ-11)

```gherkin
Feature: Offline sync
  Scenario: Queued action syncs without duplicate
    Given the driver viewed order 101 online
    And the client queued action {uuid:"u-1", type:"pickup", order:101} while offline
    When the client POSTs /api/sync with that action after reconnect
    Then the order status transitions once
    And a second identical sync with uuid "u-1" is a no-op (ack, no state change)

  Scenario: Out-of-order offline action rejected by state machine
    Given order 101 status="ReadyForPickup"
    And a queued action type="delivered" for order 101 (skipping pickup)
    When sync runs
    Then the action is returned in conflicts[] and the order status is unchanged

  Scenario: Stale action loses to newer server state
    Given order 101 became "Delivered" at server time T2
    And a queued offline action "picked_up" stamped T1 < T2
    When sync runs
    Then the conflict is reported and server state is retained
```
Test: `tests/Feature/Sync/OfflineSyncTest.php` (uses fake connectivity: direct endpoint calls)

### US-40 — Payments (REQ-12)

```gherkin
Feature: COD
  Scenario: COD order
    Given checkout with payment method "COD"
    When the order is confirmed
    Then payments row exists with method="COD", status="pending_cod"
    And no gateway call is made

Feature: Jawwal Pay / PalPay
  Scenario: Gateway callback verified and stored safely
    Given an order with a pending Jawwal transaction
    When a valid signed callback arrives with reference "JW-123"
    Then payments.status="paid" and reference_id="JW-123"
    And no PAN/cvv/credential column exists in DB (schema assertion)

  Scenario: Duplicate callback idempotent
    When the same callback (ref "JW-123") arrives twice
    Then exactly one paid record exists and balance charged once

  Scenario: Invalid signature rejected
    When a callback with bad signature arrives
    Then response is 401/403 and payment status unchanged
```
Test: `tests/Feature/Payment/CodTest.php`, `GatewayCallbackTest.php` (gateway HTTP faked)

### US-50 — Live tracking & privacy (REQ-13)

```gherkin
Feature: Tracking privacy
  Scenario: Active delivery visible
    Given order status="OutForDelivery"
    When the order's customer polls tracking
    Then driver position is returned with age <= 10s

  Scenario: Completed order location not accessible
    Given order status="Delivered"
    When the order's customer polls tracking
    Then no driver location payload is returned

  Scenario: Third party denied
    Given a different customer requests tracking for an active order
    Then the response is 403/404
```
Test: `tests/Feature/Tracking/TrackingPrivacyTest.php`

### US-60 — Notifications (REQ-14)

```gherkin
Feature: Role-specific notifications
  Scenario: Status change fans out correctly
    Given order transitions to "Delivered"
    Then customer gets in-app + web push + email receipt
    And merchant gets in-app + web push
    And driver gets in-app
    And SMS is sent ONLY for the allowlist events (OTP, order confirmation, failed delivery)

  Scenario: No cross-role leakage
    Given a status event on order X
    Then users with roles not subscribed to event X receive no notification row
```
Test: `tests/Feature/Notification/NotificationFanoutTest.php` (fake SMS/push drivers)

### US-70 — Admin moderation & reporting (REQ-15, REQ-16)

```gherkin
Feature: Admin dashboard
  Scenario: Approve/reject with reason
    Given admin logged in
    When admin approves/rejects pending merchant with reason
    Then state changes and audit row written (covered in US-02)

  Scenario: Order list filter
    Given orders exist in statuses Pending, Delivered, Cancelled
    When admin GETs /admin/orders?status=Delivered
    Then only Delivered orders are returned

  Scenario: Sales report
    Given 3 delivered orders with totals 10, 20, 30
    When admin requests the sales report for today
    Then report totals = 60 and order count = 3
```
Test: `tests/Feature/Admin/DashboardTest.php`, `ReportingTest.php`

### Non-functional ACs (REQ-17, REQ-18, REQ-20, REQ-21)

```gherkin
Feature: NFR spot-checks
  Scenario: Password hashing enforced
    Given a user is created through registration
    Then Auth::user password verifies via Hash::check and fails for wrong password

  Scenario: Rate limiting on login
    When I POST /login with wrong credentials 6 times in one minute
    Then attempt 6 returns 429

  Scenario: RBAC route isolation
    Given a customer session
    When the customer requests admin routes
    Then response is 403

  Scenario: Sanctum API token auth
    Given a valid Sanctum token for a customer
    When the token calls GET /api/v1/orders
    Then only that customer's orders are returned
    And without a token the endpoint returns 401

  Scenario: Shared service layer
    Then OrderService::place is invoked by both web checkout and POST /api/v1/orders (mock/spy assertion)
```
Test: `tests/Feature/Security/` + `tests/Unit/Services/`

**Performance (REQ-17):** 1–3s propagation and 5–10s ping cadence are runtime/infrastructure characteristics, not unit-testable — verify via seed load test (k6/artillery script) in CI-nightly; automated but outside PHPUnit. All other ACs are PHPUnit-automatable.

---

## 3. Conflicts & Resolutions (all resolved by owner decision)

| ID | Conflict / Gap | Resolution (owner-approved) |
|----|----------------|------------------------------|
| C-01 | Analyst requires an **approved** intent as input; intent was `Status: draft` | Intent approved by owner; decisions C-02…C-08 folded into intent |
| C-02 | OTP SMS is V1 "critical" notification, but phone is stored "for future verification" | OTP **deferred** to post-V1; phone stored for contact purposes only |
| C-03 | State names ambiguous (`PickedUp/OutForDelivery`, `MerchantAccepted/Rejected`); 3s vs "1–3s" boundary | Single states: `merchant_accepted → preparing → ready_for_pickup → assigned → out_for_delivery → delivered / failed / cancelled`; rejection → `cancelled`; target "within 3 seconds" |
| C-04 | Delivery fee model & driver earnings formula unspecified | Fixed delivery fee + fixed driver earnings per order, both in `config/delivery.php` |
| C-05 | Driver assignment algorithm unspecified | **Nearest available driver first** |
| C-06 | Ratings schema/scale unspecified | 1–5 integers, separate `merchant_score`/`driver_score`, **not anonymous** |
| C-07 | Jawwal Pay/PalPay sandbox credentials + callback signature format unknown | **Mock Payment Drivers** behind a replaceable `PaymentGateway` interface; real gateways plug in later without core changes |
| C-08 | Admin approval SLA + required verification documents unspecified | No fixed SLA in V1; manual Admin approval; previously defined document requirements apply |

---

## 4. Exit Criteria

1. All §2 tests green under `php artisan test`.
2. Every REQ traced to ≥1 test.
3. Conflicts C-01…C-08 answered in intent revision (intent moves to `approved`).
4. Load-test script covers REQ-17 targets.
