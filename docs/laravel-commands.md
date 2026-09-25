# Laravel command cheatsheet

Run from the project folder. These examples target your **local development database**.


## Everyday commands

```powershell
php artisan migrate          # Apply pending migrations; keep existing data
php artisan migrate:status   # See applied/pending migrations
php artisan route:list       # List routes
php artisan optimize:clear   # Clear Laravel caches
php artisan tinker           # Interactive Laravel console
php artisan test             # Run PHP tests
```

## Seeders

```powershell
php artisan db:seed                                       # Default seeders
php artisan db:seed --class=DeveloperSeeder                # Developer account + profile
php artisan db:seed --class=DevelopmentDataSeeder          # 15 mock devices
php artisan db:seed --class=DeviceSeederRS                 # Real Scenario device + CSV readings
php artisan db:seed --class=DeviceLogSeeder          # Mock readings for the first mock device
php artisan db:seed --class=FirmwareAndConfigurationSeeder # Sample firmware/configuration
```

Device seeders target `andre.troncoso@tezca.net`. Mock/real-scenario seeders require `APP_ENV=local` (testing is also allowed). Run `DevelopmentDataSeeder` before `DeviceLogSeeder`.

`DeveloperSeeder` supports local and production. Set `DEVELOPER_EMAIL` and `DEVELOPER_BOOTSTRAP_PASSWORD`; existing accounts are preserved. Default seeding also calls the local `UserSeeder`, which uses `LOCAL_DEVELOPER_PASSWORD` and resets that account's password when set. Real-scenario and mock-reading seeders are separate from default seeding.

## Fresh database — deletes ALL tables and data

```powershell
php artisan migrate:fresh          # Recreate empty tables
php artisan migrate:fresh --seed   # Recreate tables and run default seeders
php artisan db:seed --class=DeviceSeederRS # Optional: add the real scenario afterward
```

## Your local Docker app

Replace `php artisan` above with this prefix (the local Compose app otherwise uses `APP_ENV=production`):

```powershell
docker compose --env-file .env.docker exec -T -e APP_ENV=local app php artisan
```

Example:

```powershell
docker compose --env-file .env.docker exec -T -e APP_ENV=local app php artisan db:seed --class=DevelopmentDataSeeder
```

After code changes: `docker compose --env-file .env.docker up -d --build app`.

## Device log JSON migration

`App\Models\Api\DeviceLog` stores telemetry in `device_logs.data` (JSON with an
Eloquent array cast). IDs, serial numbers, timestamps, and existing indexes stay
on the renamed table. Historical measurement columns, including additional
columns on deployed tables, are copied into JSON in batches before being removed.
Database values are preserved, including nulls and decimal strings.
New `/api/log` reports retain all fields inside the `data` JSON, including reported
firmware and config versions, without coercing version strings to numbers;
existing firmware request/response formats and flat status fields remain supported.

Deploy with telemetry writers and status workers stopped (maintenance mode plus
stopped queue/scheduler processes), take a database backup, and run
`php artisan migrate --force` followed by `php artisan device:audit-telemetry`
before restarting them. Ensure the application database user has privileges on
`device_logs`, including any table-specific grants previously on `solar_tracker_logs`.
The migration requires ALTER/rename privileges and time proportional to log volume.
It can resume an interrupted backfill. Rollback deliberately refuses to discard
JSON-only fields; restore the pre-migration backup with the previous code if needed.
