# Staged delivery and release plan

Status: implementation and local verification completed on 2026-09-21. Fourteen reachable pages are migrated; delta remains deferred and email templates remain Blade. Production enablement requires separate approval.
No production deployment, application data migration or production Redis provisioning has occurred. The opt-in local Redis profile was provisioned and tested; local enablement does not authorize production changes.

## Baseline and commit discipline

Prior dashboard/forms, graph, seeder, remote-control and scheduler work was
preserved separately in baseline `2045762`. The approved architecture is
`79b11a5`; security corrections are `306beb8`; optional Redis logic is
`712e1c6`; prepared Vue forms are `0f6a384`; dashboard/admin/public preparation
and final legacy log/popup corrections are `93a3fc7`; tracker chart/control
components are `4fb6112`; Redis operational-outage handling is `f65498f`;
Vue foundation, manifest integration and bounded routing are `5fe57d5`;
pinned Docker infrastructure and configured CI/browser checks are `6cbd11f`.
Source-preparation increments stayed disabled until the foundation was installed.
Committed feature flags default off. Ignored local environment settings now
explicitly enable the frontend, workspace and approved Redis workloads.

Continue with explicit path staging, reviewed increments and a `codex/` branch.
Do not push `main` for a preview: the existing production workflow deploys.
Keep each complete release image and its assets as the deployable rollback unit;
intermediate source-preparation commits are not individually enabled previews.

The oldest allowed whole-image rollback is `93a3fc7`: it includes security
commit `306beb8`, request-log redaction in `712e1c6` and the final legacy
popup/API/browser redaction. Frontend flag rollback keeps these corrections
active. Never restore an image that reintroduces private logging or permissive
ownership, admin or verification checks. Old non-expiring verification links,
previously signed forever, require a fresh resend. Expired or tampered signatures
are rejected.

## Delivered increments and rollback

The migrated workflows, bounded router, toolchain, approved Redis workloads and
verified source cleanup have passed the local checks below. The table records
the scope and evidence for each increment and remains the checklist for a
separately approved production release. Local acceptance does not authorize
production deployment, production Redis provisioning or additional workloads.

| Stage | Delivered change | Verification scope | Rollback point |
| --- | --- | --- | --- |
| S0 | Inventory, reference comparison, architecture, Redis boundary, this plan | Approval recorded for frontend, workloads/provisioning and any security scope | Documentation-only |
| S1a | Remove sensitive logs/unsafe User serialization; baseline auth/DTO secrecy tests | No credential/hash/token in logs, JSON DTOs or generated assets; preserve public API fields except forbidden secrets | Keep security corrections when rolling back frontend |
| S1b | Node/npm pin, Vue 3/Vite lockfile, Laravel 8 manifest adapter, isolated mounts, asset selection flags | Clean `npm ci`, lint, component smoke, production build, legacy pages unchanged, missing-manifest handling | Flags off; frozen legacy assets and previous image remain |
| S1c | CI checks and reproducible Docker frontend build stage | All checks gate release artifacts; no automatic deployment from modernization work | Revert build-stage change with its complete artifact set |
| S2 | Shared navigation/feedback/fields; profile, register device, edit device; contact and thank-you | One-save contracts, fixed identity, permissions, input retention/errors, keyboard/mobile checks; one workflow per cutover | Each workflow flag returns its legacy view |
| S3 | Dashboard table and Leaflet component | Own devices; aliases/query/flash; all vs paginated shapes; empty/error/retry; safe popup text; delete cancellation; map teardown | Dashboard flag off |
| Security gate | Separately approved browser ownership/admin/verification fixes | Owner/admin/other/guest/unverified matrix; signed/tampered/expired links; one resend | Keep enforcement on both legacy and Vue paths |
| S4 | Device status, raw charts, remote card | Full existing raw-time/DST/duplicate/null suite; persisted 0/1 mode; pending/failure recovery; zero commands on mount/navigation; authorization matrix | Device-detail flag off; backend contract unchanged |
| S5a | Bounded workspace Router across five migrated destinations | Direct URL/reload, back/forward/query, stale request cancellation, session expiry, unknown 404; server checks on each read | Document navigation/per-page mounts remain available |
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
| Admin | Browser security and upload/API regression coverage | Permission matrix; 10 MB firmware and 1 MB config limits; multipart errors; independent table query params; missing/available download files |
| Static pages | `PublicFrontendTest`; routes preserved in snapshot | Contact links, privacy PDF, thank-you links, semantic markup |
| Retained external APIs | `DeviceCommunicationsTest` and current route snapshot | Response shapes/statuses, firmware telemetry rates, legacy mutation compatibility; browser client never requires token login |
| Redis | `RedisWorkloadsTest`, `RedisWorkloadStoreTest`, `scripts/test-redis-workloads.php` | Isolated real Redis hit/miss/TTL/invalidation/concurrency, disabled/outage/DNS paths, no sensitive values; normal tests without Redis |

## Final local verification record

Completed on 2026-09-21 against the combined implementation:

