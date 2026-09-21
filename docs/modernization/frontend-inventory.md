# Frontend inventory

Snapshot: 2026-09-21; Obsidian HEAD `c8c32c9` **plus existing uncommitted work**.
The dashboard, unified forms, raw graphs, remote mode and cron work from earlier
increments are part of the observed baseline and must not be discarded.

There are 32 Blade templates, 15 reachable browser screens, one unused Vue 2
component and 88 registered routes. The [file list](file-inventory.json) includes
every one of the 74 files currently under `resources/` and `public/`, including
hidden hosting files. The [route snapshot](route-snapshot.json) records methods,
names, actions and middleware from `php artisan route:list --json`.

## Screen and template dispositions

**Nothing is migrated or removed by this planning increment.** `Deferred/Sn`
means intentionally deferred until approval and the indicated delivery stage.
`Retain` is a documented server-rendered exception. Cleanup candidates stay in
place until the S8 reference check; they are not evidence of active workflows.
Paths in this table are relative to `resources/views/`.

| File | Current workflow / contract | Disposition and reason |
| --- | --- | --- |
| `device-manager.blade.php` | `/dashboard`; own devices, Leaflet, `show`/`page`, all/paginated map, view/edit/delete | Deferred/S3; migrate table and map, preserve filtering, flash and aliases |
| `profile.blade.php` | GET/PUT `/profile`; one save, contact/address/optional password | Deferred/S2; retain atomic validation, unchanged legacy phones and blank-password behavior |
| `device-register.blade.php` | GET `/device-register`, POST `/dataInsert`; profile defaults, one save, no opt-in | Deferred/S2; preserve serial uniqueness, transaction and notification defaults |
| `edit-device.blade.php` | GET/PUT `/edit-device/{id}`; owner/admin checks | Deferred/S2; preserve immutable identity and paired coordinates |
| `device-info.blade.php` | `/device-info/{id}`; status, raw charts, remote mode/speed | Deferred/S4; requires security gate and graph/control regression coverage |
| `admin-control-center.blade.php` | Admin tables, multipart firmware/config uploads, downloads | Deferred/S6; server permission gate, two independent filters and upload contracts |
| `auth/login.blade.php` | GET/POST `/login`, remember me, verification resend, reset links | Deferred/S5; preserve cookie authentication and error redirects |
| `auth/register.blade.php` | GET/POST `/register`; account/contact/address/password | Deferred/S5; preserve validation; remove stale listener for nonexistent contractor inputs |
| `auth/verify.blade.php` | `/email/verify`, resend and verification-link flow | Deferred/S5; signature/hash enforcement pending security gate |
| `auth/passwords/email.blade.php` | GET `/password/reset`, POST `/password/email` | Deferred/S5; keep server token/email processing |
| `auth/passwords/reset.blade.php` | GET `/password/reset/{token}`, POST `/password/reset` | Deferred/S5; token stays transient, preserve errors/confirmation |
| `auth/passwords/confirm.blade.php` | GET/POST `/password/confirm` | Deferred/S5; preserve session confirmation behavior |
| `contact-us.blade.php` | Public `/contact-us`, contact links | Deferred/S2; simple page; fix nested document markup without redesign |
| `thank-you.blade.php` | Authenticated `/thank-you`, device links | Deferred/S2; still a supported direct URL |
| `delta.blade.php` | Authenticated `/delta`, spreadsheet analysis, Raphael/JustGage | Deferred/follow-up; `public/Delta Analysis - Smart Panels.xlsx` is absent; retain URL until source/workflow decision |
| `layouts/app.blade.php` | Shared HTML, navigation, CSRF, asset loading | Deferred/S1 then S2; retain minimal Blade envelope, move interactive navigation to Vue |
| `partials/device-form-fields.blade.php` | Shared device address/coordinate inputs | Deferred/S2; replace with prop/event Vue fields after both forms pass |
| `partials/device-form-feedback.blade.php` | Server success/errors | Deferred/S2; shared accessible feedback component |
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
| `resources/js/app.js` | Only Mix JS entry; global Vue 2 mounts all `#app` content | S1 replace with explicit Vue 3 mounts; never mount over untouched Blade DOM |
| `resources/js/bootstrap.js` | Global Axios/Lodash/Bootstrap, swallowed Bootstrap errors | S1 replace with one client and explicit module imports |
| `resources/js/components/ExampleComponent.vue` | Only Vue SFC; unused scaffold | S1 remove after reference assertion; no Vue 2 compatibility runtime needed |
| `resources/js/reg.js`, `public/js/reg.js` | Different, unused legacy validators | S8 removal candidate; don't port stale form assumptions |
| `resources/js/payment.js`, `public/js/payment.js` | Duplicated generated payment library, no current use | S8 removal candidate with unused `payment` dependency |
| `public/js/solar-tracker-graph.js` | Active UMD/global helper + Node tests | S4 move source into ESM; preserve raw timestamps, ordering, missing points and Pacific formatting |
| `public/js/solar-tracker-remote.js` | Active UMD/global remote helper + Node tests | S4 Vue adapter; preserve explicit action-only writes and confirmed/pending/error state |
| `resources/sass/app.scss`, `_variables.scss` | Mix Sass entry and theme | S1 import through new build; retain visual defaults and test Bootstrap consolidation |
| `resources/css/app.css`, `resources/css/dash.css` | Unused stylesheet and empty file | S8 removal candidates |
| `public/css/dash.css` | Legacy web layout styling | Retain with legacy shell until S8 |
| `public/js/app.js`, `public/js/app.js.LICENSE.txt`, `public/css/app.css`, `public/mix-manifest.json` | Committed generated Mix artifacts | Keep frozen during transition; S8 remove from active loading after rollback window |
| `package.json`, `package-lock.json`, `webpack.mix.js` | Vue 2/Mix 5 build definition and stale lock | S1 replace toolchain and regenerate exact lock; S8 remove Mix config |

