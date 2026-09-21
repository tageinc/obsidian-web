# Dashboard and account/device forms

`/dashboard` is the Device Manager: the signed-in user's devices, location map,
pagination, and shortcuts to Register device and Edit profile. Login redirects
here. `/` and the old `/device-manager` URL redirect to the same dashboard,
preserving query parameters. Authentication and email verification remain required.

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
