# 05 — Routes & Views (M5 Sprint 4)

## Routes (all in `Route::middleware(['auth','role:admin'])->prefix('admin')->name('admin.')`)

| Method | URI | Name | Controller | Change |
|---|---|---|---|---|
| GET | /admin | admin.dashboard | Admin\DashboardController@index | **NEW (C1)** |
| GET | /admin/users | admin.users | Admin\UserController@index | **NEW (B)** |
| GET | /admin/approvals | admin.approvals | ApprovalController@index | unchanged |
| POST | /admin/users/{user}/approve | admin.users.approve | ApprovalController@approve | unchanged |
| POST | /admin/users/{user}/reject | admin.users.reject | ApprovalController@reject | unchanged |
| GET | /admin/orders | admin.orders | Admin\OrderController@index | unchanged route; controller now passes filters/dropdown data |
| GET | /admin/orders/{order} | admin.orders.show | Admin\OrderController@show | unchanged |
| GET | /admin/reports/sales | admin.reports.sales | Admin\ReportController@sales | unchanged route; now accepts `from`/`to` (and legacy `date`) |

Verified via `php artisan route:list --name=admin` → 8 routes.

## Request flow
1. Admin logs in → header nav (A6) → `GET /admin` → `AdminAnalyticsService::overview()` → KPI cards + status table.
2. Orders: `GET /admin/orders?status=&merchant_id=&customer_id=` → filtered/paginated list; filter selects pre-reflect `$filters` and survive pagination (`withQueryString`).
3. Users: `GET /admin/users?role=&status=` → filtered/paginated list (REQ-15).
4. Reports: `GET /admin/reports/sales?from=&to=` → delivered orders `whereBetween(created_at)` → sum(total) + count + rows; `?date=Y-m-d` behaves exactly as the original US-70 single-day report.

## Views
| View | Change |
|---|---|
| `admin/dashboard.blade.php` | **NEW** — KPI cards (today revenue/delivered, total orders, pending approvals, active merchants/drivers/customers) + orders-by-status table; same standalone CSS family as orders/reports |
| `admin/users.blade.php` | **NEW** — role/status filters + table + pagination |
| `admin/partials/sidebar.blade.php` | **NEW shared partial** — Dashboard/Orders/Users/Approvals/Reports with route-based active states; included by dashboard, orders, order detail, reports, users |
| `admin/orders.blade.php` | modified — enum status select (A2), sidebar include (A3), currency (A5), enum→string badge fix |
| `admin/reports.blade.php` | modified — sidebar include, from/to inputs (A5/C2), currency |
| `admin/order.blade.php` | modified — sidebar include, currency |
| `admin/approvals.blade.php` | unchanged |
| `layouts/app.blade.php` | modified — admin nav links (A6) |
| `admin/eports.blade.php` | **DELETED** (A4, empty typo artifact) |

## Authorization & validation
- Both new routes inherit `auth + role:admin`; controllers additionally call `Gate::authorize('admin.access')` (defense in depth, matching existing admin controllers).
- `ReportController` validates `date`, `from`, `to` as dates + `to >= from`; invalid input → redirect with errors (no 500).
- Users/orders filters only accept values into `where()` clauses — no raw SQL.
- Non-admin access verified 403 by `AdminAnalyticsTest::test_non_admin_cannot_access_dashboard_or_users`.
