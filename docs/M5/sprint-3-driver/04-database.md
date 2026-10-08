# 04 — Database

## Changes in this sprint: **NONE** (by design).

No migration was added. Verified by inspecting existing schema:

| Table | Relevant columns | Used for |
|---|---|---|
| `deliveries` | driver_id, status, picked_up_at, delivered_at | completed count, active count, history |
| `ratings` | order_id (unique), driver_score 1-5 | driver rating via join on order_id |
| `driver_profiles` | vehicle_*, verification_docs, service_area | untouched — deliberately no earnings/count/rating columns |
| `payments` | order_id, method, status, reference_id, payload | **no amount column** — payments are for gateway state only, never driver earnings |
| `orders` | delivery_fee (15.00), total | customer-side fee; NOT driver earnings |

## Why no columns on `driver_profiles`
`total_earnings`, `completed_deliveries_count`, `driver_rating` are all derivable:
- count: `SELECT count(*) FROM deliveries WHERE driver_id=? AND status='delivered'`
- earnings: count × `config('delivery.driver_earnings')`
- rating: `AVG(ratings.driver_score)` where `ratings.order_id IN (SELECT order_id FROM deliveries WHERE driver_id=? AND status='delivered')`

Persisting them would duplicate calculated data (drift risk on reject/refund/manual edits). Current scale (single-city MVP) gives no performance reason to denormalize; revisit only if dashboards become hot (documented as deferred in 07).

## Earnings rule (source of truth: config, not DB)
`config/delivery.php` → `driver_earnings = 10.00` (ILS), fixed per delivered order (SPEC-001 decision **C-04**). Nothing monetary is stored per-delivery row.
