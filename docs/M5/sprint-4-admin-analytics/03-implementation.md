# 03 — Implementation (M5 Sprint 4: Admin Analytics)

Scopes executed: A (A1–A6 repairs), B (users filter), C1 (dashboard KPIs), C2 (date-range sales). C3–C7 NOT implemented (deferred/open decisions).

## New files
1. **`app/Http/Controllers/Admin/UserController.php`** — `index()`: filters `role`, `status` (REQ-15 "filter users"), `latest()`, paginate(20)+`withQueryString()`. Gate `admin.access`.
2. **`app/Http/Controllers/Admin/DashboardController.php`** — `index(AdminAnalyticsService)` (method injection), Gate `admin.access`.
3. **`app/Services/AdminAnalyticsService.php`** — read-only KPI aggregation, **no persisted fields**:
   - `overview()` → today (date), today_revenue (delivered orders `sum(total)` where `whereDate(created_at, today)` — **same rule as US-70 sales report**), today_delivered (count), total_orders, orders_by_status (grouped COUNT), pending_approvals (merchant+driver status=pending), active_merchants/drivers/customers (role+active counts), currency from config.
4. **`resources/views/admin/dashboard.blade.php`** — standalone admin-style page (matches orders/reports CSS), KPI summary cards + orders-by-status table, ids for test assertions.
5. **`resources/views/admin/users.blade.php`** — role/status filter form (enum-driven selects) + user table + pagination.
6. **`resources/views/admin/partials/sidebar.blade.php`** — shared nav (Dashboard, Orders, Users, Approvals, Reports) using `route()` + `request()->routeIs()` for active states.
7. **`tests/Feature/Admin/AdminAnalyticsTest.php`** — 5 tests (see 06).

## Modified files
1. **`app/Http/Controllers/Admin/OrderController.php` (A1)** — now passes `$filters = $request->only([...])`, `$merchants = User::merchant()`, `$customers = role customer`; `paginate()->withQueryString()` so filters survive paging. Root cause of the old `Undefined variable $merchants` 500.
2. **`resources/views/admin/orders.blade.php`** —
   - A2: removed duplicate hidden+text `status` inputs → single `<select>` built from `OrderStatus::cases()`.
   - A3: inline sidebar → `@include('admin.partials.sidebar')` (old active-state checks compared `request()->path()` to `'/admin/orders'`, which never matches — `path()` has no leading slash).
   - A5: `$` → `{{ config('delivery.currency') }}`.
   - **Latent bug fixed**: status badge passed the `OrderStatus` enum to `str_replace()` (crashed once the `$merchants` 500 was cleared) → `$order->status->value`.
3. **`resources/views/admin/reports.blade.php` (A3, A5, C2)** — sidebar include; filter form action `/admin/reports/sales`, single `date` input → `from`/`to` range inputs; revenue formatted + config currency.
4. **`resources/views/admin/order.blade.php` (A3, A5)** — sidebar include; total currency fix.
5. **`app/Http/Controllers/Admin/ReportController.php` (C2)** — validates `date|from|to` (`after_or_equal`); `from = from ?? date ?? today`, `to = to ?? from` (**`?date=` keeps exact US-70 single-date behavior — backward compatible**); `whereBetween(created_at, [startOfDay, endOfDay])`; passes `from`/`to` to view.
6. **`routes/web.php`** — admin group: `GET /admin` → `admin.dashboard`, `GET /admin/users` → `admin.users` (both under existing `auth + role:admin`).
7. **`resources/views/layouts/app.blade.php` (A6)** — admin header nav: Dashboard/Orders/Users/Approvals/Reports (was Approvals-only).
8. **`tests/Feature/Admin/DashboardTest.php`** — assertion adjustment only (see 06 for rationale); test name + scenario unchanged.

## Deleted
- **`resources/views/admin/eports.blade.php` (A4)** — 0-byte dead typo file.

## Not changed (deliberate)
- `ApprovalController` untouched (works). No migration, no KPI columns, no new business rules, no revenue/commission logic. `OrderService`/payment/driver code untouched.
