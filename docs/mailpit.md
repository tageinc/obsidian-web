# Local email testing with Mailpit

Mailpit captures SMTP messages in a browser inbox instead of delivering them to
recipients. This follows TAGCSOFT's local mail workflow, using different host
ports so the two applications can run together.

`APP_ENV=local` automatically selects the dedicated `mailpit` mailer, even if
`MAIL_MAILER` names a real provider or the log mailer. All other environments
continue to use `MAIL_MAILER`. The local transport has no SMTP credentials or TLS
and uses separate `MAILPIT_HOST`/`MAILPIT_PORT` settings.

## Docker application

Complete the local Docker setup in [README](../README.md), including the private
`.env.docker`, app key, database passwords, and migrations. Then run:

```sh
docker compose --env-file .env.docker -f compose.yaml -f compose.mailpit.yaml up -d --build --wait
docker compose --env-file .env.docker -f compose.yaml -f compose.mailpit.yaml exec -T --user www-data app php artisan config:clear
```

Open [the Obsidian inbox](http://localhost:8026). The override sets `APP_ENV=local`
and directs the local mailer to `mailpit:1025`. Use it only for a local/test installation. The regular
`docker/deploy.sh` does not select this override or change production mail delivery.

The inbox and host SMTP listener bind to `127.0.0.1`. Defaults are UI port `8026`
and SMTP port `1026`; TAGCSOFT uses `8025` and `1025`. Set `MAILPIT_UI_PORT` or
`MAILPIT_SMTP_PORT` in `.env.docker` if needed. Container-to-container SMTP always
uses `mailpit:1025`, even if the host port changes.

Mailpit has no persistent volume; treat captured messages as disposable. No SMTP
relay or forwarding is configured. For Docker and port details, see the
[official Mailpit documentation](https://mailpit.axllent.org/docs/install/docker/).

## PHP running directly on your computer

Start just Mailpit using the same local Docker configuration:

```sh
docker compose --env-file .env.docker -f compose.yaml -f compose.mailpit.yaml up -d --wait mailpit
```

In the local application's `.env`, set:

```dotenv
APP_ENV=local
MAILPIT_HOST=127.0.0.1
MAILPIT_PORT=1026
```

Run `php artisan config:clear`. Use the selected host SMTP port if customized.
Preserve the application's other environment values and use a valid sender
address, such as `local@example.test`.

## Verify a workflow

Use a local test account to request a password reset or resend a verification
email, then inspect it in the inbox. Test using disposable data. These flows use
their existing delivery paths; Mailpit does not change authorization or queueing.

Application-update emails use the dedicated database mail queue. After an
authorized local workflow queues a test message, process one bounded batch with:

```sh
docker compose --env-file .env.docker -f compose.yaml -f compose.mailpit.yaml exec -T --user www-data app php artisan mail:work
```

See [application email delivery](application-email.md) for queue prerequisites
and the scheduler. Do not add another permanent worker or trigger real device
commands just to generate test mail.

If the inbox stays empty, check that the effective mailer is `mailpit`, clear cached
configuration, inspect `logs --tail=50 mailpit`, and check whether the message is
queued. A Docker app must use `mailpit`, whereas host PHP uses `127.0.0.1`.

## Return to the normal mail configuration

Recreate the app without the override, then clear cached configuration:

```sh
docker compose --env-file .env.docker -f compose.yaml up -d --force-recreate --no-deps app
docker compose --env-file .env.docker -f compose.yaml exec -T --user www-data app php artisan config:clear
docker compose --env-file .env.docker -f compose.yaml -f compose.mailpit.yaml stop mailpit
```

The base Docker configuration uses `APP_ENV=production` and therefore restores
`MAIL_MAILER`. Host PHP continues to use Mailpit while `APP_ENV=local`; when
configuring a non-local deployment, set the appropriate environment and provider
settings and clear configuration again. Do not remove database/storage volumes
to disable Mailpit.
