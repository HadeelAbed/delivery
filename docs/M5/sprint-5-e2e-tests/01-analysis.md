# M5 Sprint 5 — End-to-End Flow Tests: Analysis (audit)

Date: 2026-10-07. Read-only audit + one throwaway probe test (no app code changed).

## 1. Next step identified from the project plan
PLAN-001 "Remaining M4 Tasks":
- [x] admin views ×3, payment callback verification, sync idempotency
- [x] **Admin dashboard UI refinements — delivered by M5 Sprint 4** (C1 dashboard + A3/A6 nav)
- [ ] **Payment webhook setup for production** — the in-repo webhook is implemented (`POST /api/payments/callback/{gateway}` → `PaymentCallbackController` → `PaymentService::handleCallback` with HMAC mock-driver verification, covered by `PaymentWebhookTest`); only *production* gateway credentials/sandbox remain future work per C-07 (mock gateways, no production credentials) → stays deferred; cannot be exercised against live gateways in-repo.
- [ ] **End-to-end flow tests — THE next executable planned step** → this sprint's scope.

Also SPEC-001 exit criterion 1 ("All §2 tests green") is a standing goal; the 5 known sqlite-isolation failures remain tracked separately (owner instructed not to mix them into new work).

## 2. Existing test coverage (audit of tests/)
63 test methods across 19 files cover each **slice** in isolation:
- Registration/approval, catalog CRUD, checkout totals (`OrderFlowTest`), single transitions (`OrderHandlingTest`), assignment (`AssignmentTest`), pickup/deliver/reject (`DeliveryFlowTest`), per-transition notifications (`NotificationFanoutTest`), COD + callback (`CodTest`, `GatewayCallbackTest`), tracking privacy, RBAC, admin (Sprint 4), driver stats (Sprint 3).
- **Gap**: no single test drives the complete multi-role journey through real HTTP routes hop-to-hop (cart → checkout → accept → ready → assignment → pickup → deliver → rating → admin report). This is exactly PLAN-001's "End-to-end flow tests".

## 3. Integration facts verified
- `Merchant\OrderController@markReady` calls `AssignOrderJob::dispatch($order)`; `phpunit.xml` sets `QUEUE_CONNECTION=sync` → assignment runs inline during the HTTP request. No manual dispatch needed in E2E.
- Notification fanout (`SendOrderNotifications` on `OrderStatusChanged`): pending→merchant NewOrder; merchant_accepted→customer; ready_for_pickup→driver (only if delivery row exists); out_for_delivery→customer; delivered→customer+merchant; cancelled→customer+merchant.
- Checkout flow: `customer.cart.add` (session cart) → `customer.checkout.place` (clears cart, creates order + payment `pending_cod` for cod).
- Rating route exists: `customer.orders.rate` (GET) / `customer.orders.rate.submit` (POST), service `RatingService::canRate/submitRating` (spec'd REQ-19, US rating C-06 scale 1–5).

## 4. Audit-discovered pre-existing defects (verified by probe test)
`tests/Feature/E2E/RatingProbeTest.php` (temporary, will be deleted) against a delivered order:
1. **GET rate form → 403.** `RatingController@index` calls `Gate::authorize('rate', $order)` but no `rate` ability exists anywhere: `OrderPolicy` has only `manage`/`view`, no `Gate::define('rate')`. Policy registered for Order but method missing → deny. **The entire rating flow (REQ-19) is unreachable in the current build.** No test ever covered it (rating has zero tests).
2. **POST rating → 500:** `Call to a member function all() on array` — `RatingController::store` does `$validated = $request->validate([...])` (returns **array**) then calls `$validated->all()`.
3. Latent: the same rule chain contains `->whereNull('deleted_at')` on the `ratings` table which **has no `deleted_at` column** (migration 0001_01_01_000012) — flagged for verification once (2) is fixed.

These are defects against existing SPEC-001 **REQ-19** ("Customer rating of merchant + delivery service after completion"), not new requirements. Repairing them restores spec'd behavior — required for an E2E journey that ends in rating.

## 5. What must NOT be done (per standing constraints)
- No new features/requirements, no migrations, no architecture changes, no C3–C7 analytics.
- Do not touch Sprint 3/4 deliverables except where an E2E test exercises them (read-only use).
- Keep the 5 known sqlite-isolation failures (3 OfflineSyncTest + 2 SanctumTokenTest) documented as pre-existing; do not attempt to fix them here.
- Payment webhook route: NOT created (production/M4-deferred item, mock gateways per C-07).

## 6. Risks
- E2E tests are order-dependent by nature → use `RefreshDatabase`, create data per test, assert via DB + HTTP.
- Notification assertions must match actual listener behavior (ready_for_pickup notifies driver only after assignment exists — since event fires inside `markReady` before job runs, driver notification may fire only on later transitions; E2E asserts what the code actually does).
- Fixing the rating controller must not alter `RatingService` contract (its internal validation stays).
