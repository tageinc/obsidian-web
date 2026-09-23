# obsidian
The purpose of this software application is to manage Internet-Of-Things devices with geographical and sensor information with remote control capability

## Docker deployment

For automated VPS deployments, follow [GitHub Actions setup](docs/github-actions-deployment.md).

For a local test email inbox, follow [Mailpit setup](docs/mailpit.md).

Requires Docker Engine with Docker Compose v2 or newer (Docker Desktop on Windows).
The image uses PHP 8.3 and Apache, serves only `public/`, and installs production
Composer dependencies. It uses the compiled CSS/JS already committed in `public/`;
rebuild and commit those assets separately when changing frontend sources.
MySQL 8.4 and Laravel storage use persistent named volumes. The database is not
published to the host. The app defaults to `http://localhost:8080`.

1. Copy `.env.docker.example` to `.env.docker` (`cp` on Linux or `Copy-Item` in
   PowerShell). Set distinct, strong `DB_PASSWORD` and `DB_ROOT_PASSWORD` values,
   plus `APP_URL`, mail settings, and `DEVELOPER_EMAIL` for Developer Workspace
   access. Keep this file private. See [the access policy](docs/access-model.md).
2. Build the image:

   ```sh
   docker compose --env-file .env.docker build
   ```

3. For an existing installation, copy its original `APP_KEY` into `.env.docker`.
   For a new installation only, generate a key with the following command and
   paste the printed `base64:...` value into `APP_KEY` in `.env.docker`:

   ```sh
   docker compose --env-file .env.docker run --rm --no-deps app php artisan key:generate --show
   ```

4. Start the services:

   ```sh
   docker compose --env-file .env.docker up -d
   ```

5. For a new database, run migrations explicitly:

   ```sh
   docker compose --env-file .env.docker exec --user www-data app php artisan migrate --force
   ```

For an existing installation, back up and import its database before migrating,
and transfer its `storage/app` files into the app container's storage volume.
Do not generate a replacement application key. Migrations and seeding are never
run automatically during manual setup. Application update emails use a dedicated
database queue; other queue workloads retain their existing default.
Automated VPS deployments install one host cron entry for Laravel's scheduler;
see [scheduler setup and local development](docs/scheduler.md). The scheduler
processes a bounded mail batch every minute. See [application email delivery](docs/application-email.md)
for the queue migration, retries and operational checks.

On a VPS, place an HTTPS reverse proxy in front of `127.0.0.1:8080`, configure
Laravel's `TrustProxies` middleware for that proxy, and set `APP_URL` to your HTTPS
domain before switching DNS. The container does not force HTTPS itself. For
temporary direct HTTP access, set `APP_BIND=0.0.0.0` and recreate the app; the
default loopback binding keeps it private to the VPS.

Useful operations:

```sh
docker compose --env-file .env.docker logs --tail=100 app
docker compose --env-file .env.docker exec --user www-data app php artisan migrate:status
docker compose --env-file .env.docker up -d --build
docker compose --env-file .env.docker down
```

Always pass `--env-file .env.docker` so Compose uses the Docker database settings
instead of a local development `.env`. Restart with `up -d` after environment
changes. `down` retains data; `down -v` deletes the database and storage volumes.
Back up both volumes before upgrades. MySQL initialization credentials apply
only on first startup with an empty volume; changing the environment file does
not change an existing database user's password.

## Quality checks

Run `composer lint` to syntax-check first-party PHP files and `composer test`
to run the Laravel test suite. `composer check` runs both checks in order.

## Restored device workflows

Device creation uses the dashboard modal and `POST /create-device`, plus `POST /api/create-device`.
The retired `GET /create-device` page redirects to `/dashboard?create=1` to open
the modal. Validation errors reopen the modal with the entered values. The modal
also remains available when the legacy dashboard renderer is selected.
Run `php artisan migrate` to rename the legacy device table to `devices`
and remove hardware assignments while retaining device records.

In local/testing environments, `php artisan db:seed --class=DevelopmentDataSeeder`
creates or refreshes 15 devices for `andre.troncoso@tezca.net`, with random names,
worldwide coordinates, and matching statuses. `FirmwareAndConfigurationSeeder`
creates downloadable SP1 development fixtures; its firmware is not flashable.
`SolarTrackerLogSeeder` remains available for seven days of sample graph history
for the first development device.
