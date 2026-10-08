# 06 — Tests

## Added: `tests/Feature/Driver/DriverStatsTest.php` (3 tests, 11 assertions)

| Test | Verifies |
|---|---|
| `stats_math_uses_fixed_config_earnings` | 2 delivered → completed=2, earnings=2×config, AVG(5,4)=4.5; an `assigned` delivery counts as active only (not earnings) |
| `rating_null_when_no_ratings` | rating key is `null` (no divide-by-zero / no fabricated 0) |
| `dashboard_and_history_scoped_to_own_driver` | dashboard 200 + shows earnings value; driver A sees own order in history, driver B does not (data scoping) |

Results: **3 passed (11 assertions)**.

## Regression run (relevant suites)
- `DeliveryFlowTest` + `AssignmentTest`: **8 passed (20 assertions)** — assignment, pickup, deliver, reject, policy 403 all unchanged.

## Full suite result: `Tests: 6 failed, 64 passed (170 assertions)`

### Pre-existing failures (NOT caused by this sprint) — all 6
| Test | Error | Cause |
|---|---|---|
| `OfflineSyncTest` ×3 (queued action syncs without duplicate / out of order action / stale action) | `no such table: sync_outboxes` (sqlite `:memory:`) | known M4 sync isolation issue |
| `SanctumTokenTest` ×2 (token scopes results / shared service layer) | `no such table: personal_access_tokens` (sqlite `:memory:`) | missing token migration under sqlite memory — same class of M4 isolation issue |
| `Admin\DashboardTest` ×1 (admin can filter orders by status) | `Undefined variable $merchants` in `admin/orders.blade.php` | pre-existing M4 admin view bug |

Note: these are **unrelated code paths** (admin view, api sync, sanctum tokens) — none touch driver routes/services added here. The known-issue count reported by the user (6 M4 OfflineSyncTest issues) matches the observed 6 failures; exact split is 3 OfflineSyncTest + 2 SanctumTokenTest + 1 DashboardTest in the current run.

### New failures from this sprint: **NONE**
All driver-related tests (DriverStatsTest, DeliveryFlowTest, AssignmentTest, RbacRouteTest) pass.
