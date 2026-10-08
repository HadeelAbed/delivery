# 05 — Routes & Views (M5 Sprint 7)

## Routes/API changes: **NONE** — no route added, removed, renamed, or re-middleware'd. No view created or modified.

## Routes exercised by the new E2E tests (existing surface, verified live)
| Route | Auth used in test | Sprint 7 usage |
|---|---|---|
| `POST /api/sync` (name `api.sync`, `auth:sanctum`) | **Bearer token** (Sanctum — first time in E2E; previously only session-auth slices) | batch sync, replay, stale, mixed batch |
| `POST driver.toggle-online` | session (`actingAs`) | offline/reconnect narrative |
| `customer.cart.add`, `customer.checkout.place` | session | journey setup |
| `merchant.orders.accept`, `merchant.orders.ready` | session | journey setup → inline assignment |
| `GET admin.reports.sales` | session | downstream convergence (US-70) |

## Request flow proven end-to-end
`cart → checkout (order+payment pending_cod) → accept → ready (event + AssignOrderJob sync) → [driver offline] → Bearer POST /api/sync {actions[]}` → `SyncController` validation → `SyncService::processBatch` (idempotency → order lookup → `canTransitionTo` gate → per-action transaction { `OrderService::transition` → `mirrorDeliveryState` (7b) + event fanout + audit; `SyncOutbox` row }) → `{ack, conflicts}` → replay short-circuits on `status=completed` → admin report reads the delivered order.

## Authorization/validation (unchanged, observed)
- Bearer takes precedence in `auth:sanctum`; token created via `createToken()` (F2 enabler); `test_sync_requires_authentication` (existing) still covers the 401 path — not duplicated.
- `SyncController` input rules (`actions.*.client_uuid/type` required) unchanged; no new validation added.
