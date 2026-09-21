# Application update emails

Obsidian adapts TAGCSOFT's `AppUpdateDelivery`, dedicated mail job and shared
outbound pacing pattern. An application update means an event in the app, such
as a device alert. This does not create a release-announcement broadcast or
email users for every deployment.

## Delivery

`App\Services\AppUpdateDelivery::queue()` accepts already-authorized User models
and an `App\Mail\AppUpdateMail`. It deduplicates user IDs and creates one
`App\Jobs\SendAppUpdateMail` per recipient on the `app-updates` database
connection, `mail` queue. Existing low-voltage and theft/vandalism emails use
this path. Their content, status-change rules and device notification setting
are preserved. Authentication, verification, password-reset and invitation
messages keep their existing delivery path.

Each job resolves the recipient's current email. Deleted users and invalid
addresses are skipped. Device alerts also recheck current ownership, device
existence and the notification setting before delivery. The message contains
the device information captured when the alert occurred. The worker sends
directly, without creating another queued mailable. To/CC/BCC values on a reused
mailable cannot expand the selected audience.

Jobs use the same database as application records. The status change and job
insert occur in one transaction, with a lock on the status row: an enqueue
failure rolls back the transition, and an unchanged status does not enqueue
another alert. Keep `queue.connections.app-updates.connection` at `null` and
`after_commit` at `false` to retain this guarantee. The queue row is invisible to
other database connections until commit. SMTP failure later retries the saved
job without rerunning the status transition.

## Worker and retries

The existing single cron invokes `schedule:run`; the Kernel now runs `mail:work`
every minute after any due status check. It processes only `app-updates/mail`,
stops when empty, and stops between jobs after 45 seconds or 100 jobs. Each job
has a 30-second timeout; SMTP has a 20-second network timeout. The Docker image
includes PCNTL for queue timeout enforcement. Native Windows PHP cannot enforce
PCNTL timeouts; use the Docker runtime for the worker.

`withoutOverlapping(5)` and the existing host scheduler lock prevent concurrent
scheduled workers on this single-server deployment. A run may finish its last
job beyond the 45-second target; a skipped cron tick is processed on the next
minute. Do not add a second cron or long-running worker for this queue.

- `MAIL_PER_MINUTE=30` controls the app-wide outbound limit. The existing file
  cache stores the namespaced per-environment counter, with a one-minute window.
  Tests use the array cache. No Redis queue, cache-default or session change is
  required.
- Rate-limited jobs are released until the window resets, without spending an
  exception allowance. SMTP/transport exceptions retry with 60, 120, 300 and
  then 600 seconds of backoff.
- Delivery stops after five exceptions or 24 hours from enqueueing. The failed
  job is retained in the existing `failed_jobs` table. Queue attempts use an
  integer column so repeated pacing releases cannot overflow a tiny integer.
- Delivery is at least once: a process crash after SMTP accepts a message but
  before the queue acknowledges it can produce a duplicate. Database transition
  deduplication cannot guarantee exactly-once SMTP delivery.

Pending and failed job payloads are encrypted using the existing `APP_KEY`.
Keep that key stable while jobs exist; do not put credentials in mail data.
Treat failed-job exceptions and mailer logs as private operational records.
The `log` mailer records message contents and is for local development only;
tests use the in-memory `array` mailer.

## Setup, operations and rollback

Run the new `2026_09_21_000001_create_jobs_table` migration before starting the
updated scheduler. The existing deployment script already runs migrations
under the scheduler lock. No additional service or production provisioning is
needed. This change does not itself deploy or send a broadcast.

For local Docker development, use the existing log mailer and run the scheduler
as described in [scheduler setup](scheduler.md). For one bounded mail-only run:

```sh
docker compose --env-file .env.docker exec -T --user www-data app php artisan mail:work
```

Read-only operational checks:

```sh
docker compose --env-file .env.docker exec -T --user www-data app php artisan schedule:list
docker compose --env-file .env.docker exec -T --user www-data app php artisan queue:failed
```

Worker output goes to `storage/logs/mail-scheduler.log`; include it in log
rotation. Monitor pending jobs and failed jobs so a stopped scheduler or SMTP
outage is visible. After fixing a failure, `php artisan queue:retry <uuid>`
retries that specific job within its original 24-hour deadline; review the
alert's relevance first. An expired job needs a fresh, reviewed dispatch through
`AppUpdateDelivery`, because retrying it does not extend its fixed deadline.
Never retry all historical alerts as routine maintenance. Normal scheduler
runs retry transient failures automatically.

For rollback, stop the scheduler under the existing deployment lock and drain
or review the mail queue using the new code before restoring older code. Keep
the additive `jobs` table and stable application key. Do not roll back its
migration while pending jobs exist; older code cannot deserialize these jobs.

## Adding another application event

Extend `AppUpdateMail`, implement the normal mailable `build()` method, and
override `shouldSendTo(User $recipient)` if record access or preferences can
change while queued. Pass only recipients selected by the authorized workflow:

```php
app(\App\Services\AppUpdateDelivery::class)->queue($authorizedUsers, $mail);
```

For transactional mutations, call this inside the same default-database
transaction. Keep mail payloads small and free of passwords/tokens. Do not use
`ShouldQueue` or `Mail::queue()` as an additional dispatch layer. TAGCSOFT's
company rules, Activity feed and user email-preference columns are not imported;
Obsidian retains its device notification preference.

`AppUpdateMailTest`, `AppUpdateMailSchedulerTest`, `DeviceCommunicationsTest`
and the existing authentication tests cover queue execution, encryption,
recipient isolation, access/preferences, retries, pacing, transaction rollback,
unchanged-status deduplication and the unchanged account email flows.
