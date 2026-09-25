# Isolated browser regression suite

Run `npm ci`, `npm run build`, `npx playwright install chromium`, then
`npm run test:browser`. PHP 8.3 with SQLite and installed Composer dependencies
must be available as `php` (or an explicit `PHP_BINARY`). CI pins Node 24.15.0
and npm 11.8.0 and runs these tests after backend/frontend checks.

Playwright starts its own server at `127.0.0.1:8127`; it refuses to reuse an
existing server or accept an application base URL. The launcher makes a fresh
SQLite file and storage/cache/session directories under an OS temporary
`obsidian-browser-*` directory. The fixture application refuses non-testing,
non-SQLite, non-temporary databases. It applies migrations only to that new
empty file. It never runs the development or production database seeders.

Accounts and the one device are synthetic. Mail uses Laravel's array transport,
logs are disabled, Redis workloads are disabled, and external browser requests
are blocked (map tiles use a static fixture). Motor-command requests are blocked
and asserted absent on mount/navigation. The dedicated speed-slider test
fulfills commands with mocked responses and verifies that the fixture's stored
command remains unchanged. Forms exercise rejected submissions; the device modal also
saves and restores an address line on the synthetic fixture only. Screenshots,
video, traces, stored login state and artifact uploads are disabled. Any failure
context contains synthetic fixtures only. Temporary files are cleaned up on
normal server exit; forced OS termination can leave a disposable fixture
directory for the OS temporary-file cleanup policy.

The desktop and mobile Chromium projects cover login/logout redirects, bounded
workspace navigation, map loading/failure/retry/empty data, profile and device
validation, readonly device identity, raw graph ranges and timestamp labels,
remote-control inactivity on mount/navigation, slider preview/autosave, speed
bounds, pending guards, failure rollback, auth/public pages and authorized
multipart upload validation. Axe checks WCAG A/AA rules on representative pages;
horizontal overflow and keyboard menu access are also checked. Backend and Vue
unit tests cover the remaining authorization, mutation, timezone/DST, lifecycle,
and error-handling cases.

Developer coverage includes Tools navigation, key creation and validation,
one-time secret display, reload persistence, revocation, CSRF rejection, and a
real authenticated external device-list request against synthetic fixtures.
The browser fixture developer is `developer@browser.example.test`. External
remote-control endpoints are never invoked by these tests.

Release-table coverage uses synthetic firmware/configuration records to verify
full-history search, independent query state, searchable Tom Select prefix and
version filters, inclusive upload dates, clearing, sorting, pagination, reloads,
flat rows, accessibility, and desktop/mobile overflow.

The checks workflow also runs the real Redis integration script against a
disposable service with a unique namespace. The existing deployment workflow
depends on these checks; running tests does not deploy or provision production.
