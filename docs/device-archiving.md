# Device state and status

`devices.state` is `active` or `inactive` (default: `active`). The lifecycle migration
preserves the previous address-region column as `devices.address_state` and marks
existing devices active. Device create/edit forms and APIs now use `address_state`
for the installation region; user profile addresses still use `state`.

Device list responses expose `state` for lifecycle and `status` for operational
values such as `sleep` or `offline`. Operational status comes from geocode/telemetry;
the original telemetry protocol and its stored `device_logs.data.state` field
are unchanged.

Owners inactivate devices using `POST /retire-device/{id}` (session and CSRF protected) or
`POST /api/device/{id}/retire` (authenticated API). Inactive records, coordinates,
and telemetry remain stored, but active lists/maps and device status notifications
exclude them. The old browser and API delete endpoints are removed; model deletion
is rejected. Inactive devices show Reactivate, which restores their state to `active`.

Deploy with `php artisan migrate --force`, not a database reset.
