#!/usr/bin/env bash
set -euo pipefail
export LC_ALL=C

cd "$(dirname "$0")/.."
command -v crontab >/dev/null || {
    echo 'Install and enable cron on the VPS before deploying (see docs/scheduler.md).' >&2
    exit 1
}

cron_error=$(mktemp)
cron_file=$(mktemp)
trap 'rm -f "$cron_error" "$cron_file"' EXIT

if existing=$(crontab -l 2>"$cron_error"); then
    :
elif ! grep -q 'no crontab for' "$cron_error"; then
    cat "$cron_error" >&2
    exit 1
fi

# Replace only this application's managed entry. Preserve unrelated host jobs.
if [ -n "$existing" ]; then
    printf '%s\n' "$existing" | sed '/ # obsidian-web-laravel-scheduler$/d' >"$cron_file"
fi
cat docker/obsidian.cron >>"$cron_file"
crontab "$cron_file"
echo 'Installed one Obsidian Laravel scheduler cron entry for the current user.'