Styles/scripts embedded directly in Blade must be migrated with their owner:
layout navbar/logout and CDN imports; dashboard Leaflet/popup/fetch/history;
device-info chart/control bootstrap; auth/register obsolete listener; delta
Raphael/JustGage; contact/thank-you nested documents; legacy web layout inline
styles/analytics/Livewire. No frontend dependency should remain an unpinned CDN
runtime after its owning workflow is migrated.

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

## Baseline defects and compatibility constraints

1. `package-lock.json` is v2 and disagrees with the manifest: Bootstrap 4.5.3 vs
   requested 5.1.3; Sass loader 8 vs 11; no locked Vue runtime or Popper core.
   Mix is locked at 5.0.7/Webpack 4.44.2. Clean-install reproducibility is not met.
2. The active layout mixes three Bootstrap CSS sources, duplicate Bootstrap JS,
   CDN jQuery and unpinned Chart.js. Dashboard Leaflet is also unpinned. Admin
   loads unused Leaflet. Consolidation requires visible/keyboard regression checks.
3. Docker serves committed assets without a Node build. The only workflow deploys
   `main` without frontend checks; the modernization branch must not trigger it.
4. `VerifyCsrfToken` logs tokens; API login logs credentials and serializes a User
   model lacking hidden password fields. `LogRequests` logs full URLs. Dashboard
   prints data payloads and interpolates unescaped popup HTML. See security gates.
5. Actual `DeviceInfoController` checks device existence/hardware, **not ownership**.
   Admin routes use auth+verified but no server admin check. In contrast, web
   `EditDeviceController` does check owner/configured admin. Do not infer access
   enforcement from navbar visibility or from `docs/access-model.md` alone.
6. Custom verification currently ignores the URL hash, has commented middleware,
   and resends twice. Resolving it changes server behavior and needs the separate
   prerequisite scope in the approval package.
7. Device deletion mutates through GET; do not router-prefetch, replay or cache it.
   Preserve its compatibility URL pending a separately approved mutation change.
8. Duplicate email-resend declarations and duplicate device-refresh route names
   exist. Preserve observed resolution until their transport cleanup is reviewed.

## Refreshing the inventory

Use `rg --files --hidden resources public` for the exact file set and
`php artisan route:list --json` for registered routes. Before removing candidates,
search static references, dynamic view/component registration and asset URLs.
Update dispositions after **each** migration increment; never mark a page migrated
based only on a new component file without route, build and regression evidence.
