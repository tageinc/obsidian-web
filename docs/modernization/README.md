# Vue 3 and Redis modernization: approval package

Status: **approved for implementation on 2026-09-21**. Prepared 2026-09-21 from
Obsidian `c8c32c9` plus the current working tree, and TAGCSOFT `e67cbaa2`.
The user approved the frontend architecture, the specified Redis workloads plus
isolated local/test provisioning, and the scoped security fixes. Production
deployment/provisioning remains excluded. The prior working-tree baseline is
preserved in commit `2045762` on `codex/vue3-redis-modernization`.

## Decisions requested

1. Approve Vue 3, Pinia 3, Vue Router 4 and Vite 8.3, with Laravel-owned URLs and
   authentication. Start with Vue components inside Blade pages; introduce a
   bounded authenticated workspace only after its pages and contracts are tested.
   Retain Blade for the HTML envelope and email templates.
2. Approve Redis only for public firmware/config **version-number** lookups and
   bounded, anonymous request counters in the first release. Approve an isolated
   local/test Redis container to validate those workloads. Keep sessions,
   queues, authentication throttles, scheduler locks and live control data on
   their existing backends. Production Redis provisioning remains a separate approval.
3. Approve a separate, narrowly scoped security prerequisite increment before
   device-detail, admin and verification migration: enforce the documented
   owner/admin policy for browser device routes, restrict admin management,
   validate verification links, and remove sensitive serialization/logging.
   External firmware/mobile authorization redesign remains a separate ticket.

The approval gate comes from this ticket's acceptance criteria 2 and 6, and its
exclusion of backend-rule changes and Redis provisioning without separate approval.
Approving the frontend architecture alone does not approve production deployment
or the separately listed security changes.

## Read the proposal

- [Complete frontend inventory and screen dispositions](frontend-inventory.md)
- [Target architecture and dependency decisions](architecture.md)
- [Redis boundaries, expiration, invalidation and failure policy](redis-boundary.md)
- [Reviewable increments, tests, rollout and rollback](delivery-plan.md)
- [All 88 registered routes and middleware](route-snapshot.json)
- [All 74 current resource/public files](file-inventory.json)

The highest-impact findings are an out-of-sync npm lockfile, duplicate/unpinned
frontend libraries, sensitive logging/serialization, and differences between the
documented access model and some actual controllers. These are recorded as
explicit gates, not silently treated as solved by adopting Vue.
