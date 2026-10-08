# 06 — Tests (M5 Sprint 4)

## New: `tests/Feature/Admin/AdminAnalyticsTest.php` (5 tests, all pass)
| Test | Verifies |
|---|---|
| `test_dashboard_overview_shows_kpi_values` | C1: revenue 111.00 = delivered sum(total) for today (US-70 rule); pending approvals=1; active merchants=1; status table lists delivered + pending rows |
| `test_orders_filter_by_merchant_and_customer` | A1: combined merchant_id+customer_id filter returns only the matching order row (other order's link absent) |
| `test_users_filter_by_role_and_status` | B: `role=driver&status=pending` shows the pending driver, hides the active customer |
| `test_sales_report_respects_date_range` | C2: 3-day range = 210.00 (77+133) count 2; legacy `?date=` single-day = 133.00 and excludes 210.00 (backward compat with US-70) |
| `test_non_admin_cannot_access_dashboard_or_users` | RBAC: customer gets 403 on `admin.dashboard` and `admin.users` |

## Modified: `tests/Feature/Admin/DashboardTest.php` (assertion adjustment, documented rationale)
- `test_admin_can_filter_orders_by_status` previously asserted `assertDontSee('pending')` — page-wide text check. It already FAILED before this sprint (the `$merchants` 500), and after A1 the new status `<select>` legitimately renders every status label including "pending", so a page-wide string check can no longer express US-70's intent ("only the Delivered orders are returned").
- **Adjustment**: assertions now row-scoped — `assertSee('/admin/orders/{deliveredId}')` + `assertDontSee('/admin/orders/{pendingId}')`, keeping the same scenario, data, and spec intent. Test name unchanged.
- Result: **this test went from FAIL → PASS** (it was 1 of the 6 known pre-existing failures).

## Regression results (existing suites)
- Admin suite: AdminAnalyticsTest + DashboardTest + ReportingTest + ApprovalGateTest + AdminLockdownTest → **8+... all pass** (run: 8 passed/30 assertions for the 3 core classes; whole Admin namespace green).
- `ReportingTest` (US-70 sales report, untouched): **passes** — confirms `?date=` backward compatibility.
- `RbacRouteTest`, driver suites, merchant/customer/payment suites: unchanged and green (full run below).

## Full suite: `Tests: 5 failed, 70 passed (195 assertions)`

### Pre-existing failures — now 5 (down from 6)
| Test | Cause | Status |
|---|---|---|
| `OfflineSyncTest` ×3 | sqlite `:memory:` drops `sync_outboxes` | known M4 issue — untouched |
| `SanctumTokenTest` ×2 | sqlite `:memory:` missing `personal_access_tokens` | known M4 issue — untouched |
| ~~`Admin\DashboardTest`~~ | ~~undefined `$merchants` in orders view~~ | **FIXED by A1** ✅ |

### New failures from this sprint: **NONE**
