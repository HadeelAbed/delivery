# Security Overview

- **Audit commit:** `1490268` · **Date:** 2026-10-09
- **Source of truth:** application source at `1490268`. This document describes *implemented* controls, not aspirational ones. Integrations that are coded but not live-verified are labelled explicitly (see [Integrations](../integrations/INTEGRATIONS_STATUS.md)).

## 1. Authentication

| Mechanism | Where | Notes |
|---|---|---|
| Web session auth | `app/Http/Controllers/Auth/LoginController.php`, `routes/web.php` | Bcrypt hashing (`BCRYPT_ROUNDS=12`). Session driver `database`. |
| API token auth (Sanctum) | `routes/api.php` (`auth:sanctum`), `Laravel\Sanctum` | Bearer tokens via `createToken()`. Token scoping on `GET /api/v1/orders`. |
| Rate limiting (login) | `LoginController` + `AppServiceProvider` rate limiter | `LOGIN_RATE_LIMIT` (default 5) attempts/min per email + IP → HTTP 429. |
| Deactivation login block | `LoginController` | Users with `UserStatus::Deactivated` cannot authenticate. |
| Registration role restriction | `RegisterController` (forced role) + `RegisterRequest` (`rule in:customer,merchant,driver`) | Public self-registration **cannot** create `admin`. |

## 2. Authorization (RBAC)

- **Role enum:** `app/Enums/UserRole.php` → `Customer`, `Merchant`, `Driver`, `Admin`.
- **Route gating:** `role` middleware (`app/Http/Middleware/RoleMiddleware.php`) wraps role-specific web route groups; `approved` middleware (`app/Http/Middleware/RequireApproved.php`) blocks `pending` merchants/drivers from restricted operations. See [ROLES_AND_PERMISSIONS.md](ROLES_AND_PERMISSIONS.md) for the full matrix.
- **Policies:** `OrderPolicy` (customer owns / merchant owns / assigned driver / admin), `DeliveryPolicy::manage` (`user->id === delivery->driver_id`), `ProductPolicy`, `CategoryPolicy`.
- **API IDOR control:** `app/Http/Controllers/Api/OrderController.php` scopes reads to the authenticated owner via `OrderPolicy::view` — a token cannot read another user's order by ID.

All authorization is enforced **server-side** (middleware, policies, or service checks). UI/Blade conditionals are never relied upon for security.

## 3. Account deactivation + personal-data anonymization (REQ-18)

`app/Http/Controllers/Auth/DeactivateController.php`, approved **Option B** (deactivation + anonymization). Operation is wrapped in `DB::transaction` (all-or-nothing):

- Sets `status = deactivated`, revokes **all** Sanctum tokens, writes an audit-log row, invalidates the session (logout + CSRF regeneration), and prevents future login.
- **Anonymizes** user-owned PII with deterministic, collision-safe values: `name` → `deleted-user-{id}`, `email` → `deleted-{id}@deleted.example` (unique per id — no cross-user collision), `phone` → `deleted-{id}`, `remember_token` overwritten, and stale `password_reset_tokens.email` re-keyed.
- **Preserves** business/history records: orders, payments, deliveries, financial amounts, and FK identifiers (`customer_id`, `merchant_id`, `driver_id`, `actor_id`) so relationships still resolve.
- The new audit record stores **no original PII** (entity class + id + a fixed `permanent_deactivation` flag only).
- Verified by `tests/Feature/Security/AccountDeactivationTest.php` (13 tests incl. rollback and collision coverage).

## 4. CSRF & session security

- All state-changing Blade forms carry `@csrf`; verified across registration, checkout, cart, ratings, admin, and subscription forms.
- Session cookies: `SESSION_SECURE_COOKIE` env (default `false`). **Production must set it `true`** and serve over HTTPS so cookies are HTTPS-only. `SESSION_ENCRYPT=false` by default — set `true` if the at-rest session store must be encrypted.
- Logout invalidates the session and regenerates the token (`Auth::logout`).

## 5. Payment integrity

`app/Services/PaymentService.php::handleCallback` (`POST /api/payments/callback/{gateway}`):

