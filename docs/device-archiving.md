# Device state and status

`devices.state` is `active` or `archived` (default: `active`). The archive migration
preserves the previous address-region column as `devices.address_state` and marks
existing devices active. Device create/edit forms and APIs now use `address_state`
for the installation region; user profile addresses still use `state`.

Device list responses expose `state` for lifecycle and `status` for operational
values such as `sleep` or `offline`. Operational status comes from geocode/telemetry;
the original telemetry protocol and its stored `solar_tracker_logs.state` field
are unchanged.

Owners archive using `POST /archive-device/{id}` (session and CSRF protected) or
`POST /api/device/{id}/archive` (authenticated API). Archived records, coordinates,
and telemetry remain stored, but active lists/maps and device status notifications
exclude them. The old browser and API delete endpoints are removed; model deletion
is rejected. There is currently no restore control in the UI.

Deploy with `php artisan migrate --force`, not a database reset.
