#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."
test -s /opt/obsidian-web/shared/.env.docker || {
    echo 'Create /opt/obsidian-web/shared/.env.docker before deploying.' >&2
    exit 1
}
command -v crontab >/dev/null || {
    echo 'Install and enable cron on the VPS before deploying (see docs/scheduler.md).' >&2
    exit 1
}
# Wait for any current tick, and prevent new ticks during build and migrations.
exec 9>/opt/obsidian-web/shared/scheduler.lock
flock 9
ln -sfn /opt/obsidian-web/shared/.env.docker .env.docker
# The transport archive must not be baked into the app image.
rm -f release.tar.gz
compose=(docker compose --project-name obsidian-web --env-file .env.docker)
"${compose[@]}" config --quiet
echo 'Building production image, including npm ci and npm run build in the frontend stage.'
"${compose[@]}" build --pull
# Check the built image before replacing the running app. No database is needed.
"${compose[@]}" run --rm --no-deps --entrypoint php app scripts/verify-frontend-assets.php
"${compose[@]}" up -d --wait --wait-timeout 180
"${compose[@]}" exec -T --user www-data app php artisan config:clear
"${compose[@]}" exec -T --user www-data app php artisan migrate --force
"${compose[@]}" exec -T --user www-data app php artisan view:clear
# Report the actual rendering choice, not only the deployed commit/build.
"${compose[@]}" exec -T app php -r '
    require "vendor/autoload.php";
    $app = require "bootstrap/app.php";
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    foreach (config("frontend.vue3") as $page => $enabled) {
        echo "Frontend ".$page.": ".($enabled ? "Vue" : "legacy / disabled").PHP_EOL;
    }
'
# Check the login HTML; Vue renders its password input in the browser.
"${compose[@]}" exec -T app php -r '
    $body = @file_get_contents("http://127.0.0.1/login");
    if ($body === false || (
        strpos($body, "name=\"password\"") === false &&
        strpos($body, "data-vue-page=\"auth\"") === false
    )) {
        fwrite(STDERR, "Login smoke check failed\n"); exit(1);
    }
'
ln -sfn "$PWD" /opt/obsidian-web/current
bash docker/install-cron.sh
echo 'Deployment completed successfully.'
