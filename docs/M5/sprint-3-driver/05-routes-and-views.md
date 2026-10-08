# 05 — Routes & Views

## Routes (all inside `Route::middleware(['auth','role:driver','approved'])->prefix('driver')->name('driver.')`)

| Method | URI | Name | Controller | Change |
|---|---|---|---|---|
| GET | /driver/dashboard | driver.dashboard | DashboardController@index | unchanged route, controller now passes stats |
| POST | /driver/toggle-online | driver.toggle-online | AvailabilityController@toggle | unchanged |
| POST | /driver/location | driver.location.store | LocationController@store | unchanged |
| GET | /driver/deliveries/history | driver.deliveries.history | DeliveryController@history | **NEW** (placed before `{delivery}` route so literal wins) |
| GET | /driver/deliveries | driver.deliveries | DeliveryController@index | unchanged route, response split incoming/active |
| GET | /driver/deliveries/{delivery} | driver.deliveries.show | DeliveryController@show | unchanged (`DeliveryPolicy::manage`) |
| POST | .../pickup | driver.deliveries.pickup | DeliveryController@pickup | unchanged |
| POST | .../deliver | driver.deliveries.deliver | DeliveryController@deliver | unchanged |
| POST | .../reject | driver.deliveries.reject | DeliveryController@reject | unchanged |

## Request flow (driver session)
1. Login → `driver.dashboard` → stats line (completed/earnings/rating/active) from `DriverStatsService::stats()`.
2. Merchant marks ready → `AssignOrderJob` assigns nearest online driver → `deliveries` row (assigned) + order `assigned`.
3. Driver opens `driver.deliveries` → **Incoming requests** table (earnings estimate = config value) vs **Active deliveries** table.
4. `driver.deliveries.show` → Confirm Pickup (`assigned → out_for_delivery`) / Reject (deletes row, order back to `ready_for_pickup`) / Confirm Delivery (`out_for_delivery → delivered`).
5. `driver.deliveries.history` lists delivered rows (paginated 20).

## Views
| View | Change |
|---|---|
| `resources/views/driver/dashboard.blade.php` | **modified** — stats summary + nav links, i18n via `__('driver.*')`; also fixed a pre-existing bug (view read `$deliveries` which controller never passed) |
| `resources/views/driver/deliveries.blade.php` | **modified** — incoming/active sections + earnings-estimate column + history link, i18n |
| `resources/views/driver/history.blade.php` | **NEW** — paginated delivered history, own-scope |
| `resources/views/driver/delivery.blade.php` | unchanged (already shows `config('delivery.driver_earnings')` + pickup/deliver/reject buttons) |
| `resources/views/layouts/app.blade.php`, `layouts/driver.blade.php` | unchanged — responsive inline CSS (`max-width:960px`, flex-wrap header) reused as-is for mobile-friendliness |

## i18n
New namespace `driver` in `resources/lang/en/driver.php` and `resources/lang/ar/driver.php`. Existing `customer` namespace untouched. (Locale-switch mechanism itself is out of sprint scope — see 07 deferred.)
