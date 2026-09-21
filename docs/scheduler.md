# Laravel scheduler

Obsidian follows TAGCSOFT's host-cron pattern: one Linux host cron invokes
Laravel's `schedule:run` every minute inside the app container. Laravel decides
which commands are due using `app/Console/Kernel.php`. There are no per-command
host cron jobs and no second scheduler container.

The current schedule is `device:check-status` hourly, at minute zero, in the
application timezone (UTC). `withoutOverlapping()` prevents another execution
while the command's previous execution holds its Laravel cache lock. Add future
recurring Artisan commands to the Kernel, not to the host's crontab. Diagnostic
and one-time maintenance commands remain manual; `device:generate-info` is retired.

## VPS installation

On the Ubuntu/Debian VPS, install and enable cron once as an administrator:

```sh
sudo apt-get update
sudo apt-get install -y cron
sudo systemctl enable --now cron
```

Run deployments as the existing `deploy` account. `docker/deploy.sh` installs
the following entry into that account's crontab after a successful migration and
login check (the source is `docker/obsidian.cron`):

```cron
* * * * * /bin/bash /opt/obsidian-web/current/docker/schedule.sh >> /opt/obsidian-web/shared/scheduler.log 2>&1 # obsidian-web-laravel-scheduler
```

`docker/install-cron.sh` replaces only its tagged entry, so repeated deployments
leave exactly one managed scheduler entry and preserve unrelated jobs. For an
already deployed release, install it with:

```sh
cd /opt/obsidian-web/current
bash docker/install-cron.sh
crontab -l
```

When migrating from old per-command cron entries, remove those Obsidian entries
from the same account's crontab. Do not run another `schedule:run` cron or
`schedule:work` process against this deployment.

The wrapper runs Artisan as `www-data`, uses the deployed `.env.docker`, and
follows the `current` release symlink. Its shared `flock` lock skips a tick while
another tick or deployment is active. Deployments wait for a running tick before
changing containers. Skipped ticks are not replayed; the next minute evaluates
the schedule normally. A deployment spanning an hourly boundary can therefore
skip that hour's check.

Cron-wrapper and scheduler output go to `/opt/obsidian-web/shared/scheduler.log`.
The status command's stdout/stderr go to `storage/logs/device-status-scheduler.log`
in the shared application storage volume. Include both files in normal log
rotation. Application exceptions retain the configured Laravel log destination.
Read-only checks:

```sh
docker compose --project-name obsidian-web --env-file .env.docker exec -T --user www-data app php artisan schedule:list
tail -n 50 /opt/obsidian-web/shared/scheduler.log
docker compose --project-name obsidian-web --env-file .env.docker exec -T app tail -n 50 storage/logs/device-status-scheduler.log
```

## Local Windows development

Windows does not install the Linux host crontab. To run the same Kernel schedule
locally, keep one Laravel worker in a terminal while Docker is running:

```sh
docker compose --env-file .env.docker exec --user www-data app php artisan schedule:work
```

Stop it with Ctrl+C. This is a local alternative to cron, not an additional
production scheduler. It uses the same hourly cadence and does not replay missed
runs. Local `.env.docker.example` uses the log mailer for notification output.

## Verification

`php artisan test --filter=SchedulerTest` checks UTC due times, dispatch and
overlap handling without executing device commands. On a system with Bash,
`bash tests/scripts/scheduler-cron.test.sh` checks first installation, repeated
installation, preservation of unrelated cron entries and failed-read handling
using a fake crontab; it never modifies the host's real crontab.
