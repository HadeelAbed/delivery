# Changelog & Decisions

Audit commit: `1490268`. Milestones (conventional commits) and the decisions behind them. Distinguishes **implemented** from **live/externally verified**.

---

## 1. Implementation milestones (commit history)

| Commit | Message | What it delivered | Verification |
|---|---|---|---|
| `717da3f` | `feat: anonymize personal data on account deletion` | REQ-18 Option B: atomic deactivate + personal-data anonymization (name/email/phone/remember_token), audit preserved, tokens revoked | Automated |
| `1dc2583` | `docs: correct stale sprint-5 payment webhook statements` | Corrected 3 docs claiming the payment webhook was absent (it was implemented) | Docs only |
| `50e5285` | `feat: add web push notifications with vapid config and graceful fallback` | `minishlink/web-push`, subscriptions, subscribe/unsubscribe endpoints, `WebPushChannel`, degraded when VAPID absent | Automated; **live push unverified** |
| `173c2c1` | `feat: add google maps tracking ui with routing eta and graceful fallback` | Map provider abstraction, Google Directions ETA, map partial + tracking route, graceful fallback | Automated; **live map/routing unverified** |
| `596239d` | `test: add k6 local load-test suite for req-17` | `k6/load-test.js` + README; node-syntax-validated; **not executed** | Script only; **thresholds unmeasured** |
| `1490268` | `fix: enforce driver ownership on offline order sync` | Closed IDOR: sync authorizes actor (driver-ownership, forbids client `assigned`); regression tests | Automated |

## 2. Key architectural decisions

- **REQ-18 Option B (approved):** account "deletion" = deactivate + anonymize PII, preserving orders/payments/deliveries/audit and FKs. Anonymized email/phone include the user id for collision safety.
- **Services-first:** business logic in services (`OrderService`, `PaymentService`, `SyncService`, `TrackingService`, `RatingService`) shared by web controllers and `api/v1` Sanctum controllers.
- **State machine:** `OrderStatus::canTransitionTo` gates every order transition; sync runs the state-machine gate **before** authorization so existing `invalid_transition` semantics are preserved.
- **Payments behind an interface:** `PaymentGateway` with `PAYMENTS_DRIVER` config; store status + reference only; server-side HMAC verification on callbacks.

## 3. Approved security decisions (this release)

- **Sync ownership (Policy B, approved):** only an assigned driver may perform delivery transitions via `/api/sync`; customers/merchants cannot; clients cannot set `assigned` (assignment stays in `AssignOrderJob`). Implemented in `1490268` with 9 regression tests; three pre-existing tests updated (authorized) to use realistic assigned-delivery fixtures.

## 4. Blocked / deferred (not in current approved scope)

- **Blocked on credentials:** Web Push live delivery (VAPID keys); Google Maps live rendering/routing (API keys); production Jawwal/PalPay settlement (merchant credentials + signing secret). Implemented + automated-tested; **live unverified**.
- **Blocked on tooling/CI:** k6 execution (k6 not installed; repo has no CI environment). Thresholds **unmeasured**.
- **Deferred by owner:** analytics C3–C7 (merchant revenue, cancel rate, avg delivery time, rating aggregates, driver leaderboard); SMS/email notification channels; the nine extended-scope items (mobile apps, loyalty, AI, inventory, live chat, subscriptions, order batching, extended offline, etc.) — see [REQUIREMENTS_STATUS](../requirements/REQUIREMENTS_STATUS.md).
- **Map UI surface** was implemented (`173c2c1`); its live verification remains pending keys.

## 5. Documentation index

See [docs/README.md](../README.md) for the full document map and reading order.
