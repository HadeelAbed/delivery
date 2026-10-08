# M5 Sprint 3 — Driver (Phase 3A): Analysis

Date: 2026-10-07. Scope: incoming requests, earnings/stats, history.

## 1. What exists
- `users` (role driver, status, is_online) -> `deliveries()`, `driverProfile()`.
- `driver_profiles`: vehicle/service_area only. NO earnings/count/rating columns.
- `orders`: items_total, delivery_fee (fixed 15.00 at placement), total.
- `deliveries`: order_id, driver_id, status (assigned/out_for_delivery/delivered), picked_up_at, delivered_at.
- `payments`: method/status/reference/payload. NO amount column.
- `ratings`: order_id unique, merchant_score, driver_score (1-5).
- `AssignOrderJob`: nearest online driver -> Delivery(assigned) + order ready_for_pickup->assigned.
- `OrderService::transition`: state-machine gate for all transitions.
- Driver routes (role:driver+approved): dashboard, toggle-online, location.store, deliveries, show/pickup/deliver/reject. Policy: owner driver only.
- Views: layouts.app <- layouts.driver <- driver.dashboard/deliveries/delivery. Delivery view already shows config earnings.
- i18n: only customer.php lang files. No driver.php.
- Config `delivery.php`: currency ILS, delivery_fee 15.00, driver_earnings 10.00 (Decision C-04 fixed), nearest_available (C-05).

## 2. Mandatory checks
### Check 1 — earnings
`payments.amount` DOES NOT EXIST (migration 0001_01_01_000010 has no amount). Authoritative rule is fixed `config('delivery.driver_earnings')` = 10.00 ILS (C-04). Not full fee, not percent. total = delivered_count x config value. Do not invent a new rule.

### Check 2 — no duplicated columns
completed count, total earnings, driver rating are all computable: deliveries(status=delivered) count; count x config; AVG(ratings.driver_score via deliveries.order_id). No perf reason to persist. Decision: `DriverStatsService`, no migration.

### Check 3 — no second assignment system
AssignOrderJob + Delivery(assigned) + DeliveryController IS the incoming-request system. Gap is UX only: assigned/out_for_delivery mixed, no history, no stats. Decision: reuse; add grouping + stats + history + i18n only.

## 3. Gaps for Phase 3A
1. No stats service. 2. Dashboard only active count. 3. No delivered history. 4. Incoming vs active not separated. 5. No driver.php lang. 6. No stats tests.

## 4. Key files
Models: User, DriverProfile, Order, Delivery, Payment, Rating, DriverLocation. Services: OrderService, RatingService, TrackingService. Job: AssignOrderJob. Controllers: Driver/Dashboard, Delivery, Availability, Location. Policy DeliveryPolicy. Routes web.php driver group. Views driver.*. Config delivery.php. Tests DeliveryFlowTest, AssignmentTest.
