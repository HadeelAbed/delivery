# Gaza Delivery Marketplace — Documentation Index

- **Audit date:** 2026-10-09
- **Documented commit:** `1490268` (`fix: enforce driver ownership on offline order sync`)
- **Repository status:** `master`, working tree clean (only pre-existing untracked dev/scratch files present)
- **Readiness:** V1 feature-complete; two integrations (Web Push, Google Maps) implemented and automated-test verified but **not live-verified** (no credentials supplied); k6 load tests delivered but **not executed**.

## Project purpose

A Gaza-focused food/goods delivery marketplace connecting **customers**, **merchants**, and **drivers**, with an **admin** approval/oversight layer. Built on Laravel + Blade with a Sanctum-backed REST surface for future mobile clients, bilingual Arabic/English UI, COD + mock digital-payment gateways, offline-tolerant driver sync, and in-app + Web Push notifications.

## Recommended reading order

1. [`project/PROJECT_OVERVIEW.md`](project/PROJECT_OVERVIEW.md) — scope, users, V1 boundaries, readiness
2. [`architecture/ARCHITECTURE.md`](architecture/ARCHITECTURE.md) — layers, request flow, data model
3. [`security/ROLES_AND_PERMISSIONS.md`](security/ROLES_AND_PERMISSIONS.md) — role/permission matrix (server-side enforcement)
4. [`security/SECURITY_OVERVIEW.md`](security/SECURITY_OVERVIEW.md) — authn/authz, privacy, payments, findings
5. [`features/FEATURES_AND_WORKFLOWS.md`](features/FEATURES_AND_WORKFLOWS.md) — end-to-end workflows
6. [`requirements/REQUIREMENTS_STATUS.md`](requirements/REQUIREMENTS_STATUS.md) — SPEC-001 traceability
7. [`integrations/INTEGRATIONS_STATUS.md`](integrations/INTEGRATIONS_STATUS.md) — push, maps, payments, channels
8. [`testing/TESTING_AND_PERFORMANCE.md`](testing/TESTING_AND_PERFORMANCE.md) — how to run tests + k6
9. [`deployment/DEPLOYMENT_AND_OPERATIONS.md`](deployment/DEPLOYMENT_AND_OPERATIONS.md) — go-live runbook
10. [`maintenance/CHANGELOG_AND_DECISIONS.md`](maintenance/CHANGELOG_AND_DECISIONS.md) — milestones, decisions, deferrals

## Status label legend

| Label | Meaning |
|---|---|
| **Implemented** | Code exists in the repository |
| **Automated-test verified** | Covered by a passing PHPUnit test |
| **Manually verified** | Exercised in a browser/curl with representative data |
| **Live integration verified** | Verified against the real external service with valid credentials |
| **Pending** | Not yet verified / requires credentials or infrastructure |
| **Deferred** | Explicitly postponed, out of V1 scope |

> No integration in this project is **Live integration verified** at the documented commit. See `integrations/INTEGRATIONS_STATUS.md`.

## Existing documents (source of truth for history)

- [`ai-sdlc/specs/SPEC-001.md`](ai-sdlc/specs/SPEC-001.md) — requirements / user stories / acceptance criteria
- [`ai-sdlc/plans/PLAN-001.md`](ai-sdlc/plans/PLAN-001.md) — milestone plan and build notes
- [`M5/sprint-*`](M5/) — per-sprint analysis/implementation records (historical; some contain now-superseded statements)
