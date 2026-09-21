#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."
test -s /opt/obsidian-web/shared/.env.docker || {
    echo 'Create /opt/obsidian-web/shared/.env.docker before deploying.' >&2
    exit 1
}
ln -sfn /opt/obsidian-web/shared/.env.docker .env.docker
# The transport archive must not be baked into the app image.
rm -f release.tar.gz
compose=(docker compose --project-name obsidian-web --env-file .env.docker)
"${compose[@]}" config --quiet
"${compose[@]}" build --pull
"${compose[@]}" up -d --wait --wait-timeout 180
"${compose[@]}" exec -T --user www-data app php artisan migrate --force
"${compose[@]}" exec -T --user www-data app php artisan view:clear
# Require a working Laravel login page, rather than merely a running Apache process.
"${compose[@]}" exec -T app php -r '
    $body = @file_get_contents("http://127.0.0.1/login");
    if ($body === false || strpos($body, "name=\"password\"") === false) {
        fwrite(STDERR, "Login smoke check failed\n"); exit(1);
    }
'
ln -sfn "$PWD" /opt/obsidian-web/current
echo 'Deployment completed successfully.'
