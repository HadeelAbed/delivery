# Integrations Status

Audit commit: `1490268` (date: 2026-01-XX). Labels: **[IT]** Implemented + automated-test verified · **[LV]** Live integration verified with real credentials · **[P]** Pending live verification · **[D]** Deferred.

> No integration below is **[LV]**. Code exists and is covered by automated tests for the configured and degraded code paths; live end-to-end behaviour requires external credentials and manual verification.

---

## 1. Web Push (VAPID)

- **Status:** **[IT]** / **[P]** — implemented, live delivery NOT verified.
- **Library:** `minishlink/web-push` (Composer).
- **Config vars (names only):** `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT` (defined in `config/webpush.php`; empty placeholders in `.env.example`). Private key stays server-side only.
- **Code:** `app/Services/Push/WebPushService.php` (`sendToUser`), `app/Notifications/Channels/WebPushChannel.php`, `app/Models/PushSubscription.php`, migration `..._create_push_subscriptions_table`, endpoints `POST /push/subscribe|/unsubscribe` (`PushSubscriptionController`, `auth` + `throttle:30,1`), `public/sw.js`, `php artisan webpush:vapid` keygen (prints only).
- **Fallback:** when VAPID keys are absent, `WebPushService::sendToUser` returns `{sent:0, cleaned:0}` and the `database` notification channel still writes the row. Push failures never break database fanout. Expired (HTTP 404/410) subscriptions are auto-deleted.
- **Tests:** `tests/Feature/Notification/WebPushTest.php` (9) — degraded no-op, configured fanout, invalid-subscription cleanup, no-secret-leak, authorization/guest-block. **[IT]**
- **Remaining setup:** generate VAPID keys on a machine with `gmp` (this dev box's PHP lacks it — `webpush:vapid` fails there), place in `.env`, create a real browser subscription, send a real push. Until then **[P]**.

---

## 2. Google Maps (routing / ETA / map UI)

- **Status:** **[IT]** / **[P]** — implemented, live rendering/routing/ETA NOT verified.
- **Config vars (names only):** `GOOGLE_MAPS_BROWSER_KEY` (client, referrer-restricted), `GOOGLE_MAPS_SERVER_KEY` (server-only, never rendered). Defined in `config/maps.php`; empty placeholders in `.env.example`.
- **Code:** `app/Contracts/{MapProvider,MapPoint,RouteEstimate}`, `app/Services/Maps/GoogleMapsProvider.php` (Laravel HTTP client → Directions, 60s cache, failures → `null`), `app/Providers/MapServiceProvider.php`, `TrackingController@page|show` (route estimate in JSON; server key never in payload), `resources/views/partials/map.blade.php` + `customer/tracking.blade.php`, route `GET /customer/orders/{order}/track` (`orders.track`).
- **Fallback:** missing key / provider error / missing coordinates → graceful `null` and the page renders the existing text/polling experience (7s poll) unchanged.
- **Tests:** `tests/Unit/Services/GoogleMapsProviderTest.php` (7), `tests/Feature/Tracking/TrackingMapPageTest.php` (8), `TrackingPrivacyTest` (4) unchanged. **[IT]**
- **Remaining setup:** supply browser + server keys, restrict in Google Cloud Console, visually verify map + pins + ETA in a browser. Until then **[P]**.

---

## 3. Payments — Jawwal Pay / PalPay / Cash on Delivery

- **Status:** **[IT]** — implemented against mock gateways; **production credentials deferred** (see [REQUIREMENTS_STATUS](../requirements/REQUIREMENTS_STATUS.md) C-07). Live production payment settlement NOT verified **[P]**.
- **Architecture:** `app/Contracts/PaymentGateway` + `app/Services/Payment/{CodDriver,JawwalDriver,PalpayDriver}` behind `config/payment.php` (`PAYMENTS_DRIVER`). `PaymentService` records `status` + `reference_id` only (no card data); idempotency via unique `payments.reference_id`.
- **Callback:** `POST /api/payments/callback/{gateway}` → `Api\PaymentCallbackController` → `PaymentService::handleCallback`. HMAC signature verified server-side before any status change; invalid signature is rejected and cannot mark a payment successful.
- **Tests:** `CodTest`, `GatewayCallbackTest`, `PaymentWebhookTest` (7) — signature valid/invalid, idempotent replay, status transitions. **[IT]**
- **Remaining setup:** real Jawwal/PalPay merchant credentials + webhook signing secrets via env; a sandbox/production callback verification run. Until then production payment flow **[P]**.

---

## 4. Notification channels — SMS / Email

- **Status:** SMS **[D]** (deferred; no `SmsChannel`/provider in code). Email receipts **[D]** (no mail receipt channel found). In-app (`database` channel) and Web Push (§1) are the implemented channels.
- **Tests:** in-app fanout — `NotificationFanoutTest`. **[IT]** (in-app only). SMS/email have no implementation to test.

---

## 5. Offline synchronization (real-world conditions)

- **Status:** **[IT]** — server contract implemented and hardened (driver-ownership authorization in `1490268`). Browser Service Worker + IndexedDB client queue is **[D]/unverified** for realistic flaky-network behaviour.
- **Code/tests:** see [FEATURES_AND_WORKFLOWS §7](../features/FEATURES_AND_WORKFLOWS.md) and [SECURITY_OVERVIEW §6](../security/SECURITY_OVERVIEW.md). **[IT]** on the server side; end-to-end device offline/online cycling not manually verified **[P]**.

---

## Summary

| Integration | Implemented | Automated-test | Live verified | Blocked on |
|---|---|---|---|---|
| Web Push | Yes | Yes | No | VAPID keys + browser subscription |
| Google Maps | Yes | Yes | No | Browser + server API keys |
| Jawwal / PalPay | Yes (mock) | Yes | No | Merchant credentials + signing secret |
| COD | Yes | Yes | No (no external) | — |
| SMS | No | — | — | Deferred (owner decision) |
| Email receipts | No | — | — | Deferred (owner decision) |
| Offline client SW | Server only | Yes (server) | No | Manual device testing |
