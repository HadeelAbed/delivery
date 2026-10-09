# Project Overview — Gaza Delivery Marketplace

- **Audit date:** 2026-10-09 · **Commit:** `1490268`
- **Stack:** Laravel 11 (PHP), Blade, SQLite (dev/test), Sanctum, `minishlink/web-push`, Laravel HTTP client (Google Maps)
- **UI languages:** Arabic (`ar`) + English (`en`) via `resources/lang/{ar,en}`

## Product scope (V1)

Four user roles on a single marketplace:

| Role | Core job |
|---|---|
| **Customer** | Browse merchants/products, cart, checkout (COD or digital), track orders, rate completed orders, favorite merchants, manage profile, self-deactivate/anonymize account |
| **Merchant** | Manage categories & products, receive/accept/reject/prepare orders, view own order history |
| **Driver** | Go online/offline, receive nearest-order assignment, report pickup/delivery/failure, offline queue + sync, view earnings/history |
| **Admin** | Approve/reject merchants & drivers with reason, filter users & orders, view sales report, oversee audit logs |

### Business/integrity guarantees preserved on account deletion (REQ-18, Option B)
Orders, payments, deliveries, financial amounts, and audit records are **never** anonymized or deleted — only the user's personal contact fields (`name`, `email`, `phone`, `remember_token`, `password_reset_tokens` email). Foreign keys stay intact so history remains valid.

## Target users & Gaza launch context

Designed for the Gaza market: phone-first contact capture at registration, COD as a primary method, bilingual UI, and offline-tolerant driver flows (drivers may lose connectivity between pickup and delivery). No production hosting, domain, or real payment credentials are configured in-repo.

## V1 boundaries

**In scope (implemented at `1490268`):** full customer/merchant/driver/admin web workflows, order state machine, COD + mock Jawwal Pay / PalPay, offline sync with ownership enforcement (see `security/`), in-app notifications, Web Push (degraded-by-default), Google Maps tracking UI (degraded-by-default), Arabic/English, demo seeders/factories, RBAC + Sanctum API.

**Deferred (owner decisions, post-V1):**
- Analytics C3–C7 (merchant revenue, cancel rate, avg delivery time, rating aggregates, driver leaderboard) — deferred in Sprint 4
- Extended scope (native mobile apps, loyalty, AI, inventory, live chat, subscriptions, batched deliveries, broader offline)
- Live verification of Web Push, Google Maps, payment gateways (requires credentials)

## Current readiness status

- **Feature-completeness:** ~95% of SPEC-001 counting method (see `requirements/REQUIREMENTS_STATUS.md`); all automatizable acceptance criteria covered by tests.
- **Tests:** 144 passed / 594 assertions / 0 failures (see `testing/TESTING_AND_PERFORMANCE.md`).
- **Blockers to a *safe* end-to-end MVP launch:** (1) supply Google Maps keys for live map/routing/ETA; (2) supply VAPID keys for live push; (3) production payment-gateway credentials + HTTPS; (4) execute the k6 script (k6 not installed). None block an internal/staging demo — every external dependency degrades gracefully.
