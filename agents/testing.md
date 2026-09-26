# Testing rules

## Outcomes and isolation

- Test business outcomes, authorization, validation, persistence, payloads, and relevant side effects rather than implementation details or status codes alone.
- Follow nearby test conventions. Use `RefreshDatabase` for database tests where appropriate, deterministic fixtures, and only the data needed. Pure unit tests do not need a database.
- Run checks appropriate to the change. Fix relevant failures and report unresolved failures or environmental blockers before recommending a merge.
- Do not add tests that merely mirror a reversible documentation or cosmetic implementation change.
- Use disposable test accounts, databases, and files. Never run fixture setup or destructive test commands against production or a shared application database.

## Prevent environment-dependent failures

- PHP tests that render a Vue page must use `Tests\Concerns\UsesFrontendManifest`
  and call `$this->useFrontendManifest()` after `parent::setUp()`. This points
  asset rendering at the checked-in test fixture and verifies that path. Do not
  rely on an ignored local `public/build`, a development server, or a prior npm
  build to make PHP page tests pass.
- Keep backend tests runnable before the frontend build, as CI does. Preserve
  production missing-asset checks; do not disable Vue rendering, reorder CI to
  conceal a missing fixture, or remove an assertion to make a test pass.
- Use the toolchain and locked dependencies declared in
  [the checks workflow](../.github/workflows/checks.yml), `package.json`, and
  `composer.json`. When dependencies are missing or their lockfiles change,
  install from the lockfiles with `composer install` and `npm ci`; do not update
  dependencies incidentally. Verify PHP extensions and the browser runtime too.
- Use explicit synthetic fixtures and fixed clocks where a test depends on
  ordering, timestamps, timezone, or generated values. Keep tests independent
  of execution order and other tests' data or generated files.

## UI verification

UI behavior and styling changes require browser verification against the running
changed application with rebuilt assets and an isolated backend/database. Follow
[the browser harness guide](../tests/browser/README.md).

- Exercise affected workflows from their normal entry points and each affected consumer of a shared component.
- Verify saved values by reopening or reloading records. Cover applicable success, validation, cancellation, authorization, and error states.
- Check desktop/mobile widths, keyboard interaction, focus, scrolling, and overflow. Check themes only where supported.
- Use real isolated backend requests for ordinary data workflows. Component tests, mocked previews, compilation, and backend-only tests supplement browser verification.
- Preserve the harness's device-safety boundary: block or mock motor commands and other physical-device effects. Do not replace those mocks with real hardware calls to satisfy browser coverage. Map tiles and external services may remain fixtures.
- Keep artifacts and failure output free of real user data and secrets. Clean up only test-created resources.
- Report the environment, workflows exercised, results, mocked boundaries, and unverified areas. If required tooling is unavailable, attempt reasonable recovery and continue independent checks; do not describe incomplete UI verification as a pass.

## Mandatory completion checks

For application code, styles, templates, tests, dependencies, or configuration
changes, run the full checks below before reporting completion. Read the current
workflow and package scripts first; update this list when those commands change.
Focused suites are useful during development but are not a substitute for this
final run, even when the change appears limited to one layer.

Use `php scripts/check-all.php all` as the canonical local runner when a
disposable Redis service and the required testing environment are available.
CI may run `php scripts/check-all.php application` and
`php scripts/check-all.php redis` in separate jobs against the same revision;
both modes must pass for full verification. Use `--plan` to inspect the ordered
commands. The runner never deploys the application.

1. Finish implementation and review first. All agents or other contributors
   working on the same checkout must finish writing files before final checks
   start. Test runners must see a stable set of source files, tests, and assets.
2. Run `composer check` before the frontend build. It must complete both PHP
   syntax checks and the entire backend test suite successfully. Run it with
   the application's generated `public/build/manifest.json` unavailable, so an
   earlier local build cannot hide a missing test fixture. Use an isolated
   checkout that honors restricted-file exclusions, or temporarily move only
   that manifest to a verified backup path and restore it in `finally`, even
   when tests fail. Never overwrite an existing backup or delete user assets.
3. Run `npm run check`. It must complete lint, formatting, the entire Vue unit
   suite, legacy graph/control regressions, and the production build.
4. Run `php scripts/verify-frontend-assets.php` on that build.
5. Run `bash tests/scripts/scheduler-cron.test.sh` in a working Bash environment.
6. Run `docker build --tag obsidian-web:ci .` against the final files. Honor the
   restricted-file rules when preparing the build context: excluded files must
   not be copied or uploaded. This verifies the complete production image, not
   just Dockerfile syntax. It does not authorize deployment.
7. Run the full `npm run test:browser` suite with the rebuilt assets and isolated
   backend. Both configured desktop and mobile projects must pass. Install the
   matching Chromium runtime if needed; retain the hardware-request mocks.
8. Run `npx prettier --check playwright.config.mjs "tests/browser/*.mjs"`.
   Browser harness formatting and browser tests are separate CI steps and are
   not included in `npm run check`.
9. Run `php scripts/test-redis-workloads.php` against a disposable Redis service
   with the testing environment, PHP Redis extension, and explicit opt-in from
   the workflow's `redis-workloads` job. Never point it at a shared service or
   replace it with mocked cache tests and call that equivalent.

Documentation-only changes may skip application suites when they change no
runtime code, test fixtures, dependencies, executable scripts, or CI behavior.
Validate the edited instructions, referenced paths, and commands against their
sources, and state that the change is documentation-only. This exception does
not cover edits to test runners, workflow YAML, package scripts, or environment
configuration.

## Final-state verification and reporting

- A failure or a run interrupted by edits is not a passing completion check.
  Fix its cause, rerun the failing check, and rerun checks affected by the fix.
  If files changed while a full suite was running, rerun that suite after the
  files are stable; individual successful tests do not validate that mixed run.
- Rebuild assets after frontend source changes and rerun affected browser checks
  against that build. Do not cite results from an earlier revision as proof of
  the final behavior. Do not repeat successful checks when their inputs have
  not changed and no new concern affects their result.
- Do not skip tests, weaken assertions, suppress lint failures, add retries, or
  increase timeouts just to obtain a green result. Diagnose failures and preserve
  the behavior each check is intended to protect.
- Attempt reasonable recovery for unavailable tooling. If Bash, Docker, Redis,
  PHP extensions, browser installation, or another prerequisite remains blocked,
  complete independent checks and list the exact command, blocker, and remaining
  verification. Do not describe partial local validation as "all checks passed"
  or "CI-ready." Do not bypass CI or deployment gates.
- Report the commands that passed, failures or skipped checks and their reasons,
  and whether final verification used stable files and rebuilt assets. For an
  unrelated existing failure, establish that it predates the change without
  discarding others' work; preserve it and report it instead of hiding it.

For user-visible business changes, verify the UI/external API mapping and tests
required by [external-api.md](external-api.md), including authorization, shared
business results, validation, sensitive-field exclusions, and contract/discovery
documentation. Record concrete exclusions and unresolved parity gaps.
