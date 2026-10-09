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
