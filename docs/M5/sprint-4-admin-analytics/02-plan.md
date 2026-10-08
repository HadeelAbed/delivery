# M5 Sprint 4 — Admin Analytics: Proposed Plan (AWAITING APPROVAL — no code yet)

## Guiding principle
Extend M4; never rebuild. Fix M4 gaps first, then add only analytics whose rules are either spec'd (REQ-15/16, US-70) or owner-approved via the open decisions in 01-analysis.

## Scope A — Repair M4 admin/reporting (prerequisite, not "new analytics")
| # | Change | Files | Rule source | DB |
|---|---|---|---|---|
| A1 | Pass `$merchants`, `$customers`, `$filters` from `Admin\OrderController@index` (dropdowns from users role scopes) | `Admin/OrderController.php` | REQ-15; fixes failing `DashboardTest` | none |
| A2 | Remove duplicate hidden `status` input; use single status select from `OrderStatus` enum | `admin/orders.blade.php` | US-70(b) | none |
| A3 | Fix sidebar link `/admin/reports` → `/admin/reports/sales`; add Approvals link; extract shared `admin/partials/sidebar.blade.php` | admin views | PLAN-001 access URLs | none |
| A4 | Delete dead `admin/eports.blade.php` (0 bytes) | file removal | hygiene | none |
| A5 | Currency from `config('delivery.currency')` instead of hardcoded `$` | admin reports/orders views | config/delivery.php | none |
| A6 | Admin nav in `layouts.app` header (currently only role check) | `layouts/app.blade.php` | REQ-21 | none |

Tests: DashboardTest filter test goes green (known failures 6 → 5); add merchant/customer filter assertions.

## Scope B — Complete REQ-15: user filtering
| # | Change | Files | Rule | DB |
|---|---|---|---|---|
| B1 | `GET /admin/users` with `role`, `status` filters + sidebar link | NEW `Admin/UserController@index`, route, `admin/users.blade.php` | REQ-15 "view/filter ... users" (spec'd) | none |
| B2 | `UserController@show` detail (defer if time) | — | — | none |

Reuse: `User::merchant()/driver()/pending()` scopes, `Gate::admin.access`, pagination pattern.

## Scope C — Genuine M5 analytics (each flagged: rule status)
Service: **NEW read-only `app/Services/AdminAnalyticsService.php`** (pattern: Sprint 3 `DriverStatsService` — computed on read, no persisted KPI columns, no migrations).

| # | KPI / feature | Data source | Business rule status | Calculation | Reuse | New code | DB |
|---|---|---|---|---|---|---|---|
| C1 | Admin dashboard overview: today's delivered count + revenue, orders by status, pending approvals, active drivers/merchants | `orders`, `users` | Extends PLAN-001 unchecked "Admin dashboard UI refinements"; figures reuse US-70 rule (delivered, `sum(total)`, `whereDate(created_at, date)`) | COUNT/SUM grouped by status | ReportController query pattern, summary-card CSS | NEW `Admin\DashboardController@index`, `admin/dashboard.blade.php`, `GET /admin` route, service methods | none |
| C2 | Date-range sales report (from/to) | `orders` | Additive UX; same aggregation rule as spec'd single-date report | `whereBetween(created_at,...)` | extend `Admin\ReportController@sales` in place | param + view inputs | none |
| C3 | Per-merchant sales breakdown (top merchants by delivered revenue) | `orders` grouped by merchant_id | **OPEN O-2a** — advanced analytics deferred (intent §6.2); needs approval | `selectRaw sum/count groupBy orderByDesc` | Order↔merchant relation | service method + reports section | none |
| C4 | Cancellation rate (cancelled/total, optional range) | `orders.status` | **OPEN O-2b** — states exist (REQ-08), no KPI rule | counts via `OrderStatus::Cancelled` | enum, Order model | service method | none |
| C5 | Avg delivery time (`deliveries.picked_up_at → delivered_at`) | `deliveries` | **OPEN O-2c** — timestamps exist, no KPI rule | avg diff on non-null rows | Delivery model | service method | none |
| C6 | Ratings averages (merchant & driver) | `ratings` | Scale 1–5 spec'd (C-06); average is presentational | avg(merchant_score), avg(driver_score) | Rating model (read-only) | service method | none |
| C7 | Payment method split | `orders.payment_method` | Methods spec'd (REQ-12); split is presentational | groupBy count/sum | PaymentStatus enums | service method | none |

**Recommended approval set**: A + B + C1 (+ C2 if desired). C3–C7 explicitly await owner decision — flagged, not invented.

## Not duplicating
- No second sales/report controller/route — extend `Admin\ReportController`.
- No new approvals page — keep `ApprovalController`.
- No stored KPI columns / aggregate tables.
- No "platform revenue/commission" figure (O-4: does not exist in SPEC).

## Tests proposed (new)
- Fix + extend `DashboardTest` (status/merchant/customer filters).
- NEW `tests/Feature/Admin/AdminAnalyticsTest.php`: dashboard KPI values from seeded orders (delivered today vs pending vs cancelled), RBAC 403 for non-admin; range boundaries if C2; breakdown totals if C3.
- Known-failure tracking: A1 eliminates 1 of 6 (DashboardTest); remaining 5 (3 OfflineSyncTest + 2 SanctumTokenTest) untouched and reported separately.

## Execution order after approval
03-implementation → Scope A (verify DashboardTest green) → B → C1 (+ approved C items) → tests → 04-database (expected: none) → 05-routes-and-views → 06-tests → 07-final-summary.

