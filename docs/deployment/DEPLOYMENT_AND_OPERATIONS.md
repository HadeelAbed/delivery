# Deployment & Operations

Audit commit: `1490268`. **No hosting, credentials, or infrastructure details are known or asserted here.** Everything that depends on an external environment is marked **[PENDING]**.

---

## 1. Runtime prerequisites (to confirm per environment) — [PENDING]

- PHP + Composer; MySQL (or the DB used) reachable; `php artisan migrate --force` at deploy.
- Node/npm only if building front-end assets (Blade + vanilla JS; no build step required for core pages).
- Web server (Apache/Nginx) with **HTTPS enforced** (REQ-18); HSTS recommended.
- k6 **not** installed here — needed only for load verification, not production runtime.

## 2. Environment configuration — [PENDING]

- Copy `.env.example` → `.env`; set `APP_KEY` (`php artisan key:generate`), `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://<domain>`.
- **Secrets management:** supply via environment/secret manager only. Never commit secrets. Names (never values): `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT`; `GOOGLE_MAPS_BROWSER_KEY`, `GOOGLE_MAPS_SERVER_KEY`; payment gateway merchant credentials + signing secrets; DB + mail credentials; `ADMIN_PASSWORD` for the seeder-created admin.
- Only `.env.example` is tracked; `.env` must not be.

## 3. Database

- Migrations are idempotent per Laravel; run `php artisan migrate --force`. Review migrations before applying to production.
- **Backups:** configure scheduled DB backups in the target infrastructure. [PENDING] — no backup policy is defined in code.
- **Rollback:** `php artisan migrate:rollback` per migration batch; payments/sync are behind config flags (`PAYMENTS_DRIVER`, removing the `/api/sync` route disables offline path) — see [PLAN-001](../ai-sdlc/plans/PLAN-001.md) rollback notes.

## 4. Queue workers & scheduler — [PENDING]

- Some behaviour relies on queue processing (`AssignOrderJob`, notification fanout). In tests `QUEUE_CONNECTION=sync` runs jobs inline; **production needs a worker** (`php artisan queue:work`) under a supervisor. Confirm which queues are used in the target environment.

### Why a worker is required
- `config/queue.php` defaults to the **`database`** queue driver (`env('QUEUE_CONNECTION','database')`), and `.env.example` sets `QUEUE_CONNECTION=database` for production.
- `AssignOrderJob` (`implements ShouldQueue`) is the **only** queued job. It is dispatched when a merchant marks an order ready (`Merchant/OrderController@markReady`) and is responsible for **assigning the nearest active+online driver**, creating the `Delivery`, and setting the order to `assigned`.
- With the `database` driver, a dispatched job is written to the `jobs` table and only runs when a worker consumes it. **If no worker runs in production, every `ready_for_pickup` order stays unassigned forever.** The test suite does not expose this because `phpunit.xml` forces `QUEUE_CONNECTION=sync`.
- Required tables (`jobs`, `job_batches`, `failed_jobs`) already exist via the queue migration.

### Hosting strategy options (hosting provider NOT yet decided)

| Strategy | How it works | Trade-offs |
|---|---|---|
| **A. Persistent worker** | Long-running `php artisan queue:work --queue=default --tries=3 --max-time=3600` kept alive by Supervisor (`autorestart=true`) or the platform's native worker service. Run `php artisan queue:restart` on every deploy. | Real-time assignment (within seconds). Requires the host to support persistent background processes. |
| **B. Cron-based worker** | A per-minute cron entry that drains then exits: `* * * * * php artisan queue:work --stop-when-empty --max-time=50 --tries=3`. | Assignment delayed up to ~1 minute. Suitable for shared hosting / cPanel that only supports cron. |
| **C. Synchronous execution** | Set `QUEUE_CONNECTION=sync` in the production `.env`; `AssignOrderJob` then runs inline during the merchant's "mark ready" request. | No worker or cron needed at all; assignment works. Trade-off: the merchant request blocks briefly while the nearest-driver lookup runs — fine at small scale, not ideal under high order volume. **Not chosen** (would require changing production configuration — pending owner decision). |

- **Status: hosting strategy undecided.** The repository contains **no deployment artifacts** (no `Procfile`, `Dockerfile`, `docker-compose.yml`, `fly.toml`, `railway.json`, `render.yaml`, `app.json`, `vapor.yml`, or CI workflow), so host capabilities are **unknown** and must not be assumed. Branch A/B/C selection is deferred until a host is chosen.

### Staging verification required before launch
- In a staging environment configured with the chosen strategy, drive one order to "ready" through the real merchant UI and **assert a `deliveries` row is created without the request doing the work** (Branch A/B) or synchronously (Branch C).
- Confirm the `jobs` table drains and `failed_jobs` stays empty; monitor `php artisan queue:failed`.
- Record the observed assignment latency for the chosen strategy.

### Automated-test vs live verification
- **Automated-test verified:** assignment *logic* is covered by `tests/Unit/AssignmentTest.php` and `tests/Feature/DeliveryFlowTest.php`; the async `database`-queue dispatch path is covered by `tests/Feature/AssignmentAsyncQueueTest.php`. These run with `QUEUE_CONNECTION=sync` (inline) and the async test asserts the job is enqueued and processed via `queue:work --stop-when-empty`.
- **Live production verified: NO.** No worker has been provisioned and no staging assignment has been executed against a chosen hosting strategy. Assignment in production remains **PENDING** until the host is selected and the staging check above passes.
- `after_commit` race investigated: **not reachable** — `OrderService::transition()`/`markReady()` perform a plain `$order->save()` with no wrapping `DB::transaction`, and no middleware wraps requests in a transaction, so the order is committed as `ready_for_pickup` **before** the job is dispatched. No `after_commit` change is required for the current code.
- Scheduler: add `* * * * * php artisan schedule:run` if/when scheduled tasks are defined. [PENDING] — confirm scheduled tasks.

## 5. Logging, monitoring, error handling

- Laravel logging via `LOG_CHANNEL` in `.env` (configure per environment [PENDING]).
- Error handling: production `APP_DEBUG=false`; unhandled exceptions return generic responses (no PII). Payment/webhook paths log without card data or keys.
- Monitoring/alerting: [PENDING] — define in target infrastructure (no monitoring code in repo).

## 6. Post-deployment smoke tests (manual) — [PENDING]

Suggested checks after deploy (not automated):
1. Admin login with seeder credentials (rotate password after first login).
2. Customer registers → merchant/driver approval → browse → checkout (COD) → accept → ready (assignment) → driver delivers.
3. Payment callback with a valid signature marks payment paid; invalid signature is rejected.
4. `/api/sync` as a non-owner driver returns `unauthorized` (see [SECURITY_OVERVIEW](../security/SECURITY_OVERVIEW.md)).
5. Tracking page renders (with/without Maps keys).

## 7. Go-live blockers

See [REQUIREMENTS_STATUS](../requirements/REQUIREMENTS_STATUS.md) and [INTEGRATIONS_STATUS](../integrations/INTEGRATIONS_STATUS.md). Key ones: production payment credentials, optional VAPID/Maps keys, and load-test execution for SPEC-001 exit criterion 4.
