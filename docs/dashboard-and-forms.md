# Dashboard and account/device forms

`/dashboard` is the Device Manager: the signed-in user's devices, location map,
pagination, and a Create + button below the map. Profile is available from the
account menu; the dashboard header has no duplicate profile or registration
buttons. Login redirects here. `/` and the old `/device-manager` URL redirect to
the same dashboard, preserving query parameters. Authentication and email
verification remain required.

The `Create +` button sits below the device map and above Device Manager. With
the Vue dashboard and registration enabled, it opens a native modal dialog with
the shared device registration form. The dialog contains keyboard focus, closes
with Cancel, its close button, or Escape, and returns focus to Create +. Dismissal
is disabled while a native form submission is pending. Its header stays visible
while the form scrolls on smaller screens.

Registration still uses the existing native `POST /dataInsert`. Modal submissions
include `_registration_modal=1`; validation redirects back to the dashboard and
reopens the modal with entered values and focused error feedback. Profile address
defaults and allowed hardware options match the standalone registration page.
Successful registration redirects to the dashboard with the existing success
message. `/device-register` remains available for direct links and rollback; when
Vue registration is disabled, Create + links to that page.

The Device Manager table has a three-dot action menu with Edit and Delete, with
no separator. The device name opens the full View Device page. Its Overview,
History and Control sections reuse the existing device screen, including raw
timestamp graphs; opening or switching sections does not issue a control command.
Edit opens from the menu or inside the View modal. Both dialogs stay on the
dashboard, keep focus inside, and restore focus to their originating name or
action button when closed. On small screens their content scrolls beneath a
fixed header.

Modal details load from the existing authorized device URLs using
`X-Obsidian-Modal: 1` and JSON content negotiation. They reuse the exact Blade
mount props, have loading/error/retry states, and abort discarded requests.
Modal payloads require the relevant Vue page flag but do not require workspace
navigation; a disabled page falls back to document navigation. Responses remain
private and non-cacheable. The existing `/devices/{id}` and
`/edit-device/{id}` pages still support direct links and rollback.

Modal editing uses the same `PUT /edit-device/{id}` with JSON validation. Invalid
fields retain entered values and focus the error summary; close/cancel and
repeat submissions are disabled while saving. A successful save reloads the
current dashboard URL, preserving search and pagination and updating both the
table and map. Native standalone editing retains its existing redirect.

Delete retains the existing confirmation and server ownership check. The Vue
menu supports keyboard navigation, Escape, and outside-click dismissal; it is
rendered outside the table so its links remain accessible on mobile.

The Vue table follows TAGCSOFT's `ProjectsTable`/`DataTable` presentation: compact
uppercase headers, a linked device name, subtle row hover, a search toolbar, and
page-size/pagination controls alongside the result count. Click the row or use
its labeled chevron button to expand serial number, SKU, and location details;
multiple rows can stay expanded. Links and action buttons operate independently
of expansion. Missing details have explicit fallback text; a blank name uses
the serial number (or device ID) as its display name. At widths of 768px or less,
rows become labeled cards without horizontal scrolling, retaining accessible
column headings. The retained
Blade fallback keeps its previous presentation as the frontend rollback path.

The name search above the table uses `GET /dashboard?search=...&show=...` and
starts on page one. It matches a case-insensitive literal substring across all
of the signed-in user's supported devices before pagination; `%`, `_`, and `!`
are ordinary search text. Search is trimmed and limited to 255 characters.
Pagination and page-size changes preserve the applied search, both map modes
use the same filter, and Clear search resets the page while retaining page size.
An empty result explains that no namees matched rather than claiming the
account has no registered devices. The retained Blade fallback supports the
same search and action routes.

Profile uses one `PUT /profile` action (`profile.update`) for name, email, phone,
full address, and an optional password change. A blank password keeps the current
password. Validation runs before saving, so a failed field leaves the profile
unchanged and returns entered non-password values for correction.

Device registration uses one `POST /dataInsert` action. Device editing uses one
`PUT /edit-device/{id}` action (`device.update`) for name, address, and location.
Registration identity remains read-only when editing because serial number and
hardware identify telemetry and remote-control records. Device changes require
the owner or configured developer (`DEVELOPER_EMAIL`).

Register and Edit device no longer show notification opt-in controls. New web
registrations have email/SMS notifications off; editing preserves existing
notification preferences. The old per-field API routes remain available for
existing clients.

Device viewing uses `GET /devices/{id}` (`devices.show`) for the full View Device page. Authenticated API clients use `GET /api/devices/{id}` (`api.devices.show`). Both enforce owner/developer access and return 404 for missing devices. The former device-info route has been removed.

View Device supports inline editing with checkmark/save and X/cancel controls for name, serial, SKU, order, address, and coordinates. PATCH /devices/{id} validates changes and preserves serial-linked history. Status and lifecycle state cannot be changed through inline editing. The former edit-page URL redirects to View Device with its edit modal open.
