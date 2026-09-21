# Staged delivery and release plan

Status: S0 approved on 2026-09-21; implementation stages are in progress.
No production deployment, database migration or Redis provisioning has occurred.

## Baseline and commit discipline

The working tree already contains earlier requested dashboard/forms, graph,
seeder, remote-control and scheduler changes. HEAD alone is not the running
application baseline. Preserve them and record their paths before implementation.
Do not `git add -A`, reset them or silently include them in migration commits.

Before S1, establish a reviewed baseline commit for that prior work separately
from modernization, or use an isolated checkout carrying an explicitly reviewed
baseline patch. Each migration commit must then be independently buildable and
revertible relative to that baseline. Use a `codex/` implementation branch;
never push `main` to obtain a preview because its current workflow deploys.
S0 documentation can be reviewed before that baseline is finalized.

Baseline `2045762` now preserves that prior work separately. After the security
increment, record a security-corrected legacy image as the oldest allowed
deployment rollback target; never restore an image that reintroduces secret
logging or the repaired access/verification defects.

## Increments and cutover gates

Each row can require several small commits. A stage is complete only after its
focused tests, production build and direct-URL browser checks pass. Update the
inventory with actual migrated paths and commit IDs at each completion.

| Stage | Reviewable change | Required evidence / gate | Rollback point |
| --- | --- | --- | --- |
| S0 | Inventory, reference comparison, architecture, Redis boundary, this plan | Approval recorded for frontend, workloads/provisioning and any security scope | Documentation-only |
| S1a | Remove sensitive logs/unsafe User serialization; baseline auth/DTO secrecy tests | No credential/hash/token in logs, JSON DTOs or generated assets; preserve public API fields except forbidden secrets | Keep security corrections when rolling back frontend |
| S1b | Node/npm pin, Vue 3/Vite lockfile, Laravel 8 manifest adapter, isolated mounts, asset selection flags | Clean `npm ci`, lint, component smoke, production build, legacy pages unchanged, missing-manifest handling | Flags off; frozen legacy assets and previous image remain |
| S1c | CI checks and reproducible Docker frontend build stage | All checks gate release artifacts; no automatic deployment from modernization work | Revert build-stage change with its complete artifact set |
| S2 | Shared navigation/feedback/fields; profile, register device, edit device; contact and thank-you | One-save contracts, fixed identity, permissions, input retention/errors, keyboard/mobile checks; one workflow per cutover | Each workflow flag returns its legacy view |
| S3 | Dashboard table and Leaflet component | Own devices; aliases/query/flash; all vs paginated shapes; empty/error/retry; safe popup text; delete cancellation; map teardown | Dashboard flag off |
| Security gate | Separately approved browser ownership/admin/verification fixes | Owner/admin/other/guest/unverified matrix; signed/tampered/expired links; one resend | Keep enforcement on both legacy and Vue paths |
| S4 | Device status, raw charts, remote card | Full existing raw-time/DST/duplicate/null suite; persisted 0/1 mode; pending/failure recovery; zero commands on mount/navigation; authorization matrix | Device-detail flag off; backend contract unchanged |
| S5a | Enable bounded workspace Router once all five destinations qualify | Direct URL/reload, back/forward/query, stale request cancellation, session expiry, unknown 404; server checks on each read | Document navigation/per-page mounts remain available |
| S5b | Login/register/reset/confirm/verification Vue forms | Auth lifecycle, CSRF, errors, redirects, password secrecy and accessibility; security gate resolved | Auth flags off, server token/session logic retained |
| S6 | Admin uploads/tables/download navigation | Server admin policy approved; multipart size/type/errors; two filters/paginators; existing downloads/statuses | Admin flag off; no file/data migration |
| S7 | Approved optional Redis caches/counters, isolated test profile and runbook | TTL/invalidation/namespace/concurrency/outage tests; actual hit-rate/latency measurements; no session/control dependency | Disable workload flags; DB remains authoritative |
| S8 | Remove verified dead code/duplicate imports and complete inventory | No Vue 2/Mix runtime references on migrated routes, all active routes accounted for, retained exceptions explicit | Previous complete release image; retire per-view fallbacks only after acceptance |

Delta remains deliberately deferred to a source/workflow ticket; email Blade
templates and the minimal HTML envelope remain intentional non-Vue outputs.
All other reachable browser screens are included in the staged migration.

## Regression matrix

| Workflow | Existing coverage to preserve | Additional migration checks |
| --- | --- | --- |
| Dashboard/navigation | `DashboardTest` | Map JSON scope/shapes; unsafe text; no coordinates/zero coords; failed fetch; query/back-forward; mobile keyboard menu; no deletion prefetch |
| Profile | `ProfileUpdateTest` | Vue field bindings/errors/focus, one pending submit, password not preserved in bootstrap/history/store, legacy-phone cases |
| Device registration/edit | `DeviceFormsTest` | Component/default/readonly rendering, blank paired coordinates, no opt-in, validation summary, duplicate-submission prevention |
| Graphs | `SolarTrackerGraphDataTest`, `tests/js/solar-tracker-graph.test.js` | Component mount/unmount/redraw; Pacific AM/PM/noon/midnight/DST; duplicate and sparse samples; all ranges/raw values remain unchanged |
| Remote control | `SolarTrackerRemoteControlTest`, `tests/js/solar-tracker-remote.test.js` | Owner policy gate; canceled/stale reads; confirmed state on failure; no write during render/route changes; explicit action only |
| Auth/verification | Existing dashboard login/guest assertions | Complete registration/logout/remember/reset/confirm/verification/resend lifecycle with fake mail; signed/hash/expiry cases; 401/419 and HTML redirect handling |
| Admin | No focused admin/upload/download coverage | Permission matrix; 10 MB firmware and 1 MB config limits; multipart errors; independent table query params; missing/available download files |
| Static pages | No focused coverage; routes present in snapshot | Contact links, privacy PDF, thank-you links, semantic markup |
| Retained external APIs | `DeviceCommunicationsTest` and current route snapshot | Response shapes/statuses, firmware telemetry rates, legacy mutation compatibility; browser client never requires token login |
| Redis | None currently | Isolated real Redis hit/miss/TTL/invalidation/concurrency, disabled/outage paths, no sensitive values; normal tests without Redis |

