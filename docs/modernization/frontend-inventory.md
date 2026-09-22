# Frontend inventory

Baseline: 2026-09-21, preserved in `2045762`; security baseline `306beb8`; Redis increment `712e1c6`. Earlier dashboard, unified forms, raw graphs, remote mode, demo seeder and cron behavior remain part of the migration baseline.

The baseline contains 32 Blade templates, 15 reachable browser screens, one unused Vue 2 component and 88 registered routes. The [file list](file-inventory.json) preserves all 74 original resource/public paths alongside current, new and removed paths. Generated `public/build/**` files are classified together and enumerated by the build manifest. The [route snapshot](route-snapshot.json) preserves the 88-route baseline and current routes, including repaired middleware and the Developer Workspace canonical URL with its legacy redirect.

## Screen and template dispositions

`Migrated` means Vue 3 implementation and regression coverage are complete, with the final backend/component/browser/build checks passing as recorded in the delivery plan. Fourteen reachable pages are migrated. Committed flags remain off by default; the local app is explicitly enabled and verified. Production enablement is separate. `Retain` and `Deferred` are explicit exceptions. All 32 original Blade files remain accounted for; no orphan Blade was deleted. Paths below are relative to `resources/views/`.

| File | Current workflow / contract | Disposition and reason |
| --- | --- | --- |
| `device-manager.blade.php` | `/dashboard`; own devices, Leaflet, `show`/`page`, all/paginated map, view/edit/delete | Migrated: `DashboardPage`/`DeviceMap`, flag `dashboard`; legacy popup text/logs also repaired |
| `profile.blade.php` | GET/PUT `/profile`; one save, contact/address/optional password | Migrated: `ProfilePage`, flag `profile`; native atomic validation and blank-password behavior |
| `device-register.blade.php` | GET `/device-register`, POST `/dataInsert`; profile defaults, one save, no opt-in | Migrated: `DeviceFormPage`, flag `device_register`; native validation/transaction |
| `edit-device.blade.php` | GET/PUT `/edit-device/{id}`; owner/developer checks | Migrated: `DeviceFormPage`, flag `device_edit`; immutable identity and paired coordinates |
| `view-device.blade.php` | `/devices/{id}`; status, raw charts, remote mode/speed | Migrated: `ViewDevicePage`/`HistoryCharts`/`RemoteControl`, flag `view_device`; server owner/developer checks on both frontends |
| `admin-control-center.blade.php` | `/developer-workspace`; firmware/config uploads and downloads; old URL redirects | Migrated: `AdminPage`/`UploadSection`, flag `admin`; server developer restriction on both frontends, internal names retained for compatibility |
| `auth/login.blade.php` | GET/POST `/login`, remember me, verification resend, reset links | Migrated: `AuthPage`, flag `auth`; cookie authentication and native redirects |
| `auth/register.blade.php` | GET/POST `/register`; account/contact/address/password | Migrated: `AuthPage`, flag `auth`; server validation and one native submission |
| `auth/verify.blade.php` | `/email/verify`, resend and verification-link flow | Migrated: `AuthPage`, flag `auth`; signatures/hash/expiry/throttling repaired; old non-expiring links require resend |
| `auth/passwords/email.blade.php` | GET `/password/reset`, POST `/password/email` | Migrated: `AuthPage`, flag `auth`; server token/email processing retained |
| `auth/passwords/reset.blade.php` | GET `/password/reset/{token}`, POST `/password/reset` | Migrated: `AuthPage`, flag `auth`; transient reset token, no client persistence |
| `auth/passwords/confirm.blade.php` | GET/POST `/password/confirm` | Migrated: `AuthPage`, flag `auth`; server session confirmation retained |
| `contact-us.blade.php` | Public `/contact-us`, contact links | Migrated: `PublicPage`, flag `public_pages`; minimal valid Vue page |
| `thank-you.blade.php` | Authenticated `/thank-you`, device links | Migrated: `PublicPage`, flag `public_pages`; direct URL retained |
| `delta.blade.php` | Authenticated `/delta`, spreadsheet analysis, Raphael/JustGage | Deferred/follow-up; `public/Delta Analysis - Smart Panels.xlsx` is absent; retain URL until source/workflow decision |
| `layouts/app.blade.php` | Shared HTML, navigation, CSRF, asset loading | Migrated envelope: manifest assets and `NavigationPage` on Vue documents; frozen assets on fallback documents |
| `partials/device-form-fields.blade.php` | Shared device address/coordinate inputs | Retain for flag-off rollback; Vue uses `DeviceFields`/`FormField` |
| `partials/device-form-feedback.blade.php` | Server success/errors | Retain for flag-off rollback; Vue uses `FormFeedback` |
| `emails/low_voltage.blade.php` | `LowVoltageMail` | Retain Blade; server-rendered email, not browser frontend |
| `emails/theft_vandalism.blade.php` | `TheftVandalismMail` | Retain Blade; server-rendered email |
| `emails/support.blade.php` | `SupportMail` | Retain Blade; server-rendered email |
| `emails/users/invite.blade.php` | `UserInvitation` | Retain Blade; server-rendered email |
| `dashboard.blade.php` | Superseded dashboard, no current route/controller render | Deferred/S8 removal candidate; active dashboard uses device-manager |
| `front-page.blade.php` | Empty template, unrouted `FrontPage` controller | Deferred/S8 removal candidate |
| `checkout/success.blade.php` | Retired checkout result | Deferred/S8 removal candidate; billing routes already retired |
| `checkout/error.blade.php` | Retired checkout result | Deferred/S8 removal candidate |
| `update/success.blade.php` | No render/route reference found | Deferred/S8 removal candidate |
| `update/error.blade.php` | No render/route reference found | Deferred/S8 removal candidate |
| `layouts/web.blade.php` | Unused marketing/Livewire shell | Deferred/S8 removal candidate; do not recreate company/billing UI |
| `components/alert.blade.php` | Only unused web layout references it | Deferred/S8 removal candidate |
| `livewire/users-table.blade.php` | No active mount; expects missing company/user routes | Deferred/S8; audit dynamic Livewire registration before removal |
| `livewire/change-user-role.blade.php` | No active mount; legacy company roles | Deferred/S8; remove with backing orphan only after reference audit |

