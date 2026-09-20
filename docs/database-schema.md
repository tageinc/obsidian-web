# Database schema and development data

The production backup `u882932635_obsidian_db.sql` was used as a read-only legacy reference for column names and indexes. The migration `2026_09_20_000000_create_device_application_tables` adds missing device, delivery, weather, geocode, remote-control, and telemetry tables with guarded creates. Its rollback intentionally preserves pre-existing legacy tables and data.

Run `php artisan migrate:fresh --seed` only against a disposable local database. Production upgrades must use `php artisan migrate` after a schema review; no migration in this change drops or alters an existing table. Factories provide synthetic records only and contain no credentials or production identifiers.
