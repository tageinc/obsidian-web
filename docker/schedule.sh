#!/usr/bin/env bash
set -euo pipefail

# Cron has a minimal environment. Compose gets application settings from the
# deployed environment file; no credentials belong in the crontab.
export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
cd "$(dirname "$0")/.."

# Share the deployment lock so a tick cannot run against a half-deployed app.
# A slow scheduler tick also cannot overlap the next minute's tick.
exec 9>/opt/obsidian-web/shared/scheduler.lock
flock -n 9 || exit 0

exec docker compose --project-name obsidian-web --env-file .env.docker \
    exec -T --user www-data app php artisan schedule:run --no-interaction
