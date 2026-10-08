# 05 — Routes & Views (M5 Sprint 6)

## Routes/API changes: **NONE** (`php artisan route:list` unchanged — 65 routes).

No routes added, removed, renamed, or re-middleware'd. No view files created or modified.

## Behavior restored (no surface change — previously broken paths now function)
| Surface | Before Sprint 6 | After |
|---|---|---|
| `POST /api/sync` (authenticated, valid actions) | 500 (`no such table: sync_outboxes`) | 200 with `ack`/`conflicts` per `SyncService` (REQ-11) |
| Sanctum token creation (`createToken`) | exception (`no such table: personal_access_tokens`) | token issued; `Authorization: Bearer` works for `GET /api/v1/orders{,/{id}}` |

Request flow is unchanged: `SyncController@sync` → `SyncService::processBatch` (idempotency by `client_uuid`, `OrderStatus::canTransitionTo` conflict rule, transaction + audit via `OrderService::transition`); API reads stay behind `auth:sanctum` with `OrderPolicy::view` scoping.

## Authorization/validation
Untouched — `auth:sanctum` on API group, `role` middleware on web, `Gate::admin.access`, policies all as before (Sprint 6 ran the full RBAC/notification/tracking suites green as regression evidence).
