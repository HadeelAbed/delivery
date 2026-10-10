# Pre-Release Readiness Audit — Gaza Delivery Marketplace

- **Audit commit:** `686cd462d2891cad4092c472735574e74ef012cd` (this re-verification + triage pass)
- **Previous audit commit:** `a2ba9bf52e1de65e021cef97fe7cf996b751a15d`
- **Audit date:** 2026-10-10
- **Scope rule:** hosting-independent re-verification only. No deployment, no hosting selection, no software installed (k6 not installed), no live credentials configured, no secrets generated or printed. No Git history rewritten (the three additive commits since the prior audit are additive). This pass's changes are reflected here in §9 and the corrected B5; deletion of the untracked strays is an owner decision and is not performed.

> Status labels: **Implemented** · **Automated-test verified** · **Live integration verified** · **Pending** · **Deferred**.

---

## 1. Executive summary / readiness verdict

**Verdict: NOT YET RELEASE-READY — blocked by two required live integrations (Web Push VAPID, Google Maps keys), one required production queue worker, and one unmeasured performance gate (k6).**

The application core is functionally complete and fully green under automated testing: **156 tests / 596 assertions, 0 failures** (11.6s). Customer, merchant, driver, admin, order lifecycle, offline sync (with the ownership security fix), duplicate-checkout idempotency protection, payments, ratings, deactivation + anonymization, localization, and demo seeders are all implemented and covered.

What is **not** ready is launch configuration and evidence:

1. **Web Push** and **Google Maps** are implemented with graceful fallback and tested, but their credentials are empty, so their live behavior is **unverified**.
2. **Production needs a queue worker.** `AssignOrderJob` is the only queued job and `.env.example` sets `QUEUE_CONNECTION=database`; without a running worker the nearest-online-driver assignment (fired when a merchant marks an order ready) will not execute in production.
3. **k6 is not installed** and no load test has been executed, so SPEC-001 exit criterion 4 (performance evidence) is unmet. No performance target can be claimed.
4. **Repo hygiene:** untracked debug artifacts are not covered by `.gitignore`.

None of the above are application-code defects. They are credential, infrastructure, and evidence gaps.


---

## 2. Verified repository state

| Item | Value | Evidence |
|---|---|---|
| Branch | `master` | `git rev-parse --abbrev-ref HEAD` |
| Local HEAD | `686cd462d2891cad4092c472735574e74ef012cd` | `git rev-parse HEAD` |
| `origin/master` | `686cd462d2891cad4092c472735574e74ef012cd` | `git rev-parse origin/master` (after `fetch`); `git ls-remote origin refs/heads/master` confirms |
| HEAD == remote | **Yes** | equal hashes; `git rev-list --left-right --count master...origin/master` → `0 0` |
| Working tree | **Clean of tracked changes** | `git status --short` shows only `??` untracked |
| Uncommitted security fixes | **None** | no staged/modified tracked files |



