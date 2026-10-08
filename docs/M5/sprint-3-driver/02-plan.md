# 02 — Plan (Phase 3A)

## Goal
Driver sees incoming requests (assigned), active (out_for_delivery), history (delivered), plus earnings/completed/rating computed live. No new assignment system, no profile columns, no payments.amount.

## New file
- `app/Services/DriverStatsService.php`: `stats(User $driver): array{completed, earnings, rating, active}` + `perOrderEarnings(): float`. Queries deliveries + ratings join. Returns rating null when none.

## Modified
- `Driver/DashboardController@index`: add stats via service.
- `Driver/DeliveryController@index`: split assigned vs out_for_delivery; add `history()` (delivered, paginated 20).
- `routes/web.php`: add `GET /driver/deliveries/history` before show route.
- Views: dashboard (stats cards), deliveries (two sections + earnings estimate), new `history.blade.php`, delivery view (t()` strings).
- Lang: `resources/lang/en/driver.php`, `resources/lang/ar/driver.php`.
- No migration. No policy change (history reuses manage? list scoped to own id so fine).

## Business rules kept
- Earnings fixed config value per delivered order.
- role:driver + approved middleware unchanged.
- State machine untouched; reject still deletes row -> ready_for_pickup.

## Tests
- `tests/Feature/Driver/DriverStatsTest.php`: count/earnings math, rating avg ignoring nulls, no-rating null, history auth scoping, dashboard shows stats.

## Risks
- Route order: history must precede {delivery} show route.
- Rating join must go via deliveries.order_id, not ratings.driver_id (does not exist).