## JavaScript, CSS and build entries

| Paths | Current role | Target disposition |
| --- | --- | --- |
| `resources/js/app.js` | Only Mix JS entry; global Vue 2 mounts all `#app` content | Removed: only build reference was the retired Mix entry; compiled legacy bundle retained |
| `resources/js/bootstrap.js` | Global Axios/Lodash/Bootstrap, swallowed Bootstrap errors | Removed: only importer was removed Vue 2 entry; shared API client replaces it |
| `resources/js/components/ExampleComponent.vue` | Only Vue SFC; unused scaffold | Removed: unused scaffold, only registered in removed Vue 2 entry |
| `resources/js/reg.js`, `public/js/reg.js` | Different, unused legacy validators | S8 removal candidate; don't port stale form assumptions |
| `resources/js/payment.js`, `public/js/payment.js` | Duplicated generated payment library, no current use | Deferred cleanup candidate; the unused `payment` dependency is removed from the modern manifest |
| `public/js/solar-tracker-graph.js` | Active UMD/global helper + Node tests | Retain frozen fallback and Node regressions; Vue uses `features/solar-tracker/timeSeries.js`/`HistoryCharts.vue` |
| `public/js/solar-tracker-remote.js` | Active UMD/global remote helper + Node tests | Retain frozen fallback and Node regressions; Vue uses `RemoteControl.vue`, preserving explicit action-only writes |
| `resources/sass/app.scss`, `_variables.scss` | Mix Sass entry and theme | Retain retired theme source as rollback reference; no active build import; Vue uses pinned Bootstrap and `resources/css/frontend.css` |
| `resources/css/app.css`, `resources/css/dash.css` | Unused stylesheet and empty file | S8 removal candidates |
| `public/css/dash.css` | Legacy web layout styling | Retain with legacy shell until S8 |
| `public/js/app.js`, `public/js/app.js.LICENSE.txt`, `public/css/app.css`, `public/mix-manifest.json` | Committed generated Mix artifacts | Keep frozen during transition; S8 remove from active loading after rollback window |
| `package.json`, `package-lock.json`, `webpack.mix.js` | Vue 2/Mix 5 build definition and stale lock | Migrated manifest/lock to Vite/Vue 3; removed `webpack.mix.js` after proving no remaining build reference |

New entries are `resources/js/entries/app.js`, `vite.config.mjs` and `resources/css/frontend.css`. `App\Support\FrontendAssets` reads the Vite manifest; `frontend/mount.blade.php` emits allowlisted props. Feature folders cover navigation, profile, devices, dashboard, solar-tracker, auth, admin and public. Shared API, controls, form composable and Pinia session-display state live under `resources/js/shared/`; bounded routing lives under `resources/js/workspace/`. New source files are enumerated in the JSON file list; focused tests live under `tests/Feature`, `tests/Unit`, `tests/js` and `tests/browser`.

