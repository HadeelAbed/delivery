# 02 — Plan (M5 Sprint 5: End-to-End Flow Tests)

## Goal
Close PLAN-001's last executable M4 task: automated end-to-end tests driving the full multi-role journey through real HTTP routes. Repair the two audit-discovered defects that block the spec'd rating hop (REQ-19). No new features, no migrations, no route/API changes.

## Scope A — Rating hop repair (restores existing SPEC-001 REQ-19; discovered in audit)
| # | Change | File | Rule source | DB |
|---|---|---|---|---|
| R1 | Add `rate(User $user, Order $order): bool` → `$user->id === $order->customer_id` (mirrors existing `view()` style) | `app/Policies/OrderPolicy.php` | REQ-19 + existing policy pattern; no new rule — ownership already used by `view` | none |
| R2 | Fix `$validated->all()` → use `$validated` array directly (validate() already returns the array) | `app/Http/Controllers/Customer/RatingController.php` | bug fix only | none |
| R3 | Verify/remove phantom `->whereNull('deleted_at')` on ratings (no such column) — remove if it errors after R2 | same controller | ratings table has unique(order_id); rule is redundant/harmful | none |
| R4 | Delete temporary probe `tests/Feature/E2E/RatingProbeTest.php` | test dir | hygiene | none |

Not touched: `RatingService`, rating routes, views, rating migration.

## Scope B — E2E tests (the plan item)
**NEW `tests/Feature/E2E/EndToEndFlowTest.php`** — journeys use only real routes:

1. **`test_full_cod_journey_from_cart_to_admin_report`**
   seed merchant+profile+category+product+driver(online, location) →
   customer POST `customer.cart.add` → POST `customer.checkout.place` (cod) →
   merchant POST `merchant.orders.accept` (prep time) → POST `merchant.orders.ready` (fires `AssignOrderJob` inline via sync queue) →
   driver POST `driver.deliveries.pickup` → POST `driver.deliveries.deliver` →
   customer POST `customer.orders.rate.submit` (merchant 5 / driver 4) →
   admin GET `admin.reports.sales` (today) shows the order total →
   asserts: order status path, `order_items` totals, payment `pending_cod`, delivery row delivered + timestamps, ratings row exists, `DriverStatsService` earnings = config value and rating = 4.0, notifications rows exist for merchant/customer at the right hops, admin report 200 + total.

2. **`test_cancellation_journey_ends_cleanly`**
   cart → checkout → merchant POST reject (reason) → asserts cancelled status, `reject_reason`, `assertDatabaseMissing deliveries`, customer+merchant OrderCancelled notifications, admin report (today) does NOT count it, driver stats unaffected (0 earnings).

3. **`test_rating_hop_is_reachable_and_owner_scoped`** (guards R1–R3)
   delivered order → owner GET rate form 200 → owner POST rate creates row → second POST rejected (unique/already_rated) → another customer GET rate form 403.

Reuse: existing factories (`User::factory()->merchant()/driver()/admin()`), `RefreshDatabase`, route() helpers, config('delivery.driver_earnings'). No service/controller changes for tests.

## Not in scope (documented as deferred)
- Payment webhook route (`/api/payments/callback/*`) — PLAN-001 "for production" + C-07 mock gateways.
- The 5 known sqlite-isolation test failures (3 OfflineSyncTest + 2 SanctumTokenTest).
- Offline-sync E2E (covered by OfflineSyncTest at slice level; those tests are the known-broken ones).
- C3–C7 analytics; any rating feature beyond repair (e.g., rating display pages).

## Test acceptance
- New suite green; existing suite unchanged: 70 passed / 5 failed (the same pre-existing 5) plus new tests.
- `pint --test` clean on touched files.
- Backward compatibility: no production behavior change except the rating endpoints going from 403/500 → working (a fix, not a break).

## Execution order
1. ✅ 01-analysis.md (done)
2. 02-plan.md (this file)
3. Scope A repairs (R1–R3), delete probe (R4)
4. Scope B test file
5. Run suite → 03–07 docs
