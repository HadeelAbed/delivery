# 04 — Database

## Changes in this sprint: **NONE** (per plan 02 — no migrations allowed).

| Table | Role in this sprint |
|---|---|
| `orders` | journey state assertions (status path pending→merchant_accepted→assigned→out_for_delivery→delivered / cancelled) |
| `order_items`, `payments` | checkout assertions (totals, `pending_cod`) |
| `deliveries` | assignment (sync queue), pickup/deliver timestamps, absence on cancel |
| `ratings` | created by repaired rating hop; unique `order_id` backs the one-rating-per-order rule (controller unique rule now matches DB exactly) |
| `notifications` | fanout assertions per transition |
| `driver_locations` | seeded to trigger nearest-driver assignment |

No columns added/removed; no KPI or derived fields persisted (standing doctrine from Sprints 3–4); the removed `whereNull('deleted_at')` referenced a column that never existed — the query now matches the actual schema.