The previous implementation increment passed 54 PHPUnit tests (8,386 assertions),
the two Node helper suites and isolated cron-installer checks. This is a baseline
record, not evidence that a Vue build or Redis integration already passes.

## CI and reproducible production assets

Add a checks workflow for PRs and branch changes, separate from production
deployment. The test environment contains synthetic data and fake/log mail only.

1. Install backend dependencies with `composer install` from the lockfile; run
   PHP syntax checks and all affected/full PHPUnit tests on the supported test
   PHP version. Keep SQLite isolation for the existing suite.
2. Install frontend dependencies using the pinned Node/npm and `npm ci` from an
   empty dependency directory. No developer-global packages or committed stale
   `node_modules` may make the job pass.
3. Run configured lint and format checks, Vitest component/helper tests, then
   `npm run build`. Check the manifest references emitted files and compatible
   runtime/compiler versions. Reject a second Vue runtime or Vue 2 dependency.
4. Build the production Docker image from a clean checkout without copied local
   assets/dependencies. Verify the login and a seeded authenticated page load
   the manifest-generated scripts/styles successfully.
5. Run workflow/browser and accessibility checks at representative mobile,
   tablet and desktop widths, including keyboard-only interactions and network
   errors. Seed only a disposable database; never operate real hardware or send
   notification mail from tests.
6. Run a separate opt-in Redis integration job with a disposable service and
   namespace; simulate connection loss/timeout and test recovery. No production
   service or credential access is needed.
7. Verify redaction with sentinel secret fixtures: no fixture credentials/hash,
   session identifiers or backend env values in logs, page DTOs or generated
   bundles. Production source maps remain private unless explicitly reviewed.

Record commands, versions, results and known exceptions in each increment's
review notes. Build reproducibility means clean installs reliably produce valid
assets from the lockfile; compare emitted hashes in the same pinned build image
to catch nondeterministic inputs, not across different OS/toolchain versions.

## Release without unreviewed deployment

- Work in reviewed commits/PRs. Do not run the current production workflow or
  push its trigger branch during implementation. Adding checks must not itself
  provision Redis, change production secrets or apply database migrations.
- Ship code with migration flags and optional Redis workloads off. A separately
  approved staging release validates direct URLs, auth/authorization, forms,
  graphs, remote control using a simulator, uploads and failed asset requests.
- Verify performance using the same representative seeded payloads and browser:
  initial JS/CSS weight, route loads, table/map interaction, chart redraw and
  server version-lookup latency/hit rates. No arbitrary performance claim before
  measurements; investigate meaningful regressions before cutover.
- A production release needs explicit approval, retained prior image/assets,
  completed security gates, checks passing, and an agreed operator/runbook.
  Enable one workflow group at a time. Redis gets its own enablement decision.
- Watch error rates, 401/403/419/422/5xx patterns, missing chunks, latency and
  Redis failures without logging sensitive payloads. Revert the relevant
  frontend flag first; use the prior complete image if the build layer fails.
- Redis rollback disables only the optional workloads. No session migration,
  queue drain, control-data reconciliation or database rollback is required.
- After acceptance and an agreed rollback window, S8 can remove dead frontend
  assets/source and flags. The release notes must list remaining exceptions and
  the exact previous image/commit rollback target.

## Follow-up tickets and blockers

| Follow-up | Why split / impact |
| --- | --- |
| Supported Laravel upgrade | Laravel 8 is outside its support period; backend/framework upgrade exceeds a frontend rewrite and must be scheduled separately |
| Browser ownership/admin/verification prerequisites | Separate scope approval requested now; these block secure cutover of affected workflows if declined |
| Firmware/mobile API security audit | Different authentication/compatibility consumers; do not change firmware behavior through a browser migration |
| Delta source and retained use | Missing XLSX prevents a faithful current workflow test; keep route documented rather than fabricate data |
| CSRF-protected device deletion contract | Existing GET mutation needs a reviewed compatibility transition, independent of Vue rendering |
| Redis sessions/queues/rate limits | Workloads have availability/delivery/security semantics outside the proposed disposable caches |
| Production Redis and asset-retention provisioning | Explicitly excluded without separate approval; local integration tests do not authorize it |

## Acceptance tracking

1. Inventory: prepared, all screens classified as deferred/retained with reasons;
   update to migrated/removed only with execution evidence.
2. Architecture: proposed and grounded; approval pending.
3. Reproducible Vue build: S1, not yet implemented.
4. Workflow contracts/regressions: S2–S6 plus security gates.
5. State/navigation conventions and legacy cleanup: S1, S5a, S8.
6. Approved Redis boundaries/operations: proposal prepared; approval and S7 pending.
7. Secrets: prerequisites and tests specified; existing defects not yet remediated.
8. CI: S1c and workflow/Redis jobs, not yet implemented.
9. Reviewable rollback-safe delivery: this plan defines increments; implementation
   baseline and commits remain to be established after approval.