- **Signature verification** for Jawwal Pay and PalPay callbacks (HMAC over the payload using the configured secret). A callback **cannot** mark a payment successful without valid server-side verification.
- **Idempotency:** unique index on `payments.reference_id` + replay guard — duplicate callbacks do not double-apply.
- Only `status` + `reference_id` are stored; raw gateway payloads are not persisted as PII.
- COD (`CodDriver`) is a no-op that sets `pending_cod` — no external verification.
- Covered by `PaymentWebhookTest`, `CodTest`, `GatewayCallbackTest`.

## 6. Offline-sync ownership (fixed in `1490268`)

`app/Services/SyncService.php::processBatch` → `authorizeDeliveryAction($user, $order, $type)`, run **after** the state-machine gate and **before** the transaction:

- `assigned` → always rejected (`unauthorized`). Assignment is exclusively `AssignOrderJob`'s responsibility (it creates `Delivery` rows directly and never routes through sync).
- `out_for_delivery` / `delivered` / `failed` → require `role == Driver` **and** a `Delivery` row for that order with `driver_id == user->id`.
- Customers and merchants cannot perform driver-only delivery transitions via sync.
- Unauthorized actions return `{reason: "unauthorized"}` in the existing `conflicts[]` shape with **no** order/delivery mutation and no `sync_outbox` row. Idempotency and per-action transaction safety are unchanged.
- Verified by `tests/Feature/Sync/SyncAuthorizationTest.php` (9 tests).

> Prior to `1490268`, `$user` was never used for authorization on the sync path (only `OrderStatus::canTransitionTo`, which validates the status hop, not the actor) — any authenticated user of any role could drive a valid transition on any order. That gap was already noted in `docs/M5/sprint-7-offline-sync-journey/01-analysis.md` (Q5) and is now closed.

## 7. Secrets management

- All secrets are **environment-only**. `.env.example` ships **empty** placeholders for `VAPID_*`, `GOOGLE_MAPS_*`, `JAWWAL_PAY_SECRET`, `PALPAY_SECRET`. No real secret is committed.
- `config/webpush.php` and `config/maps.php` read exclusively from `env()`; the Google **server key** is used only by `GoogleMapsProvider` (server-side HTTP) and is never rendered to Blade/JS/API/logs.
- **⚠️ Finding (Low/Medium):** `config/payment.php` defines default values `'jawwal_secret' => env('JAWWAL_PAY_SECRET', 'test-secret')` and `'palpay_secret' => env('PALPAY_SECRET', 'test-secret')`. The `test-secret` default is a weak, committed fallback. **Production must set both env vars** to strong secrets; otherwise callbacks are verified against a publicly known secret. Classified as *Implemented but needs production config*.

## 8. Data privacy

- PII fields (`name`, `email`, `phone`) are anonymized on deletion (REQ-18). Business records keep referential integrity without exposing the deleted user's identity beyond `deleted-{id}`.
- New audit records and log lines never contain VAPID/subscription private keys or newly-anonymized original PII (test-asserted in `WebPushTest` and `AccountDeactivationTest`).
- Merchant business profile and driver verification fields are intentionally **not** anonymized in V1 (preserves business/audit history per Option B) — see [Changelog](../maintenance/CHANGELOG_AND_DECISIONS.md).

## 9. Logging & error handling

- `LOG_CHANNEL=stack`, `LOG_LEVEL=debug` in local. Production should reduce to `warning`/`error` to avoid logging sensitive payloads at debug level.
- Push-delivery failures log `user_id`/`subscription_id` only. Map provider failures log a generic warning without keys or coordinate payloads.

## 10. Remaining risks / verification status

| Item | Status |
|---|---|
| Offline-sync ownership (driver-only, no `assigned`) | **Verified secure** (automated tests, `1490268`) |
| Admin-only actions server-side gated | **Verified secure** (`role` middleware + policies + `AdminLockdownTest`) |
| Customer/merchant cannot touch others' orders (web + API) | **Verified secure** (policies + `ApiAuthorizationTest`) |
| Payment callback signature + idempotency | **Automated-test verified** (mock drivers); live gateway verification pending |
| Web Push delivery | **Implemented, degraded-by-default**; live delivery **unverified** (no VAPID keys) |
| Google Maps rendering/routing/ETA | **Implemented, graceful fallback**; live rendering **unverified** (no API keys) |
| `SESSION_SECURE_COOKIE` / HTTPS / `SESSION_ENCRYPT` | **Pending** — production config only (not a code gap) |
| Payment default `test-secret` fallback | **Finding** — set env secrets in production |

