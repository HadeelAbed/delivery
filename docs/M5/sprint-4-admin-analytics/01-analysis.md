# M5 Sprint 4 — Admin Analytics: Analysis (audit vs SPEC-001 / PLAN-001)

Date: 2026-10-07. Documentation-only audit — no code changed.

## 1. SPEC-001 requirements related to admin/reporting
- **REQ-15**: Admin dashboard: approve/reject merchants & drivers with reason; view/filter orders & users; all admin actions logged.
- **REQ-16**: Basic reporting: orders and sales.
- **US-70** scenarios: (a) approval/rejection writes audit row; (b) `GET /admin/orders?status=Delivered` returns only those; (c) sales report for today totals 60 / count 3 (sum of `orders.total` of delivered orders created that day).
- **REQ-04**: admin actions audit-logged. **REQ-21**: single responsive Blade app, role dashboards.
- **Intent §6.2**: "Advanced analytics and BI" is explicitly **deferred out of V1** (one of 9 deferred items). Boundary for M5 scope.

## 2. PLAN-001 M4 requirements
- M4 = Payments + Offline sync + **Admin dashboard/Reporting**.
- Delivered: admin views ×3 (orders, reports, order detail), routes `/admin/orders`, `/admin/reports`, `/admin/orders/{id}`.
- Explicitly unchecked remaining M4 tasks: **"Admin dashboard UI refinements"**, **"End-to-end flow tests"**, "Payment webhook setup".
- Tests named: `Admin/DashboardTest`, `Admin/ReportingTest` (both exist).

## 3. Current implementation inventory (already exists)
Controllers (all `Gate::authorize('admin.access')`, gate = role Admin in AppServiceProvider):
- `Admin/ApprovalController`: index (pending merchants/drivers), approve, reject (reason → profile.rejection_reason, AuditLog, notify).
- `Admin/OrderController`: index with filters `status`, `merchant_id`, `customer_id` → paginate(20); show detail.
- `Admin/ReportController@sales`: `date` param (default today), `orders = delivered WHERE whereDate(created_at, date)`, `total = sum('total')`, `count`.

Routes: `admin.approvals`, `admin.users.approve/reject`, `admin.orders`, `admin.orders.show`, `admin.reports.sales` — under `auth + role:admin`.

Views: `admin/approvals.blade.php` (works, extends layouts.admin), `admin/orders.blade.php` (standalone Tailwind CDN + sidebar; filter form for status/merchant/customer), `admin/reports.blade.php` (date picker, summary cards Total Revenue + Orders Delivered, table), `admin/order.blade.php` (detail + timeline), `admin/eports.blade.php` (**0 bytes, dead typo artifact**).

Tests: AdminLockdownTest (3), ApprovalGateTest (3), DashboardTest (2), ReportingTest (1), RbacRouteTest admin isolation (1).

## 4. Already satisfied vs missing
**Satisfied (verified):**
- Approve/reject with reason + audit (ApprovalGateTest passes).
- Sales report US-70(c): ReportingTest passes (60/2 scenario via `total` sum).
- Order filters exist in controller; RBAC isolation works.
- Admin audit logging on approvals/rejections.

**Broken/incomplete (pre-existing M4 bugs):**
1. `orders.blade.php` references `$merchants`, `$customers`, `$filters` — controller passes only `$orders` → **500** → `DashboardTest::admin_can_filter_orders_by_status` FAILS (one of the 6 known failures).
2. Duplicate `status` inputs (hidden + text) in the same filter form — last wins, confusing.
3. Sidebar links to `/admin/reports` but real route is `/admin/reports/sales` → **404**. Sidebar also lacks Approvals link; `layouts.admin`/`layouts.app` header has no admin nav beyond role check.
4. `admin/eports.blade.php` empty leftover.
5. Views hardcode `$` while `config('delivery.currency')` = ILS.
6. No admin **users** list/filter → REQ-15 "filter **users**" unmet (orders filter broken anyway).
7. No admin dashboard home (route list has no `admin.*` index; no KPI overview).

**Missing vs SPEC:** user filtering (REQ-15 partial). Everything else in REQ-15/16 is present modulo the bugs above.

## 5. Existing code to reuse
- `Order` (status enum, totals, timestamps), `User::merchant()/driver()/pending()` scopes, `Rating`, `Delivery`, `Payment`, `AuditLog::record`.
- `ReportController@sales` query pattern; `Admin\OrderController` filter pattern; sidebar/summary-card CSS already in admin views; `Gate::admin.access`; admin test factory pattern (`User::factory()->admin()`).
- `DriverStatsService` (Sprint 3) as pattern for a read-only stats service.

## 6. Duplication to avoid
- Do NOT create parallel report controller/route for basic sales — extend `ReportController`.
- Do NOT re-implement approvals/users filtering already partially present.
- Do NOT add order/user listing endpoints — exist.
- Do NOT store derived KPI columns (same rule as Sprint 3).

## 7. Database changes
**None needed.** All KPI candidates derive from existing tables (`orders`, `users`, `deliveries`, `ratings`, `payments`, `audit_logs`) which carry `created_at`/status columns. No migrations proposed.

## 8. Risks / open decisions (no rule in SPEC/PRD → do not invent)
- **O-1 Revenue date semantics**: current = `created_at` date of delivered orders (matches US-70 test). Delivered-date bucketing would change spec'd behavior. Keep unless owner decides.
- **O-2 KPI set for "M5 Admin Analytics" undefined**: intent defers "Advanced analytics and BI". Any KPI beyond finishing REQ-15/16 needs owner definition (e.g. per-merchant leaderboard, cancellation rate, avg delivery time, driver utilization, ratings avg, payment method split).
- **O-3 Currency display** ($) vs config ILS — cosmetic fix decision.
- **O-4 No platform-commission concept exists** anywhere in SPEC — "platform revenue" must not be invented; sum of `orders.total` is the only spec'd figure.
- Risk: `orders.total`/`items_total` are decimal(10,2) — sums fine in SQL; keep sums in DB, not PHP collection, for future scale.