| Check | Result |
| --- | --- |
| Full PHPUnit suite | PASS: 131 tests, 9,104 assertions |
| PHP syntax | PASS: 158 files |
| Vue unit/component tests | PASS: 78 tests in 14 files |
| ESLint and Prettier | PASS |
| Existing raw-graph and remote-control helper suites | PASS |
| Isolated Linux cron-installer checks | PASS |
| Playwright desktop/mobile workflows and strict axe checks | PASS: all 12 tests, approximately 1.7 minutes |
| Locked npm dependency audit | Zero reported vulnerabilities at the recorded check |
| Final Docker app build | PASS: clean `npm ci` with Node 24.15.0/npm 11.8.0 and production compilation; emitted asset hashes matched the host build |
| Real isolated Redis | PASS: hit/miss, TTL, invalidation, namespace isolation, 300 concurrent increments, DNS/refused-connection/read-timeout fallback and next-request recovery |

The Redis transport and service subset separately passed 35 tests / 144
assertions without requiring the host Redis extension. The real container run
measured 71.46 ms for 100 cached reads, 15.15 ms for refused-connection fallback,
69.68 ms for DNS failure and 201.62 ms for read-timeout fallback. These are local
synthetic measurements, not a production speedup or DNS-deadline guarantee.
Exact commands and failure policy are in the [Redis runbook](redis-boundary.md).

The final local app is running at `http://localhost:8080` with ignored local
frontend/workspace/Redis flags enabled. Direct browser verification covered the
authenticated dashboard, profile and device detail, with 2,018 raw readings and
146 points in the selected 12-hour range. Axis ticks displayed real clock times
such as 9 AM, 11 AM and 1 PM, and the checked pages had no horizontal overflow.
This verification issued no remote command or other mutation against the user's
database; mutation regressions use the isolated test environment.

GitHub CI is configured but was **not executed on GitHub** in this task. The
equivalent local checks above passed. No production deployment or production
Redis provisioning occurred.

## CI and reproducible production assets

The configured checks workflow covers PRs and branch changes separately from
production deployment. Its GitHub execution is not claimed here; the equivalent
local results are recorded above. The test environment contains synthetic data
and fake/log mail only.

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
- Commit code with migration flags and optional Redis workloads off. Local ignored
  environment settings may enable them for verification. A separately
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
- Four proven-unused source/build files are removed now: the old Vue 2 entry,
  bootstrap, ExampleComponent and Mix configuration. All frozen public assets,
  all 32 original Blade templates and retired Sass reference sources remain.
  Remove fallback assets/flags or orphan Blade candidates only after acceptance,
  an agreed rollback window and another dynamic-reference audit. Release notes
  must name the exact security-corrected image/commit rollback target.

## Follow-up tickets and blockers

| Follow-up | Why split / impact |
| --- | --- |
| Supported Laravel upgrade | Laravel 8 is outside its support period; backend/framework upgrade exceeds a frontend rewrite and must be scheduled separately |
| Firmware/mobile API security audit | Different authentication/compatibility consumers; do not change firmware behavior through a browser migration |
| Delta source and retained use | Missing XLSX prevents a faithful current workflow test; keep route documented rather than fabricate data |
| CSRF-protected device deletion contract | Existing GET mutation needs a reviewed compatibility transition, independent of Vue rendering |
| Redis sessions/queues/rate limits | Workloads have availability/delivery/security semantics outside the approved disposable caches |
| Production Redis and asset-retention provisioning | Explicitly excluded without separate approval; local integration tests do not authorize it |

## Acceptance tracking

1. Inventory: all 32 baseline Blade templates and 74 baseline resource/public
   paths are accounted for. Fourteen reachable pages have Vue implementations;
   delta is deferred, email output retained, and orphan templates remain explicit
   cleanup candidates. Snapshots record new and removed files.
2. Architecture: grounded in TAGCSOFT and approved before broad migration.
   The committed lock selects Vue 3/Vite, Pinia and bounded Vue Router.
3. Reproducible assets: the final pinned Node/npm Docker build and clean
   dependency installation passed; emitted hashes matched the host build.
4. Workflow contracts: migrated forms, dashboard, charts, remote control, auth,
   public and admin views retain their server boundaries with focused coverage.
   The full backend suite, 78 Vue tests, 12 desktop/mobile Playwright tests,
   strict axe checks and final direct-browser verification passed.
5. Conventions: shared client/forms/components and in-memory Pinia display
   state are implemented; bounded workspace routing retains document boundaries.
   No active Mix/Vue 2 source build remains; frozen assets are intentional rollback.
6. Redis: approved version-only caches and anonymous aggregate counts implemented,
   default off, with ordinary tests independent of Redis and real integration
   evidence. No sessions, queues, rate-limit, live data or production provisioning.
7. Secrets: server serialization/logging, legacy payload logs and popup rendering
   corrected with sentinel coverage. Old non-expiring verification links must
   be resent; expired or tampered signatures are rejected.
8. Checks: all final local backend, frontend, browser, accessibility, syntax,
   helper, cron and production asset checks passed. CI is configured but has
   not run on GitHub; no remote CI result is implied.
9. Delivery: baseline, approval, security, Redis, prepared workflows and the
   complete frontend foundation are separate increments. Committed flags default
   off; the local app is enabled and verified. Retain the documented security
   fixes on rollback. Production deployment and production Redis remain
   separately approved work.
