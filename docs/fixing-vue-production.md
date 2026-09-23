# Fixing Vue in production

Use this guide when production shows the old dashboard, the Actions menu looks
different from the Vue component, or compiled JavaScript/CSS is missing.

Vue needs both **enabled rendering flags** and **compiled assets**. Rebuilding
assets does not enable Vue. The original production mismatch was caused by all
Vue flags being disabled, even though the server had the same commit.

## 1. Check the effective Vue settings over SSH

```bash
cd /opt/obsidian-web/current

docker compose --project-name obsidian-web --env-file .env.docker exec -T app php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach (config("frontend.vue3") as $page => $enabled) {
    echo $page.": ".($enabled ? "Vue enabled" : "Vue disabled").PHP_EOL;
}
'
```

For the full Vue interface, all entries should say `Vue enabled`. The command
reads Laravel's effective configuration, including any cached configuration.

## 2. Enable Vue if it is disabled

Edit the shared production environment file:

```bash
nano /opt/obsidian-web/shared/.env.docker
```

Replace existing entries with these values; avoid duplicate keys:

```dotenv
FRONTEND_VUE3_ENABLED=true
FRONTEND_VUE3_WORKSPACE=true
```

Remove per-page `FRONTEND_VUE3_*=false` overrides if all pages should use Vue.
Page overrides take precedence over the master switch. The current page suffixes
are `PROFILE`, `CREATE_DEVICE`, `DEVICE_EDIT`, `DASHBOARD`, `VIEW_DEVICE`, `AUTH`,
`ADMIN`, and `PUBLIC_PAGES`. For example, `FRONTEND_VUE3_DASHBOARD=false` still
selects the old dashboard even when the master switch is true.

Preserve the other settings, including the application key and database values.
Save in nano with Ctrl+O, Enter, then Ctrl+X.

If assets already pass the check in step 3, apply this configuration-only change:

```bash
cd /opt/obsidian-web/current
docker compose --project-name obsidian-web --env-file .env.docker up -d --force-recreate --no-deps app
docker compose --project-name obsidian-web --env-file .env.docker exec -T --user www-data app php artisan config:clear
docker compose --project-name obsidian-web --env-file .env.docker exec -T --user www-data app php artisan view:clear
```

This briefly restarts the app. A plain container restart does not load edited
environment values. Repeat step 1 after recreation.

## 3. Check compiled assets

On releases containing the asset verifier, run:

```bash
cd /opt/obsidian-web/current
docker compose --project-name obsidian-web --env-file .env.docker exec -T app php scripts/verify-frontend-assets.php
```

Success prints `Frontend assets verified` and a file count. The verifier checks
`public/build/manifest.json`, the app entry, imported and dynamically imported
chunks, and all referenced files, including CSS. Missing, empty, or unreadable
files cause a nonzero exit. It does not need a database or application secrets.

If the verifier script itself is missing, deploy the newer commit containing this
guide. File verification confirms the image contents; use step 5 to check browser
delivery and interaction.

## 4. Compile assets and deploy

For a new commit, use GitHub **Actions → Deploy VPS → Run workflow** on `main`.
The workflow uploads that commit and invokes `docker/deploy.sh` on the server.

To rebuild and redeploy the already selected current release over SSH:

```bash
cd /opt/obsidian-web/current
bash docker/deploy.sh
```

This command deploys the code in that release directory; it does not fetch a newer
commit. It also runs migrations and the usual deployment checks.

The deployment builds a Docker image. Its Node stage runs `npm ci` followed by
`npm run build`, and the PHP image receives the resulting `public/build` files.
The asset verifier runs during image construction and again against the built
image before the running app is replaced. Any build or verification failure
stops deployment. Deployment then clears Laravel's config/view caches and prints
the effective Vue flags.

Docker may reuse a successful build layer when its inputs are unchanged. Changes
to resources, the lockfile, package configuration, or Vite configuration invalidate
the relevant layer automatically. A host `public/build` folder is excluded from
the image, so local compiled files cannot overwrite the Docker build output.

Do not run npm inside the production PHP container: Node is in the separate build
stage. For a local checkout with the package.json-required Node/npm versions:

```bash
npm ci
npm run build
php scripts/verify-frontend-assets.php
```

Changing the example environment files does not update the shared server file.
Keep step 2's settings in `/opt/obsidian-web/shared/.env.docker` across deployments.

## 5. Verify in the browser

Reload the dashboard after deployment. Its page source should include
`data-vue-page="dashboard"` and `/build/assets/` URLs. Open Actions, then Edit,
and confirm the Vue popup and dialog appear.

If flags and file verification pass but the page still fails, inspect the browser
Network and Console panels for failed `/build/assets/` requests or JavaScript
errors. Record the failing URL and HTTP status. A missing chunk in an old open tab
may need a full reload after deployment; persistent failures need investigation
of asset serving, proxy settings, or the application error.

See [deployment setup](github-actions-deployment.md) for the full VPS workflow.
