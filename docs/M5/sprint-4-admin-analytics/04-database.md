# 04 — Database

## Changes in this sprint: **NONE** (by design — approved in 02-plan).

All KPI/analytics figures are computed at read time from existing tables:

| Table | Columns used | Used by |
|---|---|---|
| `orders` | status, total, created_at, merchant_id, customer_id | dashboard KPIs (C1), sales range report (C2), order filters (A1) |
| `users` | role, status, created_at | pending approvals, active role counts (C1), users filter (B) |
| `deliveries`, `ratings`, `payments` | untouched this sprint | C3–C7 candidates (deferred) |

## Why no schema change
- **No persisted KPI fields** (approved constraint): `today_revenue`, `orders_by_status`, `pending_approvals` etc. are derived per request — same decision as Sprint 3 `DriverStatsService`.
- Revenue rule = sum of `orders.total` of delivered orders (US-70) — `total` already stored; no new money columns.
- Date-range reporting works on the indexed-enough `orders.created_at` timestamp column already present.

## Migrations added: none. Migrations removed: none. Config changed: none (`delivery.currency`/`delivery.delivery_fee` read as-is).
