# Obsidian access model

Obsidian access is based on authenticated users and device ownership or developer authorization. A registered device can use supported device and telemetry APIs without a license, subscription, order, payment, or billing status.

Authorize.Net, checkout, recurring subscription, license assignment, cancellation, and billing update paths have been retired. Existing deployments retain historical orders, licenses, subscription identifiers, and payment records until a separately approved archival plan is executed. Fresh installations do not create the retired billing, product, software, or connection tables.

Authentication, email verification, ownership checks, and developer authorization remain active.

## Developer Workspace

`GET /developer-workspace` is the canonical firmware/configuration management page.
The old `/admin-control-center` bookmark redirects there with its query parameters.
The Vue workspace follows TAGCSOFT's sidebar and content-card layout: release
navigation on the left, a compact section header with the upload action, and a
release-history table. On smaller screens the navigation sits above the content.
Firmware and Configuration sit under the collapsible Software item, using the
same Font Awesome download icon as TAGCSOFT. The group starts expanded and keeps
the selected section when collapsed. Both tools retain keyboard tab navigation
and upload dialogs.
Release rows stay flat, with the complete description visible. Each history has
its own full-history text search, Tom Select prefix/version multiselects,
inclusive upload-date filters, sorting, removable filter chips, and result-count
pagination. The default page size is 10. Search, filter, sort, and page-size
changes start that history on page one while retaining the other history's
filters and page. Filter drafts apply together; dismissing the menu discards
unapplied selections. Applied state is stored in the URL and survives reloads.
The legacy rendering fallback retains its existing forms.
Both URLs require authentication, a verified email, and the exact nonempty email
configured as `DEVELOPER_EMAIL` (`app.developer_email`). Missing or blank values
deny developer access. `ADMIN_EMAIL` is no longer read and has no fallback role.
Local examples use `andre.troncoso@tezca.net`; setting the policy does not create
an account or change its password. Refresh Laravel's configuration cache after
changing the setting in an environment.

For a whole-image rollback to a release before this rename, restore that
release's `ADMIN_EMAIL` setting to the same intended account and refresh its
configuration cache. A frontend feature-flag rollback keeps `DEVELOPER_EMAIL`
and the canonical route, because the server authorization policy is shared.

The same `User::isDeveloper()` policy permits developer access to device details,
graphs, remote controls, and device edits alongside the owning user. Upload
POST URLs remain `/upload-firmware` and `/upload-config`, with this developer
boundary enforced on the server. Existing browser download authorization and
external firmware/mobile API contracts are unchanged.

`DeveloperSeeder` runs in local and production environments. It reads
`DEVELOPER_EMAIL`, `DEVELOPER_BOOTSTRAP_PASSWORD`, and `DEVELOPER_BOOTSTRAP_NAME`.
It creates a missing configured developer account once and preserves existing
credentials. Local `UserSeeder` uses `LOCAL_DEVELOPER_PASSWORD`.

## External API keys

For setup instructions, request examples, and troubleshooting, see the
[External API keys user and integration guide](external-api-keys.md).

Developer Workspace → Tools → External API Keys creates named keys for
integrations. All keys belong to the one configured developer; there is no God
or admin user/role. Each key grants the developer's access to the explicitly
supported external endpoints. Multiple integrations can have separate keys.

Only a verified developer browser session can list, create, or revoke keys.
Management requests use session authentication and CSRF protection, never bearer
keys. The legacy frontend offers the same operations at
`/developer-workspace/api-keys`. Creation accepts a name and optional expiration
date (00:00 UTC, strictly after today). The full key appears once, with a copy
action in Vue; refreshing or closing the dialog loses it. Only a SHA-256 hash,
display prefix, owner, name, expiration, revocation, and usage timestamps are
stored. List responses do not contain either the secret or its hash.

Send `Authorization: Bearer <key>` to `/api/external/v1`. Every request checks
that the key is active and its owner is still verified and matches the current
`DEVELOPER_EMAIL`. Changing or clearing that setting, changing the owner's email,
or deleting the owner removes access. Revocation takes effect on subsequent
requests. Expired/revoked/invalid keys return JSON 401; no cookie fallback is
accepted. Successful authentication updates last-used time. The external API
allows 120 requests per minute per key, alongside the existing API IP limit;
rate-limit responses return 429 and `Retry-After`. No secrets are logged.

| Method | Path under `/api/external/v1` | Operation |
| --- | --- | --- |
| GET | `/` | Discover supported endpoints |
| GET | `/devices` | List devices across owners, paginated (`per_page` 1–100) |
| GET | `/devices/{id}` | Device details |
| PATCH | `/devices/{id}` | Edit fields supported by the existing device form; serial number is immutable |
| GET | `/devices/{id}/data` | Existing telemetry/graph response |
| GET | `/remote-control?serial_no=...` | Remote-control state |
| POST | `/remote-control` | Set `serial_no`, `mode`, and `motor_speed` using existing validation |
| GET / POST | `/firmware` | List releases / upload `firmware`, `description`, and `prefix` |
| GET | `/firmware/{version}/download` | Download firmware |
| GET / POST | `/configuration` | List releases / upload `config`, `description`, and `prefix` |
| GET | `/configuration/{version}/download` | Download configuration |

Uploads use multipart form data and return JSON 201 with a version and message.
Lists omit storage paths. Device edits and commands reuse existing controllers
and validation; there is no blanket bypass of application rules. Device
registration, account changes, key management, and owner-only lifecycle actions
are not exposed by this initial external endpoint group. Existing hardware and
mobile endpoint URLs/payloads remain unchanged.

## Application-owned API authentication

Sanctum has been removed. `ApiToken` and the `api` request guard now authenticate
existing clients. `POST /api/login` retains its `status`, `user`, and `token`
response, without creating a browser login session. Issued tokens remain
`id|secret`; only hashes are stored. Existing `personal_access_tokens` IDs and
hashes are retained, so previously issued client tokens continue working.
`POST /api/logout` revokes the presented token and returns 204. Other client
tokens remain valid. Invalid, expired, revoked, and orphaned tokens are denied.

Client tokens and external keys are separate: neither credential authenticates
the other's endpoint group. Same-origin browser API requests can still use
Laravel sessions with CSRF checks on writes. Browser login/logout remains
session-based. Cross-origin integrations use bearer tokens; the removed
`/sanctum/csrf-cookie` endpoint and `SANCTUM_STATEFUL_DOMAINS` are no longer used.
