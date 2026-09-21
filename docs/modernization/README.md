# Vue 3 and Redis modernization

Status: **implemented and locally verified on 2026-09-21**. Prepared from
Obsidian `c8c32c9` plus the current working tree, and TAGCSOFT `e67cbaa2`.
The user approved the frontend architecture, the specified Redis workloads plus
isolated local/test provisioning, and the scoped security fixes. Production
deployment/provisioning remains excluded. The prior working-tree baseline is
preserved in commit `2045762` on `codex/vue3-redis-modernization`.

## Approved decisions

1. Vue 3, Pinia 3, Vue Router 4 and Vite 8.3, with Laravel-owned URLs and
   authentication. Start with Vue components inside Blade pages; introduce a
   bounded authenticated workspace only after its pages and contracts are tested.
   Retain Blade for the HTML envelope and email templates.
2. Redis only for public firmware/config **version-number** lookups and
   bounded, anonymous request counters in the first release, with an isolated
   local/test Redis container to validate those workloads. Keep sessions,
   queues, authentication throttles, scheduler locks and live control data on
   their existing backends. Production Redis provisioning remains a separate approval.
3. A separate, narrowly scoped security prerequisite increment before
   device-detail, admin and verification migration: enforce the documented
   owner/admin policy for browser device routes, restrict admin management,
   validate verification links, and remove sensitive serialization/logging.
   External firmware/mobile authorization redesign remains a separate ticket.

The approval gate comes from this ticket's acceptance criteria 2 and 6, and its
exclusion of backend-rule changes and Redis provisioning without separate approval.
Approving the frontend architecture alone does not approve production deployment
or the separately listed security changes.

## Implementation and release records

- [Complete frontend inventory and screen dispositions](frontend-inventory.md)
- [Target architecture and dependency decisions](architecture.md)
- [Redis boundaries, expiration, invalidation and failure policy](redis-boundary.md)
- [Reviewable increments, tests, rollout and rollback](delivery-plan.md)
- [88 original and 89 current registered routes and middleware](route-snapshot.json)
- [All 74 original resource/public files plus new/removed paths](file-inventory.json)

Fourteen reachable pages are migrated to Vue 3, with a reproducible container
build, server authorization/redaction corrections and approved optional Redis
workloads. Delta is deferred because its source spreadsheet is absent; email
templates remain Blade. All 32 original Blade templates and 74 original
resource/public paths are accounted for, including retained rollback assets.

Original migration checks passed: 131 PHP tests (9,104 assertions), 78 Vue tests,
12 desktop/mobile Playwright tests with strict axe checks, lint/format checks,
PHP syntax, legacy helper/cron checks, production Docker build and real Redis
integration. GitHub CI is configured but was not executed on GitHub.

The subsequent device-page and Developer Workspace update is locally verified:
135 PHP tests (9,176 assertions), Vue component/regression checks, 12 desktop/mobile
browser tests with strict axe checks, lint/format checks, and the pinned production
Docker build passed. Device sections preserve raw timestamps and confirmed control
state; the workspace separates release history from on-demand uploads. The canonical
URL and `DEVELOPER_EMAIL` policy are documented in the [access model](../access-model.md).

The verified local app runs at `http://localhost:8080` with ignored local
frontend/workspace/Redis flags enabled. Committed flags default off. Production
deployment and Redis provisioning still require separate approval; the
[delivery record](delivery-plan.md) documents results, exceptions and rollback.
