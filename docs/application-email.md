# App activity and email preferences

Obsidian adapts TAGCSOFT's `AppUpdateDelivery`, dedicated mail job and shared
outbound pacing pattern. An application update means an event in the app, such
as a device communication-status change. This does not create a release-announcement
broadcast or email users for every deployment.

## Device status notifications

The hourly `device:check-status` command records an activity for the device owner
whenever the newly classified communication status differs from its saved
status. This includes going offline, coming online, recovering from an alert,
low voltage, theft/vandalism, and other reported telemetry states. The device
must be active, have an existing location status record, and have an owner.
An unchanged status creates no additional activity or email. Email is also
queued when the owner's **Receive app activity emails** preference is enabled
and the owner has a valid email address. An invalid email address does not
prevent the activity from appearing in the bell.

The existing low-voltage and theft/vandalism messages retain their content.
Other transitions use `DeviceStatusChangedMail`, which includes the previous
and new status and the device's identifying information. This concerns
communication status, not edits to a device's name, address, or lifecycle state.

Checks still run at the top of every hour; the mail worker runs every minute.
The check compares the latest classified status with the last saved status, so
intermediate changes between checks are not individually emailed. Missing or
stale telemetry classifies as offline; fresh telemetry uses its reported state
or online when no state is reported. Normal queue pacing and retries can add
delivery time.

## User Settings and the activity bell

The navigation gear opens **User Settings**. Its **Receive app activity emails**
checkbox defaults to checked for existing and new users. Settings are stored
in a one-to-one `UserSettings` record in `user_settings`; a user without a saved
record receives the same default. Saving unchecked stops app activity emails,
including already queued messages whose worker has not sent them yet.
Verification, password-reset, and other account-access emails keep their
existing behavior. Turning email off never disables the activity bell.

User Settings also offers **Adaptive**, **Light**, and **Dark** theme modes.
Adaptive is the default: Light from 06:00 until 18:00 and Dark from 18:00 until
06:00 in the user's local time.
The saved theme is independent of email delivery: changing either preference
preserves the other, and saving a theme creates no activity or email.
Adaptive's automatic local-time appearance changes also create no events.

This account-wide preference applies to all of the user's active devices.
The legacy `devices.status_notification` field and its compatibility endpoints
remain stored and callable, but that field no longer controls app activity
emails. New device registration does not require a separate notification opt-in.

The bell follows TAGCSOFT's notification design: a red unread badge capped at
`9+`, a viewport-constrained dropdown, unread dots and stronger titles, and
local timestamps. It loads on mount and when opened, with no polling. Pages
contain 20 newest-first activities, with cursor-based loading on scroll or
**Load more**. Selecting an activity marks it read before opening its device;
failed marking leaves it unread and does not navigate. Opening the bell alone
does not mark items read. Each item can be dismissed; **Clear all** clears the
signed-in user's feed. Settings and activity controls are also available in
the legacy layout through a separate lightweight frontend entry.

Activities use Laravel database notifications and belong to their recipient.
The Developer sees their own feed, not other users' notices. Browser and
external clients share the same [settings and activity API](external-api-keys.md).

## Delivery

`App\Services\AppUpdateDelivery::queue()` accepts already-authorized User models
and an `App\Mail\AppUpdateMail`. It deduplicates user IDs and creates one
`App\Jobs\SendAppUpdateMail` per recipient on the `app-updates` database
connection, `mail` queue. All device status-change emails use this path and
respect the recipient's User Settings. Authentication, verification,
password-reset and invitation messages keep their existing delivery path.

Each job resolves the recipient's current email. Deleted users and invalid
addresses are skipped. Device status emails also recheck current ownership,
device existence, active state and the latest user email preference before delivery.
The message contains the device information captured when the status changed. The worker sends
directly, without creating another queued mailable. To/CC/BCC values on a reused
mailable cannot expand the selected audience.

Jobs use the same database as application records. The status change, activity,
and eligible email job insert occur in one transaction, with a lock on the status
row: an enqueue failure rolls back the transition and activity, and an unchanged status does not enqueue
another email. Keep `queue.connections.app-updates.connection` at `null` and
`after_commit` at `false` to retain this guarantee. The queue row is invisible to
other database connections until commit. SMTP failure later retries the saved
job without rerunning the status transition or creating another activity.

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

Run outstanding migrations, including the `jobs`, `user_settings`, and
`notifications` tables and the additive `theme_mode` column on `user_settings`,
before starting the updated scheduler and application.
The existing deployment script already runs migrations
under the scheduler lock. No additional service or production provisioning is
needed. This change does not itself deploy or send a broadcast.

With `APP_ENV=local`, [Mailpit](mailpit.md) automatically captures email instead
of the configured provider. Start its local service and run the scheduler
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
override `shouldSendTo(User $recipient)` if record access can change while queued.
The shared delivery job checks the user email preference independently. Pass
only recipients selected by the authorized workflow:

```php
app(\App\Services\AppUpdateDelivery::class)->queue($authorizedUsers, $mail);
```

Record the corresponding database activity once at the originating event,
independently of email opt-out. For transactional mutations, record it and queue
eligible email inside the same default-database transaction. Delivery retries
and rendering must never create activities. Keep mail payloads small and free of passwords/tokens. Do not use
`ShouldQueue` or `Mail::queue()` as an additional dispatch layer. TAGCSOFT's
company rules and role-based authorization are not imported; Obsidian retains
its owner and Developer authorization boundaries.

`AppUpdateMailTest`, `AppUpdateMailSchedulerTest`, `DeviceCommunicationsTest`
and the existing authentication tests cover queue execution, encryption,
recipient isolation, access/preferences, retries, pacing, transaction rollback,
all communication-status transitions, unchanged-status deduplication and the
unchanged account email flows.
