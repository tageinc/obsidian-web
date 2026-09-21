# Dashboard and account/device forms

`/dashboard` is the Device Manager: the signed-in user's devices, location map,
pagination, and shortcuts to Register device and Edit profile. Login redirects
here. `/` and the old `/device-manager` URL redirect to the same dashboard,
preserving query parameters. Authentication and email verification remain required.

The Device Manager table has a three-dot action menu for View, Edit, and Delete.
Delete retains the existing confirmation and server ownership check. The Vue
menu supports keyboard navigation, Escape, and outside-click dismissal; it is
rendered outside the scrollable table so its links remain accessible on mobile.

The alias search above the table uses `GET /dashboard?search=...&show=...` and
starts on page one. It matches a case-insensitive literal substring across all
of the signed-in user's supported devices before pagination; `%`, `_`, and `!`
are ordinary search text. Search is trimmed and limited to 255 characters.
Pagination and page-size changes preserve the applied search, both map modes
use the same filter, and Clear search resets the page while retaining page size.
An empty result explains that no aliases matched rather than claiming the
account has no registered devices. The retained Blade fallback supports the
same search and action routes.

Profile uses one `PUT /profile` action (`profile.update`) for name, email, phone,
full address, and an optional password change. A blank password keeps the current
password. Validation runs before saving, so a failed field leaves the profile
unchanged and returns entered non-password values for correction.

Device registration uses one `POST /dataInsert` action. Device editing uses one
`PUT /edit-device/{id}` action (`device.update`) for alias, address, and location.
Registration identity remains read-only when editing because serial number and
hardware identify telemetry and remote-control records. Device changes require
the owner or configured developer (`DEVELOPER_EMAIL`).

Register and Edit device no longer show notification opt-in controls. New web
registrations have email/SMS notifications off; editing preserves existing
notification preferences. The old per-field API routes remain available for
existing clients.
