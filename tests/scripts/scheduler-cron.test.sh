#!/usr/bin/env bash
set -euo pipefail

repo=$(cd "$(dirname "$0")/../.." && pwd)
fixture=$(mktemp -d)
trap 'rm -f "$fixture/bin/crontab" "$fixture/crontab" "$fixture/before" "$fixture/deny"; rmdir "$fixture/bin" "$fixture"' EXIT
mkdir "$fixture/bin"
export CRON_TEST_STATE="$fixture"
export PATH="$fixture/bin:$PATH"

# Test the installer without touching the machine's actual crontab.
cat >"$fixture/bin/crontab" <<'STUB'
#!/usr/bin/env bash
set -eu
if [ "$1" = '-l' ]; then
    if [ -f "$CRON_TEST_STATE/deny" ]; then
        echo 'crontab: permission denied' >&2
        exit 1
    fi
    if [ ! -f "$CRON_TEST_STATE/crontab" ]; then
        echo 'no crontab for test-user' >&2
        exit 1
    fi
    cat "$CRON_TEST_STATE/crontab"
else
    cat "$1" >"$CRON_TEST_STATE/crontab"
fi
STUB
chmod +x "$fixture/bin/crontab"

# First installation on an account with no crontab.
bash "$repo/docker/install-cron.sh"
cmp "$repo/docker/obsidian.cron" "$fixture/crontab"

# Preserve comments, environment and unrelated jobs; replace duplicate managed
# entries instead of adding another scheduler on every deployment.
cat >"$fixture/crontab" <<'CRON'
# Existing maintenance
MAILTO=admin@example.test
30 2 * * * /usr/local/bin/backup
* * * * * obsolete-command # obsidian-web-laravel-scheduler
* * * * * another-obsolete-command # obsidian-web-laravel-scheduler
CRON
bash "$repo/docker/install-cron.sh"
test "$(grep -c ' # obsidian-web-laravel-scheduler$' "$fixture/crontab")" -eq 1
grep -Fxq '# Existing maintenance' "$fixture/crontab"
grep -Fxq 'MAILTO=admin@example.test' "$fixture/crontab"
grep -Fxq '30 2 * * * /usr/local/bin/backup' "$fixture/crontab"
if grep -q 'obsolete-command' "$fixture/crontab"; then
    echo 'Obsolete managed cron entry was not replaced.' >&2
    exit 1
fi

# Reinstalling must not change the resulting crontab.
cp "$fixture/crontab" "$fixture/before"
bash "$repo/docker/install-cron.sh"
cmp "$fixture/before" "$fixture/crontab"

# A failed read must never be mistaken for an empty crontab and overwrite it.
touch "$fixture/deny"
if bash "$repo/docker/install-cron.sh"; then
    echo 'Expected a failed crontab read to stop installation.' >&2
    exit 1
fi
cmp "$fixture/before" "$fixture/crontab"

echo 'Scheduler cron installation tests passed.'
