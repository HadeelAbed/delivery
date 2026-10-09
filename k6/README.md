# k6 Load-Test Suite — REQ-17 (SPEC-001)

Performance verification for SPEC-001 REQ-17 / Exit Criterion 4. Runs **outside
PHPUnit** by design (SPEC-001 §2 performance note) and is **not** wired to CI
(this repository has no CI environment).

> **Execution status:** runtime results are **UNVERIFIED** as of authoring —
> `k6` was not installed on the machine where this script was written. The
> script passed syntax validation only. Run the commands below and record the
> JSON summary to establish actual results.

## Scenarios and thresholds

| # | Scenario | Load (bounded) | Metric | Threshold | How measured |
|---|----------|----------------|--------|-----------|--------------|
| 1 | `order_placement` | 4 VUs, 8 iterations, ≤3 min | `checkout_placement_ms` | p95 < 3000 ms | Cart→`POST /customer/checkout` round trip |
| 1 | `order_placement` | same | `order_propagation_ms` | p95 < 3000 ms | Checkout POST start → new order visible via `GET /api/v1/orders` (shared service-layer read path). Unseen after 5 s is recorded as 6000 ms so the threshold fails honestly |
| 1 | `order_placement` | same | `http_req_duration{stage:placement}` | p95 < 3000 ms | k6 request timing, placement scenario only |
| 2 | `tracking_poll` | 2 VUs × 2 min, `sleep(7)` | `poll_interval_ms` | p90 < 8500 ms | Time between consecutive polls in the same VU (7000 ms sleep + request time + jitter) |
| 2 | `tracking_poll` | same | `http_req_duration{stage:tracking}` | p95 < 1500 ms | Server processing per poll |
| 2 | `tracking_poll` | same | `tracking_fresh_rate` | **report only** | Fresh driver-location flag in each response (decays to 0 without a live driver — see interpretation) |
| 3 | `mixed_checkout` + `mixed_sync` | ≤4 + ≤3 VUs, ≤2.5 min | `http_req_duration{stage:mixed}` | p95 < 3000 ms | Both mixed scenarios |
| 3 | `mixed_sync` | same | `sync_accepted_rate` | report only | `POST /api/sync` answered 200 |
| all | — | — | `http_req_failed` | rate < 1 % | k6 across every scenario (auth/api included) |

The **1–3 s propagation target is only “met” if the executed summary shows
`order_propagation_ms p95 < 3000`**. Never claim it from the script alone.

## Prerequisites

- **k6 ≥ 0.50** (scenario-level `tags`): https://k6.io/docs/get-started/installation/
- A running app instance on **localhost/staging** (`php artisan serve` or XAMPP),
  database migrated and demo data seeded (see below).
- PHP CLI for the seed/prep commands.

## Safety rules (enforced in `load-test.js`)

- `BASE_URL` is **required** — the script throws without it.
- Non-localhost `BASE_URL` additionally requires `--env ALLOW_NONLOCAL_BASE_URL=true`.
  **Never** point this at production.
- Bounded by construction: ≤13 concurrent VUs, ≤3 minutes total, COD only,
  no parallel/looping order spam beyond the fixed iteration counts.
- No credentials are hardcoded; everything is passed via `--env`.

## Seed-data setup

```powershell
# 1. Migrate + deterministic demo data (customer/merchant/driver/products/orders):
php artisan migrate:fresh --seed          # DatabaseSeeder → DemoSeeder
```

DemoSeeder creates `demo.customer@example.com`, `demo.merchant@example.com`,
`demo.driver@example.com` (password printed by your own setup — pass it via
`CUSTOMER_PASSWORD`, do not commit it).

