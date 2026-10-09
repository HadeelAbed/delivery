# Testing & Performance

Audit commit: `1490268`. Results below are from this repository state unless noted.

---

## 1. Running the suite

```bash
php artisan test            # full PHPUnit suite (uses sqlite :memory: per phpunit.xml, QUEUE_CONNECTION=sync)
php artisan test --filter=AccountDeactivationTest     # focused
php artisan test tests/Feature/Sync tests/Feature/E2E   # focused path
php -l <file.php>           # syntax check
./vendor/bin/pint --test <files>   # style check (no writes)
```

- Test config: `phpunit.xml` (in-memory sqlite, `QUEUE_CONNECTION=sync` so jobs like `AssignOrderJob` run inline).
- Test data: Eloquent factories (`database/factories`) + `DemoSeeder`. No real personal data, no hardcoded credentials.

## 2. Verified results (at `1490268`)

| Run | Result |
|---|---|
| Full `php artisan test` | **144 passed, 594 assertions, 0 failures** (assertions + test count vs prior `135` baseline: +9 sync-authorization regression tests) |
| `php -l` (touched files) | clean |
| `./vendor/bin/pint --test` (touched files) | **PASS** |

> Whole-repo `pint --test` still flags the pre-existing untracked debug script `inspect_order.php`; all tracked files are clean.

## 3. Meaningful coverage by area (not just counts)

- Auth/RBAC: `AccountDeactivationTest` (13, incl. anonymization + rollback + collision), `RbacRouteTest`, `LoginRateLimitConfigTest`, `AdminLockdownTest`.
- Orders/state machine: `OrderStateMachineTest`, `OrderFlowTest`, `OrderHandlingTest`, `EndToEndFlowTest`.
- Payments: `CodTest`, `GatewayCallbackTest`, `PaymentWebhookTest` (signature/idempotency).
- Offline sync + ownership: `Sync/OfflineSyncTest`, `Sync/SyncAuthorizationTest` (9: cross-driver, customer/merchant, unassigned, forbidden `assigned`, mixed batch, missing/malformed order_id), `E2E/OfflineSyncJourneyTest`.
- Notifications/Web Push: `NotificationFanoutTest`, `WebPushTest` (9).
- Maps: `GoogleMapsProviderTest` (7), `TrackingMapPageTest` (8), `TrackingPrivacyTest` (4).
- See [REQUIREMENTS_STATUS](../requirements/REQUIREMENTS_STATUS.md) for the per-requirement mapping.

## 4. Performance / load testing (k6)

- **k6 installed?** **No** — `k6 version` → command not found on this machine. Not installed (requires approval for system-wide software).
- **Script:** `k6/load-test.js` + `k6/README.md`. Validated as far as possible: `node --check` → SYNTAX-OK. **Not executed** — runtime results UNMEASURED.
- **Scenarios & thresholds:** (1) order placement + propagation (order-state visible via `GET /api/v1/orders` within threshold), (2) tracking poll at 7s cadence, (3) mixed checkout + offline-sync under concurrency. Global `http_req_failed rate < 1%`. Exact thresholds and interpretation are documented in `k6/README.md`.
- **Safety:** requires explicit `BASE_URL` (non-localhost needs `ALLOW_NONLOCAL_BASE_URL=true`); bounded VUs/duration; credentials only via `--env`.
- **SPEC-001 exit criterion 4 (load verification):** **NOT met** — no measured results. To meet it: install k6 (owner approval), seed per README, run `k6 run --summary-export=k6-summary.json`, record output.

## 5. Labelling legend

**RIT]** Implemented + automated-test verified · **RLV]** Live integration verified · **RM]** Manually verified · **RP]** Pending · **RD]** Deferred · **UNMEASURED** no measured result.
