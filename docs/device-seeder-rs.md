# DeviceSeederRS (real scenario)

This optional local/testing seeder creates a separate **Real Scenario** device
(`RS0000000001`) for `andre.troncoso@tezca.net`, at the Los Angeles default
coordinates. It imports recorded telemetry into the existing device graphs.
It does not run automatically with `DatabaseSeeder`.

The supplied `solar_tracker_logs 2-10 to 2-13.csv` is included at
`database/data/device-real-scenario.csv`: 30,491 readings from
2026-02-10 00:00:03 through 2026-02-13 17:16:52, originally recorded for
device `202501220008`. The file is preserved unchanged. Its timezone-free SQL
timestamps are interpreted as UTC, matching the application's telemetry convention.
Set `DEVICE_SEEDER_RS_CSV` to override the default source.

Expected comma-delimited columns:

- `timestamp` (or `updated_at`): `YYYY-MM-DD HH:MM:SS` in UTC, or ISO 8601 with
  seconds and a timezone offset. Source times are retained, not shifted to today.
- `motor_speed`
- `temperature` (or `temp`)
- `ps1`
- `ps2`

Blank readings remain null; numeric values use the database's three decimal
places. No unit conversion or smoothing is applied. Duplicate timestamps remain
separate readings. Optional `ps_avg`, `pds`, `cts`, and `state` are imported too.
Source row IDs and device serials are not reused. Historical readings result
in an offline device status. The existing graph endpoint returns at most the
latest 9,000 readings; all 30,491 are retained in the database.

```powershell
php artisan db:seed --class=DeviceSeederRS
```

For Docker, include the CSV under `database/data`, rebuild the app, then run:

```sh
docker compose --env-file .env.docker exec -T -e APP_ENV=local app php artisan db:seed --class=DeviceSeederRS --force
```

The entire file is validated before writing. Re-running replaces only this
seeder's device history in a transaction, so it does not accumulate duplicates
or change the other development devices. Do not use its reserved serial for a
physical device. Malformed or missing files fail without replacing history.