```powershell
# 2. API tokens (values are per-installation secrets — export, do not commit):
php artisan tinker --execute="echo App\Models\User::where('email','demo.customer@example.com')->first()->createToken('k6')->plainTextToken;"
php artisan tinker --execute="echo App\Models\User::where('email','demo.driver@example.com')->first()->createToken('k6')->plainTextToken;"

# 3. Tracking order: flip the demo 'assigned' order to out_for_delivery and
#    plant a driver location (needed by the tracking_poll scenario):
php artisan tinker --execute="$o = App\Models\Order::where('status','assigned')->first(); $o->update(['status'=>'out_for_delivery']); App\Models\DriverLocation::create(['driver_id' => App\Models\User::where('email','demo.driver@example.com')->first()->id, 'order_id' => $o->id, 'latitude' => 31.51, 'longitude' => 34.48]); echo $o->id;"

# 4. Terminal demo order id (sync scenario target — already delivered, so
#    concurrent sync actions resolve to non-mutating invalid_transition conflicts):
php artisan tinker --execute="echo App\Models\Order::where('status','delivered')->first()->id;"
```

## Execution

```powershell
k6 run `
  --env BASE_URL=http://127.0.0.1:8000 `
  --env CUSTOMER_EMAIL=demo.customer@example.com `
  --env CUSTOMER_PASSWORD=<your-seed-password> `
  --env CUSTOMER_API_TOKEN=<customer-token-from-step-2> `
  --env DRIVER_API_TOKEN=<driver-token-from-step-2> `
  --env TRACKING_ORDER_ID=<tracking-order-id-from-step-3> `
  --env SYNC_ORDER_ID=<delivered-order-id-from-step-4> `
  --summary-export=k6-summary.json `
  k6/load-test.js
```

If `TRACKING_ORDER_ID` or `DRIVER_API_TOKEN`/`SYNC_ORDER_ID` are omitted, the
corresponding scenario is **skipped with a warning**; the other scenarios still
run (partial coverage — the summary will not contain their metrics).

## Interpreting results

k6 exits **non-zero when any threshold fails**. In the console:

- `✓` / `✗` next to each threshold = pass / fail of that assertion.
- In `k6-summary.json`, inspect `metrics.<name>.thresholds` (`ok: true|false`)
  and `metrics.<name>.values` (`p(90)`, `p(95)`, `avg`, `rate`, `count`).

Key readings:

| Signal | Healthy | Failure meaning |
|--------|---------|-----------------|
| `order_propagation_ms p(95)` | < 3000 | Writes not visible to the shared read path within 3 s (REQ-17 violation) or API tokens invalid (`p(95)=6000` = never visible) |
| `checkout_placement_ms p(95)` | < 3000 | Checkout path too slow under load |
| `poll_interval_ms p(90)` | < 8500 | Poll cadence drifted beyond 7 s + 1.5 s jitter budget |
| `http_req_failed rate` | < 0.01 | Investigate: `419` = CSRF token rotation, `401` = bad/expired API token, `403`/`404` = wrong account or `TRACKING_ORDER_ID` not `out_for_delivery` |
| `tracking_fresh_rate` | decays to 0 **without live driver** | Expected with a static seed location (stale after 10 s). With a live driver posting `/driver/location`, should stay ≈1 |
| `sync_accepted_rate` | ≈1 (200 responses) | <1 = auth/config issue, not load |

Common failure causes, in order of likelihood:
1. Seed/prep steps skipped (missing tokens or order ids) → scenarios skipped
   or `401`s.
2. Wrong `TRACKING_ORDER_ID` (order not `out_for_delivery`, wrong owner) →
   `404`/`403`, failing both the error budget and tracking thresholds.
3. Session/CSRF issues (419) → the script retries once per checkout; repeated
   419s mean the app session config differs from expectations.
4. Genuine performance regression → compare `p(95)` values against the
   thresholds table above.

## Do-not-do list

- Never run against production (guarded, but do not override the guards).
- Do not raise VUs/durations without an explicit owner decision — the bounds
  above are the approved envelope.
- Do not add these tests to PHPUnit or CI (SPEC-001 keeps load tests outside
  both; no CI environment exists in this repo).

