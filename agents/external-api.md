# UI and external API parity

Read this file for every user-visible functionality or business-behavior change,
whether it starts in Vue, Blade, a controller, a service, or an integration.
It also applies to external routes, authentication, keys, and API contracts.

## Required business-capability parity

The application UI and `/api/external/v1` must maintain 1:1 business-capability
parity within the acting user's authorization. A new or changed UI capability
is not complete until an authorized external client can perform the same
underlying operation and obtain the same relevant business data. A missing or
stale external equivalent for an in-scope capability is a defect; implement the
equivalent, its tests, and its documentation in the same change set.

This includes reads, creates, edits, deletes, lifecycle transitions, bulk
actions, uploads/downloads, exports, reports, search, filters, sorting,
pagination, displayed fields, metrics, calculations, validation, empty results,
and Developer workflows. Review design changes when they expose different data
or actions. An endpoint returning some of the same records is not sufficient
when its supported operations or business semantics differ.

Parity concerns behavior and contracts, not rendering. Reuse the application's
services, policies, validation, transactions, state transitions, and side
effects where practical. Keep timestamps, timezones, range boundaries, units,
precision, null handling, and calculations consistent. A chart's layout is
presentation; its underlying measurements, filters, and calculated business
results still require parity. Do not make an integration reverse-engineer UI
code to reproduce an undocumented business calculation.

## Obsidian authorization and compatibility

- Extend the existing `/api/external/v1` route group and `auth.external`
  middleware. External routes require external bearer keys, with no browser
  cookie or application-token fallback. External keys must not authenticate
  application-owned or hardware endpoint groups. Preserve the existing
  application-owned API support for client tokens and same-origin browser
  sessions with CSRF protection; parity does not remove that session support.
- Exactly one application user is the Developer, determined only by
  `config('app.developer_email')` from `DEVELOPER_EMAIL` and `User::isDeveloper()`.
  Missing or blank configuration denies access. There is no admin or God role,
  role-only key, additional developer account, or TAGCSOFT company-scope model.
- External keys belong to that verified Developer user. Preserve active-key,
  expiration, revocation, owner verification, current Developer authorization,
  and rate-limit checks on every request. Separate integration keys do not
  create additional Developer users or permissions.
- Preserve each operation's ownership and Developer policies. Existing
  Developer access to devices across owners is not a blanket authorization
  bypass. Owner-only actions must retain their ownership checks; never
  impersonate another owner or expand access just to achieve parity. The lack
  of an endpoint alone is not an authorization exception.
- Preserve deployed device/firmware and mobile URLs, payloads, authentication,
  and response contracts. External API parity must not repurpose hardware
  endpoints or introduce physical-device effects into read operations.
- Never expose stored secret hashes, credentials, internal storage paths, or
  hidden fields to achieve parity. Maintain the existing one-time display of
  newly issued key secrets and sanitized list responses.

## Explicit exclusions

Presentation-only markup, styling, modal/tab navigation, browser focus, and
local display preferences do not need separate API operations. Authentication
and session infrastructure, isolated test/debug tooling, raw queue/storage
administration, and third-party behavior Obsidian cannot authoritatively
implement may also be excluded with a concrete explanation.

External key listing, creation, and revocation deliberately require a verified
Developer browser session and CSRF protection. Keep this security boundary:
bearer keys must not gain key-management or self-provisioning access through a
parity change. Other account/security workflows must preserve their existing
identity and verification requirements.

For each exclusion, record the affected capability, applicable boundary, and
reason in the change description; record lasting contract exclusions in
`docs/external-api-keys.md` or `docs/access-model.md`. Do not classify new data,
filters, calculations, or business actions as presentation-only. If parity
would require changing the authorization model, identify the gap and boundary
instead of silently weakening it; that model change needs explicit scope.

## Verification and completion

- Map each affected UI business capability to its external method/path and
  request/response contract, or to an explicit exclusion. Check existing routes
  and implementation; do not assume that documentation proves parity.
- Include authorized success, relevant ownership/Developer denial, validation,
  empty-result and missing-record behavior, persistence and side effects, and
  absence of sensitive internal fields in meaningful tests. Compare UI/API
  business results with the same isolated fixtures where practical. For auth
  changes, also cover invalid, expired, revoked, and unauthorized-owner keys,
  blank Developer configuration, and credential-type separation.
- Keep `GET /api/external/v1` capability discovery accurate when supported
  operations change. Update the integration contract and examples in
  [docs/external-api-keys.md](../docs/external-api-keys.md), affected feature
  documentation, and [docs/access-model.md](../docs/access-model.md) when access
  behavior changes. Preserve established compatibility or document an explicit
  migration; do not silently change clients' contracts.
- Follow [testing.md](testing.md), including real isolated browser verification
  for UI changes. Mock or block motor commands and other physical-device effects;
  never exercise actual hardware to prove parity.
- Report the parity mapping, any exclusions or unresolved gaps, and checks run.
  Do not defer an in-scope equivalent to an unspecified follow-up or claim the
  feature is complete while a required equivalent is missing.

Current implementation references: [external routes](../routes/api.php),
[authentication](../app/Http/Middleware/AuthenticateExternalApi.php),
[key validation](../app/Models/ExternalApiKey.php), and
[capability discovery](../app/Http/Controllers/Api/ExternalApiController.php).
