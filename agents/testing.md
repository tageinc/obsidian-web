# Testing rules

## Outcomes and isolation

- Test business outcomes, authorization, validation, persistence, payloads, and relevant side effects rather than implementation details or status codes alone.
- Follow nearby test conventions. Use `RefreshDatabase` for database tests where appropriate, deterministic fixtures, and only the data needed. Pure unit tests do not need a database.
- Run checks appropriate to the change. Fix relevant failures and report unresolved failures or environmental blockers before recommending a merge.
- Do not add tests that merely mirror a reversible documentation or cosmetic implementation change.
- Use disposable test accounts, databases, and files. Never run fixture setup or destructive test commands against production or a shared application database.

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

## Existing checks

- Backend: `composer check` runs syntax and regression checks; use focused PHPUnit runs during iteration.
- Frontend: `npm run check` runs the configured lint, formatting, unit/regression tests, and production build.
- Compiled assets: `php scripts/verify-frontend-assets.php` after a production build.
- Browser: follow `tests/browser/README.md` and run `npm run test:browser` with the required runtime installed.
- Infrastructure: validate relevant script/config syntax and build behavior; distinguish syntax checks from a full image build or deployment.

Use current package/composer scripts as the source of truth. Do not import
TAGCSOFT-specific seeders, database names, or test services. Broaden checks when
shared contracts or unresolved risks warrant it; avoid repeatedly rerunning
unchanged successful checks.
