# 03 — Implementation

## New files
1. **`app/Services/DriverStatsService.php`** — service-layer stats, no persisted duplicates:
   - `perOrderEarnings()`: `config('delivery.driver_earnings', 10.00)` (Decision C-04 rule).
   - `stats(User $driver)`: returns `completed` (deliveries status=delivered), `active` (assigned+out_for_delivery), `earnings = completed × perOrderEarnings`, `rating = AVG(ratings.driver_score)` joined via `deliveries.order_id` (null when no ratings), `per_order`, `currency`.
   - Why: user mandated computed-not-stored (check 2) — single source of truth stays in `deliveries` + `ratings`.

2. **`resources/lang/en/driver.php` + `resources/lang/ar/driver.php`** — new driver namespace for AR/EN i18n (project only had `customer.php`).

3. **`resources/views/driver/history.blade.php`** — delivered history, own-driver scoped, paginated (20), `data-order` attr used by tests.

4. **`tests/Feature/Driver/DriverStatsTest.php`** — 3 tests (see 06-tests).

## Modified files
1. **`app/Http/Controllers/Driver/DashboardController.php`**
   - Before: only counted active deliveries.
   - After: injects `DriverStatsService` (method injection), passes `driverStats` + `isOnline`.
   - Why: dashboard must show earnings/completed/rating per REQ-10.

2. **`app/Http/Controllers/Driver/DeliveryController.php`**
   - `index()`: now splits one list into `incoming` (status=assigned) and `activeDeliveries` (out_for_delivery); passes `perOrderEarnings` + `currency` for the earnings estimate column.
   - Added `history()`: delivered deliveries, `latest()`, `paginate(20)`, scoped to `Auth::user()->deliveries()` (implicitly own-driver only).
   - Why: incoming-vs-active separation + history per REQ-10/dashboard history requirement. Reuses existing Delivery model — no second system (check 3).

3. **`routes/web.php`** — added `GET /driver/deliveries/history` (`driver.deliveries.history`) **before** `/deliveries/{delivery}` so literal route wins.

4. **`resources/views/driver/dashboard.blade.php`** — replaced recent-deliveries table (view previously used `$deliveries` which the controller did not even pass — pre-existing mismatch) with stats line + nav links; all strings via `__('driver.*')`.

5. **`resources/views/driver/deliveries.blade.php`** — two sections (incoming with earnings-estimate column; active), counts, i18n strings, history link.

## Not changed (deliberate)
- No migration, no `driver_profiles` columns (check 2).
- No change to `AssignOrderJob`, `OrderService`, `Payment`/payments table (checks 1 & 3).
- `DeliveryPolicy`, middleware `role:driver + approved`, pickup/deliver/reject logic untouched.

## Authorization & validation
- All new route under existing `auth + role:driver + approved` group.
- `history()` scoped by `Auth::user()->deliveries()` — drivers only ever see their own rows.
- No new request input → no new FormRequest/validation needed.
