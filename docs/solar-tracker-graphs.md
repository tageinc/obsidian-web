# Solar Tracker Graph Time Policy

Solar Tracker charts use each telemetry row's `updated_at` timestamp as the
recorded time. The API returns its ISO-8601 timestamp and epoch milliseconds;
the browser plots the epoch value on a linear time axis, so sparse readings keep
their real spacing and readings remain chronological.

All chart labels use the `America/Los_Angeles` timezone. Axis ticks show a
12-hour clock with AM/PM. Multi-day tick labels and tooltips also include the
date, while tooltips include the Pacific timezone abbreviation (PST or PDT).
This keeps the two 1:30 AM readings at the daylight-saving fall transition
distinguishable. Range controls filter by elapsed time from the newest reading,
not by a count of samples or an integer hour-offset label.

Charts display the most recent 9,000 individual telemetry rows in chronological
order, with record ID as the tie-breaker. Every reading retains its original
timestamp and value, including duplicate timestamps; no averaging, bucketing,
resampling, or curve smoothing is applied. The sensor graph plots PS1 and PS2
separately rather than the device-reported `ps_avg`. Temperature and motor speed
also use their raw recorded values. Lines connect readings with straight segments;
missing metric values remain null gaps and are never presented as zero. Empty
ranges show an explicit no-telemetry message. Rows without a recorded timestamp
cannot be positioned and are excluded.

Graph responses return only `graph` with `points`; the legacy `tempAverages`,
`psAverages`, and `motorAvgs` arrays have been removed. Each point includes the
source record `id`, `timestamp`, `epoch_ms`, `label`, `temp`, `ps1`, `ps2`, and
`motor_speed`. The existing current-status fields (including the device-reported
`ps_avg`) remain available independently of the graph.

## Local sample history

`DevelopmentDataSeeder` creates the demo Solar Tracker for
`andre.troncoso@tezca.net` and runs `SolarTrackerLogSeeder`. This adds seven days
of randomized readings at five-minute UTC intervals (2,017 samples), with
Pacific day/night sensor and temperature patterns plus varying motor speeds.
These are synthetic readings for device `202600000001` only. Existing readings
and account passwords are preserved; repeating the seed fills missing time slots.
Both seeders are restricted to `local` and `testing` environments.

For the local Docker installation:

```sh
docker compose --env-file .env.docker exec -T --user www-data -e APP_ENV=local app php artisan db:seed --class=DevelopmentDataSeeder --force
```
