# 06 — Tests (M5 Sprint 5)

## New: `tests/Feature/E2E/EndToEndFlowTest.php` — 3 tests, 44 assertions, all pass
| Test | Journey / coverage |
|---|---|
| `test_full_cod_journey_from_cart_to_admin_report` | Complete multi-role path via HTTP only: cart.add → checkout.place (totals 25+15=40, payment `pending_cod`, NewOrder→merchant) → merchant accept (merchant_accepted, OrderAccepted→customer) → ready (auto `AssignOrderJob` sync → assigned, delivery row for seeded online driver) → pickup (out_for_delivery, OutForDelivery→customer) → deliver (delivered + timestamps, OrderDelivered→customer+merchant) → rating POST (ratings row 5/4) → `DriverStatsService` (completed=1, earnings=config, rating=4.0) → admin sales report (200, `40.00` shown, order listed). |
| `test_cancellation_journey_ends_cleanly` | checkout → merchant reject → `cancelled` + `reject_reason`, no `deliveries` row, OrderCancelled→customer+merchant, order **excluded** from admin sales report. |
| `test_rating_hop_is_reachable_and_owner_scoped` | Regression guard for the three repairs: owner GET form 200 (was 403), owner POST creates row (was 500), duplicate POST still only 1 row (one-per-order rule), stranger GET 403. |

## Changed tests: none
(No existing test was modified this sprint. The temporary `RatingProbeTest` used during audit was deleted.)

## Regression runs
- `EndToEndFlowTest + OrderFlowTest + NotificationFanoutTest + TrackingPrivacyTest`: **15 passed (70 assertions)**.
- Sprint 3/4 suites re-verified within full run (driver, admin-analytics all green).

## Full suite: `Tests: 5 failed, 73 passed (239 assertions)` (was 70 passed before this sprint)

### Pre-existing failures — unchanged 5
| Test | Cause |
|---|---|
| `OfflineSyncTest` ×3 | sqlite `:memory:` drops `sync_outboxes` |
| `SanctumTokenTest` ×2 | sqlite `:memory:` missing `personal_access_tokens` |

### New failures from this sprint: **NONE**
### Defects found & repaired this sprint (pre-existing, were invisible because no test covered the rating flow): rating 403 (missing `rate` policy ability), rating POST 500 (`$validated->all()`), rating view enum→`str_replace` 500, phantom `deleted_at` rule.

## Style
`pint --test` clean on all touched files (`OrderPolicy`, `RatingController`, `EndToEndFlowTest`). Pre-existing style issues in untouched files (e.g. other Customer controllers) remain as before.