Legacy inline code remains only in its owner's flag-off branch:
layout navbar/logout and CDN imports; dashboard Leaflet/popup/fetch/history;
view-device chart/control bootstrap; auth/register obsolete listener; delta
Raphael/JustGage; contact/thank-you nested documents; legacy web layout inline
styles/analytics/Livewire. No frontend dependency should remain an unpinned CDN
runtime on a Vue document. Delta remains a documented legacy exception. No parallel Vue 2 dependency installation or active Mix toolchain remains.

## Static, hosting and non-browser entries

- Preserve the login-linked `public/pdf/Obsidian Privacy Policy May1st2024.pdf`.
- Preserve active `public/img/icon.png` and `logo.png`. Other PNGs are
  `americanexpress`, `auto2`, `down`, `mastercard`, `off`, `piggybank`, `refresh`,
  `remote`, `rubberDucky`, `stop`, `sub-lock`, `up`, `visa`, `wrench2`; all are
  deferred cleanup candidates, not deleted merely because current views omit them.
- Preserve `public/index.php`, `public/.htaccess`, `public/web.config`, root
  `.htaccess`, `server.php`; review asset rewrites during S1. Root `default.php`
  is a legacy hosting placeholder outside the Docker document root.
- Build/deployment entries: `Dockerfile`, `.dockerignore`, `compose.yaml`,
  `docker/apache.conf`, `docker/php.ini`, `docker/entrypoint.sh`,
  `docker/deploy.sh`, `.github/workflows/deploy.yml`. Scheduling files remain
  server infrastructure: `docker/{schedule.sh,install-cron.sh,obsidian.cron}`.
- Route sources: `routes/web.php`, `routes/api.php`; preserve external API
  contracts. `routes/channels.php` and `routes/console.php` remain server-only.
- Keep `resources/lang/en/{auth,pagination,passwords,validation}.php` as server
  message sources. `codebits/map_device_regis_admin_center.txt` is a historical
  snippet, not an executable frontend entry.

## Baseline defects and disposition

1. Baseline `package-lock.json` was v2 and disagreed with the manifest: Bootstrap 4.5.3 vs
   requested 5.1.3; Sass loader 8 vs 11; no locked Vue runtime or Popper core.
   Mix was locked at 5.0.7/Webpack 4.44.2. Resolved by the modern lock and clean container dependency install/build.
2. The baseline layout mixed three Bootstrap CSS sources, duplicate Bootstrap JS,
   CDN jQuery and unpinned Chart.js. Dashboard Leaflet was also unpinned; admin
   loaded unused Leaflet. Vue documents now use one pinned bundle; old assets are a rollback exception.
3. Baseline Docker served committed assets without a Node build, and the only
   workflow deployed `main` without frontend checks. Docker now compiles Vite assets; deployment remains separately approved.
4. Baseline `VerifyCsrfToken` logged tokens; API login logged credentials and
   serialized a User model lacking hidden password fields. `LogRequests` logged
   full URLs. Dashboard printed payloads and interpolated unescaped popup HTML. Server redaction/allowlisting is implemented; legacy browser logs/popups were also repaired so frontend-flag rollback does not restore those exposures.
5. Baseline `ViewDeviceController` checked device existence/hardware without
   ownership enforcement; admin routes lacked a server admin check. The existing
   web `EditDeviceController` already checked owner/configured admin. Approved server middleware now enforces the owner/admin browser policy on both frontend paths.
6. Baseline custom verification ignored the URL hash, lacked enforcement
   middleware and resent twice. The separately approved prerequisite correction
   is implemented. Old non-expiring verification links, previously signed forever,
   require a fresh resend; expired or tampered signatures are rejected.
7. Device deletion mutates through GET; do not router-prefetch, replay or cache it.
   Preserve its compatibility URL pending a separately approved mutation change.
8. Duplicate email-resend declarations and duplicate device-refresh route names
   existed. Verification routes are now canonical; both existing device-refresh URL/method contracts remain supported.

## Verified cleanup in this increment

Removed only `resources/js/app.js`, `resources/js/bootstrap.js`,
`resources/js/components/ExampleComponent.vue` and `webpack.mix.js`. The
retired Mix entry was the only build reference to the old app entry; that app
entry was the only importer of bootstrap and the unused example component.
Vite builds from the new entry. All frozen `public/js/**` and `public/css/**`
assets remain available for flag-off rollback. All 32 baseline Blade templates
remain, including orphan candidates and email templates; no dynamic Blade or
Livewire removal is assumed from a static search.

## Refreshing the inventory

Use `rg --files --hidden resources public` for the exact file set and
`php artisan route:list --json` for registered routes. Before removing candidates,
search static references, dynamic view/component registration and asset URLs.
Update dispositions after **each** migration increment; never mark a page migrated
based only on a new component file without route, build and regression evidence.