**Untracked artifacts (pre-existing, preserved):** the **7 top-level `git status --short` `??` entries** (2 directory entries = `.opencode/` + `storage/`; 5 file entries); **NOT** the total recursive file count. Recursive untracked inventory (scope: repo root; exclusions: automation tooling `node_modules` trees + this audit's 2 capture temp files, removed) = **19 untracked files**, none staged or modified by this audit. Top-level entries: `.opencode/`, `dbg_synservice.ps1`, `dbg_synservice2.ps1`, `fix_synservice.ps1`, `fix_synservice2.ps1`, `inspect_order.php`, `storage/`.

**Recent commit chain (verified):**

| Commit | Message |
|---|---|
| `686cd46` | docs: refresh pre-release readiness audit after idempotency fix |
| `88e1e22` | style: fix blank-line separation in checkout idempotency test |
| `b86b1ca` | fix: prevent duplicate checkout submissions |
| `8be75c8` | fix: make driver assignment atomic and idempotent |
| `b4f3d4c` | docs: refresh requirements audit commit reference |
| `755eafd` | docs: document queue worker deployment requirement and hosting options |
| `7f197f6` | test: cover async database-queue assignment path |
| `cb42b6b` | docs: add pre-release readiness audit |
| `a2ba9bf` | docs: add project documentation and roles/permissions audit |
| `1490268` | fix: enforce driver ownership on offline order sync |
| `596239d` | test: add k6 local load-test suite for req-17 |
| `173c2c1` | feat: add google maps tracking ui with routing eta and graceful fallback |

---

## 3. Verified completed work

| Area | Status | Evidence |
|---|---|---|
| Full test suite | **Automated-test verified** | `php artisan test` → 156 passed (596 assertions), 0 failures (11.6s) |
| Offline-sync ownership security fix | **Automated-test verified** | `SyncService::authorizeDeliveryAction` + `SyncAuthorizationTest` (9 tests) |
| Duplicate-checkout idempotency | **Automated-test verified** | `orders(customer_id, idempotency_key)` unique index + `CheckoutController` replay guard (customer-scoped lookup, re-throws when no matching order); `CheckoutIdempotencyTest` (7 tests) |
| Payment callback integrity (Jawwal/PalPay) | **Implemented + Automated-test verified** | HMAC-SHA256 `verifyCallback` with `hash_equals`; `CodDriver` no-op |
| Merchant/driver approval gate | **Automated-test verified** | `RequireApproved` middleware + tests |
| RBAC + server-side authorization | **Automated-test verified** | `RoleMiddleware`, 4 policies, `RbacRouteTest` |
| Account deactivation + anonymization | **Automated-test verified** | `DeactivateController`, atomic, `AccountDeactivationTest` (13) |
| Ratings, catalog, cart/checkout, admin reports | **Automated-test verified** | per-feature suites |
| EN/AR localization | **Implemented** | `resources/lang/{en,ar}/*` |
| Demo seeders/factories | **Automated-test verified** | `DemoSeeder` + `DatabaseSeedersTest` |
| Web Push | **Implemented + Automated-test verified, Live PENDING** | `WebPushService` degraded guard; `WebPushTest` (9) |
| Google Maps (render/route/ETA) | **Implemented + Automated-test verified, Live PENDING** | `GoogleMapsProvider`, `TrackingMapPageTest`, `TrackingPrivacyTest` |
| k6 load-test suite | **Implemented (script), NOT executed** | `k6/load-test.js`, `k6/README.md` |

### Offline-sync authorization — re-verification (Phase 2)

Inspected `app/Services/SyncService.php` directly. Confirmed:

- `type === OrderStatus::Assigned->value` → **`return false`** (client cannot set `assigned`; rejected as `unauthorized`). Assignment stays with `AssignOrderJob`, which does not route through sync.
- Delivery-lifecycle transitions (`out_for_delivery`, `delivered`, `failed`) require **`$user->role === UserRole::Driver`** AND a `Delivery` row for that order with **`driver_id === $user->id`**.
- Customers, merchants, unassigned orders, and other drivers' deliveries → `['reason' => 'unauthorized']`, with **no order/delivery mutation**.
- State-machine gate runs **before** the authorization gate, preserving `invalid_transition` semantics.
- Regression coverage: `tests/Feature/Sync/SyncAuthorizationTest.php` — **9 test methods**.

**Conclusion:** approved Policy B is fully implemented and regression-tested. No unresolved sync vulnerability found.


---

## 4. Release blockers (with evidence and impact)

### B1 — Web Push not live-verified (VAPID keys empty)
- **Status:** Implemented + automated-test verified · **Live PENDING**
- **Evidence:** `.env.example` lines 43–45: `VAPID_PUBLIC_KEY=`, `VAPID_PRIVATE_KEY=`, `VAPID_SUBJECT=` are **empty**. `WebPushService` short-circuits to database-only notifications when unconfigured (verified degraded guard). `WebPushTest` (9) covers the configured + unconfigured paths.
- **Impact:** Push deliveries cannot occur in production without keys; browser subscription + delivery unverified. **Not a crash risk** — the app falls back to in-app notifications.
- **To close:** supply VAPID keypair (generate with `php artisan webpush:vapid` on a capable machine — the local XAMPP PHP lacks `gmp`), set the three env vars in the real environment, then verify an actual browser subscription receives a push.

### B2 — Google Maps not live-verified (keys empty)
- **Status:** Implemented + automated-test verified · **Live PENDING**
- **Evidence:** `.env.example` lines 50–51: `GOOGLE_MAPS_BROWSER_KEY=`, `GOOGLE_MAPS_SERVER_KEY=` are **empty**. `GoogleMapsProvider` degrades to `null` (no route/ETA) and the tracking page renders the existing text/polling fallback (verified by `TrackingMapPageTest`, 8 tests).
- **Impact:** Live map, routing, and ETA unverified; server key must remain backend-only. **Not a crash risk** — graceful fallback.
- **To close:** provide a referrer-restricted browser key + a server-only server key, then verify the tracking page renders pins, live driver marker, and ETA in a real browser.

### B3 — Production queue worker not provisioned
- **Status:** Implemented · **Infrastructure PENDING**
- **Evidence:** `app/Jobs/AssignOrderJob.php` implements `ShouldQueue` (the only queued class in `app/`). `.env.example` line 55: `QUEUE_CONNECTION=database`.
- **Impact:** In production, if no `php artisan queue:work` (or supervisor-managed worker) runs, the **driver-assignment workflow will not fire** — orders marked ready will never be assigned to the nearest online driver. This is a functional regression risk at launch, not a test failure (tests run `QUEUE_CONNECTION=sync`).
- **To close:** provision a persistent queue worker + supervisor config; add queue-worker health to monitoring; run `php artisan queue:work` (or `queue:listen` in dev) as a managed service.

### B4 — k6 load test not executed
- **Status:** Script implemented · **NOT executed / NOT measured**
- **Evidence:** `k6 version` → command not found (**k6 is not installed**). `k6/load-test.js` + `k6/README.md` exist with 3 scenarios and explicit thresholds.
- **Impact:** SPEC-001 exit criterion 4 (load/performance evidence) is unmet. **No performance target can be claimed.** The 1–3s order-propagation target is measured by the script but has never been run.
- **To close:** install k6 (requires approval), seed per README, run `k6 run k6/load-test.js` against a **safe local/staging** environment (never production), capture `--summary-export=k6-summary.json`, and record actual numbers.

### B5 — Untracked debug artifacts (untracked; `inspect_order.php` breaks whole-repo `pint --test`)
- **Status:** Hygiene · **PENDING**
- **Evidence (re-verified 2026-10-10):** `.gitignore` has **no** entries for `inspect_order.php`, `dbg_*.ps1`, `fix_*.ps1`. These remain untracked (`??`). Application code is clean — `vendor/bin/pint --test app tests config database routes resources` → **192 files PASS (exit 0)**. The **only** whole-repo failure is the untracked debug file: `vendor/bin/pint --test` → **196 files, 1 style issue, in `inspect_order.php`** (`single_quote, fully_qualified_strict_types, concat_space, no_closing_tag, single_line_after_import`). There is **no CI workflow** in the repo and **no `pint.json`**, so Pint uses its built-in preset over the whole tree with no path scoping.
- **Correction to prior advice:** **Pint does not honor `.gitignore`.** Proven empirically — with `inspect_order.php` present in `.gitignore`, `pint --test` **still** flagged it. Therefore *adding ignore rules will NOT clear the whole-repo Pint gate.* The gate can only be satisfied by **deleting** the file (or excluding it via a `pint.json` `notPath`/finder rule), not by ignoring it.
- **Impact:** Low. The strays are untracked and do not ship. But `inspect_order.php` makes a whole-repo `pint --test` fail if anyone wires that command into CI, and it is a stale script (see §9).
- **To close:** delete `inspect_order.php` (recommended — it is a broken one-off, see §9). For the `.ps1` debug scripts, delete them; they are not in Pint's PHP-only scope, so ignoring them would silence only `git status`, not any gate.

---

## 5. Safe remediation steps (priority order)

Ordered by launch impact. Steps B1/B2/B4 require explicit owner approval (credentials/install) per the audit rules; none are performed here.

1. **B3 — Provision a production queue worker (highest functional risk).**
   - Add a supervisor config (or platform worker) running `php artisan queue:work --queue=default --tries=3` against the `database` queue.
   - Add a queue-depth / worker-heartbeat check to monitoring.
   - Local/dev equivalent for verification: `php artisan queue:work` (or `QUEUE_CONNECTION=sync` in `.env.testing`, which tests already use).
   - **Live check:** mark a merchant order `ready_for_pickup` and confirm a `deliveries` row + `assigned` status appears with the worker running (and does **not** appear with it stopped).

2. **B1 — Configure Web Push VAPID (needs credentials).**
   - Generate a keypair on a capable PHP (`php artisan webpush:vapid`; the local XAMPP build lacks `gmp`). Never commit the values.
   - Set `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT` in the real environment only.
   - **Live check:** subscribe from a real browser, trigger an `out_for_delivery` event, confirm the push arrives. Until then the feature remains **Automated-test verified, Live PENDING**.

3. **B2 — Configure Google Maps (needs credentials).**
   - Set `GOOGLE_MAPS_BROWSER_KEY` (referrer-restricted) and `GOOGLE_MAPS_SERVER_KEY` (server-only, IP-restricted) in the real environment only.
   - **Live check:** open a tracking page for an in-flight order; confirm merchant/destination pins, the live driver marker, and distance/ETA render. Confirm the server key never appears in HTML/JS/API/logs. Until then: **Automated-test verified, Live PENDING**.

4. **B4 — Execute the k6 load test (needs approval + k6 install).**
   - Install k6 locally (system-wide install requires approval), seed per `k6/README.md`, then run against a **safe local/staging** environment — never production.
   - Record actual output: `k6 run k6/load-test.js --summary-export=k6-summary.json`.
   - Only after a recorded run may the 1–3s propagation / 7s cadence thresholds be called **met**.

5. **B5 — Repo hygiene (small, non-code).**
   - **Delete** `inspect_order.php` — it is the sole whole-repo `pint --test` failure, and Pint ignores `.gitignore` (proven), so ignoring will not clear the gate. Deleting the already-applied, obsolete `fix_synservice*.ps1` / `dbg_synservice*.ps1` (see §9) is also recommended.
   - Alternatively, add a `pint.json` that scopes Pint to `app`, `tests`, `config`, `database`, `routes`, `resources` (or a `notPath` exclude for the strays) so whole-repo `pint --test` passes without touching the files. `.gitignore` alone is **insufficient**.
   - Untracked files are preserved (not deleted) by this audit; deletion is an owner decision.

---

## 6. Explicit status distinctions

| Layer | Meaning used in this audit |
|---|---|
| **Implemented** | Code exists in the repository (route/service/policy/config). |
| **Automated-test verified** | Covered by PHPUnit tests that pass in CI (`php artisan test`). |
| **Live integration verified** | Confirmed against a real external service with valid credentials in a real client — **none claimed in this audit.** |
| **Pending** | Implemented/needed but awaiting credentials, infrastructure, or an executed run (B1–B5). |
| **Deferred** | Explicitly out of V1 scope (analytics C3–C7, the nine extended-scope items). |

Current tally: **156 tests / 596 assertions / 0 failures = Automated-test verified. Live-verified count = 0.** No integration is claimed production-ready on the strength of automated tests alone.

---

## 7. Final release checklist

8. **B1 live push**: no live browser push delivery exercised.
9. **B2 live map**: no live keys/render/polling/ETA exercised.
10. **B4 performance**: no k6 run → 1–3s propagation / 7s cadence thresholds **unmeasured**.

**Go/No-go:** do not launch until **B3** is closed (functional risk), **B1/B2** are either live-verified or consciously accepted as fallback-only, **B4** is executed, and **B5** (debug-raw tracks resolved). No external credentials, k6 installation, hosting selection, or deployment was performed by this pass.
7. **B3 live assignment**: no live worker → live assignment path not exercised.
- [ ] **B3** Production queue worker provisioned + monitored; assignment verified live. *(blocker — do first)*
- [ ] **B1** VAPID keys set in the real environment (not committed); browser push delivery verified live.
- [ ] **B2** Google Maps browser + server keys set (server key backend-only); map/route/ETA verified live.
- [ ] **B4** k6 installed; load test executed against local/staging; `k6-summary.json` recorded; thresholds evaluated from real numbers.
- [ ] **B5** Repo hygiene: **delete** `inspect_order.php` (sole whole-repo `pint --test` failure) **or** add a `pint.json` scoping Pint to app paths — note `.gitignore` does NOT clear the Pint gate (proven). Triage of all strays in §9.
- [ ] HTTPS enforced; secrets present in the environment only (no values in repo); DB migrations run on deploy; backups + queue + scheduler + monitoring in place (see `docs/deployment/DEPLOYMENT_AND_OPERATIONS.md`).
- [ ] Post-deployment smoke tests pass (register → checkout → accept → ready → assign → out_for_delivery → delivered; a `/api/sync` batch; a payment callback).
- [ ] HEAD == `origin/master`; only intended files committed; no untracked strays staged.

**Go/No-go:** do not launch until B3 is closed (functional risk) and B1/B2 are either live-verified or consciously accepted as fallback-only. B4 is the outstanding SPEC-001 exit criterion; B5 is hygiene.

---

## 8. Re-verification pass (2026-10-10) — hosting-independent

Added after the two additive commits `b86b1ca` (duplicate-checkout idempotency) and `88e1e22` (its style fix). This pass performed only checks that require **no** deployment, hosting selection, k6 install, or live credentials.

**Verified (fresh evidence):**
- `git rev-parse HEAD` == `origin/master` == `686cd46`; `git ls-remote origin refs/heads/master` and `git rev-list --left-right --count master...origin/master` (`0 0`) confirm the shared branch is in sync. Working tree has no tracked modifications; only the pre-existing untracked strays remain (`git status --short`).
- `php artisan test` → **156 passed / 596 assertions / 0 failures** (11.6s), up from 144/551.
- `git diff --cached --check` clean for the feature commit.

**Idempotency handler review (targeted, passed):** `CheckoutController::place` catches `UniqueConstraintViolationException` but does **not** blindly convert it to success. It re-queries `Order::where('customer_id', Auth::id())->where('idempotency_key', $idempotencyKey)->first()`; if no such persisted order exists it **re-throws** the exception. The `orders` table's only live unique index on this path is `(customer_id, idempotency_key)` (`payments.reference_id` is `NULL` for COD/pending, so multiple NULLs are permitted and it cannot fire). The failed insert is rolled back by the surrounding `DB::transaction`, so the lookup only ever returns the previously committed order — scoped to the authenticated customer. Verified by `CheckoutIdempotencyTest` (7 tests: repeat, same-order return, fresh-token new order, stale-form-after-success, validation-retry, cross-customer isolation, DB-constraint proof).

**Pint gate (hosting-independent hygiene):** `vendor/bin/pint --test` over the whole repo still reports the pre-existing `inspect_order.php` issues (B5, untracked stray — out of scope to modify). The new `CheckoutIdempotencyTest.php` initially failed `class_attributes_separation` (a double blank line); this was corrected in `88e1e22` and now **passes** `pint --test`. No committed tracked file fails Pint except via the untracked stray.

**Still pending (unchanged, require out-of-scope resources):** B1 (VAPID), B2 (Google Maps keys), B3 (queue worker / hosting), B4 (k6 install + run). This pass did **not** deploy, select a host, install k6, or configure any live credentials.
**9. Debug-file triage + final prioritized checklist**

**9.1 Debug-file triage** (contents read only; no file deleted, modified, staged, or ignored)

| File | Role | Needed? | Appearance | Safest handling |
|---|---|---|---|---|
| `inspect_order.php` | Standalone script that vendor/autoloads and dumps the first `Order`, its `Payment`, `Delivery`, sample payments. | **No** | Temp dev-debug tool. **Broken/stale**: `payments` has **no `amount` column**, yet prints `$payment->amount` (and `$order->items_total` *does* exist, `$payment->method/status` do too). Not used by the app or tests. | **Delete.** |
| `dbg_synservice.ps1` | Read-only PowerShell inspector: CRLF/bytes, locates `SyncOutbox::create`. | **No** | Temp debug tool. | **Delete**. |
| `dbg_synservice2.ps1` | Read-only PowerShell inspector: hexdump around `SyncOutbox::create`. | **No** | Temp debug tool. | **Delete**. |
| `fix_synservice.ps1` | One-shot script that mutated `SyncService.php` (already applied; file now clean at line 79). | **No** | Temp, obsolete/mutating. | **Delete**. |
| `fix_synservice2.ps1` | Same as above (byte-level pattern). | **No** | Temp, obsolete/mutating. | **Delete**. |
| `.opencode/` | Editor tool runtime metadata/cache. | **No** | Tool artifact. | `.gitignore`+ (excluded). |
| `storage/` | Laravel runtime cache/storage. | **No** | App runtime dir. | `.gitignore`/gitignored by default. |

None are required by the app or test suite. All are dev/debug artifacts left in the working tree. Following audit rules, **no untracked file was deleted, modified, staged, or ignored** by this pass — deletion is an owner decision (see §9.3).

**9.2 Why `inspect_order.php` fails the whole-repo `pint --test` gate — correctly distinguished:**

- The failure is **pure code style in one untracked debug file** (`single_quote`, `fully_qualified_strict_types`, `concat_space`, `no_closing_tag`, `single_line_after_import`).
- **No tracked/application code fails Pint.** Verified: `vendor/bin/pint --test app tests config database routes resources` → **192 files PASS (exit 0)**.
- **Pint does not honor `.gitignore`.** Proven empirically: while `inspect_order.php` was in `.gitignore`, `pint --test` still flagged it. So gitignoring does **not** clear the gate — only deleting the file or scoping Pint (a `pint.json` with `notPath`/path exclusion) does.
- No CI workflow exists at the repo root and there is no `pint.json`; this is currently a **manual** whole-repo check, not an enforced CI gate.
**9.3 Owner decision** (not performed by this audit): delete or move out of the tree the **7 top-level git-status entries** — the two directory entries `.opencode/` and `storage/`, and the five file entries `dbg_synservice.ps1`, `dbg_synservice2.ps1`, `fix_synservice.ps1`, `fix_synservice2.ps1`, `inspect_order.php`. Because `inspect_order.php` trips the whole-repo Pint gate if CI ever runs `pint --test`, deletion is the recommended resolution. Note that `.gitignore` entries are insufficient on their own to fix the Pint gate (shown above). Nothing was, or will be, deleted, modified, staged, or ignored by this audit; deletion of any untracked file is an explicit owner decision, and the 19-file recursive inventory noted in §2 is *not* a directive to delete any of them.

**9.4 Prioritized finalization checklist** (no deployment performed)

*Tasks that can be completed locally with **no external credentials**, hosting, or tooling beyond the existing dev environment:*

1. **Confirm whole-`pint --test` is green** on the cleaned tree (`vendor/bin/pint --test`), consistent with the 192-tracked-file pass already verified. (Item 3.)
2. **Confirm the full PHPUnit suite stays green** on the cleaned tree (`php artisan test` → 156 passed / 596 assertions / 0 failures), re-running only the checkout, order-flow, and E2E suites that touch the idempotency feature.

*Tasks requiring secrets, external services, or a hosting decision:*

3. **B1 — Web Push VAPID** (VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY, VAPID_SUBJECT): owner supplies keypair via `php artisan webpush:vapid`; never commit. **Live PENDING** — no live push verified.
4. **B2 — Google Maps** (GOOGLE_MAPS_BROWSER_KEY, GOOGLE_MAPS_SERVER_KEY): owner supplies referrer-restricted browser key + server-only key; server key must stay backend-only. **Live PENDING** — no live map/route/ETA verified.
5. **B3 — Production queue worker**: owner provisions a persistent worker (`php artisan queue:work --queue=default --tries=3`) + supervisor; `AssignOrderJob` is `ShouldQueue` with `.env.example` setting `QUEUE_CONNECTION=database`. **Functional risk** if unrun at launch.
6. **B4 — k6 load test** (requires owner approval to install): seed per `k6/README`, run `k6 run k6/load-test.js` against a **safe local/staging** env (never production), record `--summary-export`, evaluate 1–3s propagation / 7s cadence thresholds from real numbers. **NOT executed here** (k6 not installed).
