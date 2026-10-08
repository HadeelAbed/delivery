# 03 — Implementation (M5 Sprint 5: End-to-End Flow Tests)

Scopes executed: A (rating hop repair R1–R4), B (E2E tests). Nothing else touched.

## New files
1. **`tests/Feature/E2E/EndToEndFlowTest.php`** — 3 journeys, all through real HTTP routes (details in 06):
   - `test_full_cod_journey_from_cart_to_admin_report` (6 hops: cart→checkout→accept→ready/assign→pickup→deliver→rate→admin report)
   - `test_cancellation_journey_ends_cleanly`
   - `test_rating_hop_is_reachable_and_owner_scoped`
2. `docs/M5/sprint-5-e2e-tests/01…07` documentation set.

## Modified files (repairs only — discovered by audit, restoring SPEC-001 REQ-19)
1. **`app/Policies/OrderPolicy.php`** — added `rate(User, Order): bool` → `$user->id === $order->customer_id`.
   - Why: `RatingController@index` called `Gate::authorize('rate', $order)`; the `rate` ability existed nowhere (no `Gate::define('rate')`, no policy method) → every rating form request was **403**, making the entire spec'd rating flow unreachable. The ownership rule mirrors the existing `view()` method — no new business rule.
2. **`app/Http/Controllers/Customer/RatingController.php`** — two line-level fixes in `store()`:
   - `$validated->all()` → `$validated` (`$request->validate()` returns an array; calling `->all()` caused **500: Call to a member function all() on array` on every rating POST).
   - Removed phantom `->whereNull('deleted_at')` from the unique rule — `ratings` has no `deleted_at` column (migration 0001_01_01_000012); the unique constraint already exists at DB level (`unique('order_id')`) and the rule still scopes `customer_id`.
   - Pint then normalized pre-existing style in this file (PLAN-001 lint convention) — behavior unchanged.
3. **`resources/views/customer/rating-create.blade.php`** — `str_replace('_', ' ', $order->status)` → `$order->status->value` (enum passed to `str_replace` → **500**, same latent-bug class as Sprint 4's admin badge; was masked because the 403 gate prevented the view from ever rendering).

## Deleted
- `tests/Feature/E2E/RatingProbeTest.php` — temporary audit probe (documented in 01-analysis), removed after repairs verified.

## Not changed (deliberate)
- No routes added/changed; no migrations; no service-layer changes (`RatingService`, `OrderService`, `PaymentService`, `DriverStatsService`, `AdminAnalyticsService` untouched); no view changes beyond the one enum line; nothing from Sprints 3/4 modified; the 5 known sqlite-isolation failures untouched.

## Verification flow
Probe (GET 403 → 500 → 200, POST 500 → 302) documented the repair path; final state: rating GET 200, POST 302 with row created, duplicate rejected, stranger 403.
