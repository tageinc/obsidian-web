# Deploy from GitHub Actions

The `Deploy VPS` workflow uploads the exact main-branch commit over SSH, builds
the Docker image on the VPS, starts MySQL and the app, runs migrations, and checks
the login page. It runs on pushes to `main` or manually from the Actions tab.
Concurrent deployments are serialized. No GitHub credentials are stored on the VPS.

## Prepare the VPS once

Install Docker Engine and the Compose plugin for your VPS operating system.
For Ubuntu, use [Docker's official apt installation steps](https://docs.docker.com/engine/install/ubuntu/)
and [Caddy's Ubuntu installation instructions](https://caddyserver.com/docs/install#debian-ubuntu-raspbian).
Skip installation if your VPS image already includes them.
Confirm `docker compose version` works. The commands below assume a Linux VPS
with an existing SSH administrator and a dedicated `deploy` account:

```sh
sudo adduser --disabled-password --gecos '' deploy
sudo usermod -aG docker deploy
sudo install -d -o deploy -g deploy -m 750 /opt/obsidian-web
sudo install -d -o deploy -g deploy -m 700 /opt/obsidian-web/shared
sudo install -d -o deploy -g deploy -m 700 /home/deploy/.ssh
```

Docker group membership grants administrative control of the host. Use a
dedicated deployment key rather than your personal SSH key. Generate it on your
computer (use an empty passphrase for this unattended key):

```sh
ssh-keygen -t ed25519 -f github_obsidian_deploy -C github-obsidian-deploy
```

Put the contents of `github_obsidian_deploy.pub` in
`/home/deploy/.ssh/authorized_keys`. Set ownership to `deploy:deploy` and mode 600.
Verify a new SSH connection as `deploy` can run `docker info`.

Create `/opt/obsidian-web/shared/.env.docker` using `.env.docker.example` from this
repository. Set `APP_URL=https://obsidian.tezca.net`, `APP_BIND=127.0.0.1`, and
distinct strong database passwords. Use the existing site's `APP_KEY` when
migrating data. For a new installation, generate a key on the VPS with:

```sh
docker run --rm php:8.3-cli php -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'
```

Paste that value into `APP_KEY`. Set the environment file's owner to `deploy` and
permissions to 600. Keep credentials out of Git. Configure real SMTP settings if
you need verification emails; the example's log mailer does not deliver email.

## GitHub secrets

In the repository, open **Settings → Secrets and variables → Actions** and add
these repository secrets (or environment secrets for a `production` environment):

| Secret | Value |
| --- | --- |
| `VPS_HOST` | VPS public IP or SSH hostname |
| `VPS_USER` | `deploy` |
| `VPS_PORT` | SSH port, usually `22` (optional) |
| `VPS_SSH_KEY` | Full contents of the dedicated private key `github_obsidian_deploy` |
| `VPS_KNOWN_HOSTS` | Verified SSH known_hosts entry for the VPS |

Obtain the host-key entry with `ssh-keyscan -p 22 YOUR_VPS_IP`. Verify its
fingerprint against `ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub` in the
provider's trusted VPS console before saving it. Use the same hostname/IP and
port as the workflow. Do not disable host-key checking.

## HTTPS and DNS

Point the `obsidian.tezca.net` A record at the VPS. Any AAAA record must also point
at this VPS. Allow inbound TCP 80 and 443, plus your SSH port, in the VPS firewall.
Keep database port 3306 and app port 8080 private.

Install Caddy on the VPS host and configure this site in `/etc/caddy/Caddyfile`:

```caddyfile
obsidian.tezca.net {
    reverse_proxy 127.0.0.1:8080
}
```

Caddy obtains and renews HTTPS certificates when DNS and ports are ready. Before
using the site, configure Laravel to trust the host proxy by setting
`TRUSTED_PROXIES` in the server environment file to the Docker bridge gateway IP
seen by the app. After the first deployment, obtain it with:

```sh
docker network inspect obsidian-web_default --format '{{(index .IPAM.Config 0).Gateway}}'
```

Add `TRUSTED_PROXIES=THE_GATEWAY_IP` to the environment file, then rerun the
workflow. Validate and reload Caddy using `sudo caddy validate --config
/etc/caddy/Caddyfile` and `sudo systemctl reload caddy`.

## First deployment and updates

Push the workflow to GitHub, then use **Actions → Deploy VPS → Run workflow** on
`main`. Subsequent pushes to `main` deploy automatically. The app must have its
environment file before the first run. For migration from existing hosting,
import the database and uploads before enabling public traffic.

This is a single-server deployment with a brief restart, not a zero-downtime
rollout. Migrations run automatically and must be backward-compatible. Back up
the database and storage volumes before schema-changing releases. A failed
migration or smoke check fails the workflow but does not automatically revert
the running containers or database.

Releases remain under `/opt/obsidian-web/releases/COMMIT_SHA`. `current` identifies
the most recent successful deployment. All releases share the same Compose
project name and persistent volumes. To redeploy an earlier release, SSH into
its directory and run `bash docker/deploy.sh`; review database compatibility
first, since this does not undo migrations. Never run `docker compose down -v`
on production unless you intend to delete its data.
