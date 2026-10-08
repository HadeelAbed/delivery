# 05 — Routes & Request Flow (M5 Sprint 5)

## Routes/API changes: **NONE** (tests exercise the existing surface).

Existing routes driven end-to-end by `EndToEndFlowTest` (verified via `route()` helpers):

| # | Route | Hop |
|---|---|---|
| 1 | POST `customer.cart.add` | customer adds item (session cart) |
| 2 | POST `customer.checkout.place` | creates order + items + payment `pending_cod`, fires NewOrder → merchant |
| 3 | POST `merchant.orders.accept` | pending→merchant_accepted, OrderAccepted → customer |
| 4 | POST `merchant.orders.ready` | merchant_accepted→ready_for_pickup, then `AssignOrderJob::dispatch` runs **inline** (`phpunit.xml` QUEUE_CONNECTION=sync) → assigned + `deliveries` row |
| 5 | POST `driver.deliveries.pickup` | assigned→out_for_delivery, OutForDelivery → customer |
| 6 | POST `driver.deliveries.deliver` | out_for_delivery→delivered, OrderDelivered → customer+merchant |
| 7 | POST `customer.orders.rate.submit` | repaired: 302 + `ratings` row (was 500) |
| 8 | GET `customer.orders.rate` | repaired: 200 owner / 403 stranger (was 403 for everyone) |
| 9 | GET `admin.reports.sales` | US-70 rule counts the delivered order; excludes cancelled |
| 10 | POST `merchant.orders.reject` | pending→cancelled + OrderCancelled → customer+merchant |

## Views
- `customer/rating-create.blade.php` — one line fixed (enum → `->value`); otherwise untouched.
- No new/modified views; no layout changes.

## Authorization (as-is, verified by tests)
- `auth + role:driver + approved` on driver hop; `role:merchant + approved` on merchant hop; `role:admin` on report; customer session on cart/checkout/rating.
- New `OrderPolicy::rate` now backs `Gate::authorize('rate', $order)` — owner-only, matching the `view` policy pattern.
- DeliveryPolicy::manage owner-only re-verified implicitly (E2E uses the assigned driver).

## Queue/event behavior exercised
- `OrderStatusChanged` event → `SendOrderNotifications` fanout asserted for: pending (merchant), merchant_accepted (customer), out_for_delivery (customer), delivered (customer+merchant), cancelled (customer+merchant).
- Documented nuance: `ready_for_pickup` event fires inside `OrderService::transition` **before** `AssignOrderJob` creates the delivery row, so its driver notification branch (`$order->delivery?->driver_id`) is null at that moment — driver receives no ready_for_pickup notification in the current architecture. Not changed; noted as observation.
