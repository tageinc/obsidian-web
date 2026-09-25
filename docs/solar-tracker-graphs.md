# Solar Tracker Graph Time Policy

## Device page organization

The Vue 3 device page opens on Overview: the latest reported state, temperature,
motor speed, confirmed control mode, sensor readings, and device details. The
reading timestamp makes freshness explicit; devices without telemetry show
missing values rather than placeholder zero readings.

History shows one selected measurement at a time: temperature, panel sensors
(PS1 and PS2), PDS, PS average, or motor speed. PDS and PS average use the
recorded `pds` and `ps_avg` values; the reported average is not recalculated
from PS1 and PS2. Each new measurement has the same raw scatter points and
independent linear trend, and missing values remain absent rather than zero.

The time-period controls follow TAGCSOFT's Project Manager Labor tab: the range
heading and compact previous/next/Today controls sit above the Filters rows.
View and its period selector occupy the first row; Measurement and Clear occupy
the second. The default is the last 24 elapsed hours ending now, with a single
Ending at datetime. Week and Month show one date selector. Custom span shows
Pacific-time From and To datetimes, while Available history uses all loaded
readings without date inputs. Weeks start Monday; months follow the calendar.
Previous/next advances a whole period; Today returns to the current period
without changing the View. These navigation controls are disabled for Custom
span and Available history. Clear resets the range to the last 24 hours ending
now and preserves the chosen measurement. Invalid or reversed dates show
validation instead of a chart. Date entry supports seconds; skipped Pacific
spring-forward times are rejected, and repeated fall-back boundary times include
both occurrences (earlier From, later To).
Its chart is created only while History is visible and released when leaving;
measurement and range selections survive section changes. Control has its own
section, with the same automatic/remote switch and manual command behavior.
The control component stays mounted across section changes so confirmed state
and pending requests remain intact. Switching sections sends no device commands.

The compact header, clear page context, white content surfaces, and separation
of tasks are grounded in TAGCSOFT's dashboard layout and developer workspace;
device-specific behavior remains Obsidian's. Section tabs support arrow keys,
Home, and End. Cards stack on small screens.

## Timestamp and raw-data behavior

Solar Tracker charts use each telemetry row's `updated_at` timestamp as the
recorded time. The API returns its ISO-8601 timestamp and epoch milliseconds;
the browser plots the epoch value on a linear time axis, so sparse readings keep
their real spacing and readings remain chronological.

All chart labels use the `America/Los_Angeles` timezone. Axis ticks show a
12-hour clock with AM/PM. Multi-day tick labels and tooltips also include the
date, while tooltips include the Pacific timezone abbreviation (PST or PDT).
This keeps the two 1:30 AM readings at the daylight-saving fall transition
distinguishable. The Vue datetime controls filter inclusively by the selected instants, and the
chart axis spans those bounds even when readings are sparse. Reloading resets
the range to the last 24 hours ending now. Calendar views follow Pacific time,
including daylight-saving changes. The legacy fallback retains its relative
ranges ending at the latest reading.

Charts display the most recent 9,000 individual telemetry rows in chronological
order, with record ID as the tie-breaker. Every reading retains its original
timestamp and value, including duplicate timestamps; no averaging, bucketing,
resampling, or curve smoothing is applied. The Panel sensors plot shows PS1 and PS2 separately; the PS average plot
shows the device-reported `ps_avg`. Temperature and motor speed
also use their raw recorded values. Each reading is a scatter point; readings
are not connected. Missing metric values remain null, are omitted from the plot
and the fit, and are never presented as zero. Empty
ranges show an explicit no-telemetry message. Rows without a recorded timestamp
cannot be positioned and are excluded.

Each measurement also has a separately labelled dashed linear regression line,
fitted by ordinary least squares to **every valid raw reading in the selected
range**. PS1 and PS2 get independent fits. Each observation has equal weight,
including readings with duplicate timestamps; no bucket averages replace the
samples. Changing the range recalculates the fit. The line stops at the earliest
and latest valid timestamp for that measurement, without extrapolating.

The regression uses elapsed hours from the earliest valid timestamp, centered
around the mean, for stable arithmetic with large epoch values. Consequently,
irregular sampling and daylight-saving transitions retain their actual elapsed
spacing. At least two readings at distinct timestamps are required; empty,
single-reading, and single-timestamp series show no regression line. A constant
measurement across distinct timestamps produces a horizontal fit.

Legends distinguish raw readings from fitted trends. Tooltips retain source
timestamps and reading IDs for the raw points; the fitted endpoints are not
reported as measured readings. The current-status values and remote controls
remain independent of the graphs.

`timeSeries.spec.js` and `tests/js/solar-tracker-graph.test.js` cover regression
math, irregular sampling, duplicates, missing data, degenerate fits, and range
changes for Vue and the legacy fallback. Component and desktop/mobile browser
checks cover measurement/range controls and accessible chart descriptions.

Graph responses return only `graph` with `points`; the legacy `tempAverages`,
`psAverages`, and `motorAvgs` arrays have been removed. Each point includes the
source record `id`, `timestamp`, `epoch_ms`, `label`, `temp`, `ps1`, `ps2`, `ps_avg`, `pds`, and
`motor_speed`. The existing current-status fields (including the device-reported
`ps_avg`) remain available independently of the graph.

## Local sample history

`DevelopmentDataSeeder` creates the demo Solar Tracker for
`andre.troncoso@tezca.net` and runs `DeviceLogSeeder`. This adds seven days
of randomized readings at five-minute UTC intervals (2,017 samples), with
Pacific day/night sensor and temperature patterns plus varying motor speeds.
These are synthetic readings for device `202600000001` only. Existing readings
and account passwords are preserved; repeating the seed fills missing time slots.
Both seeders are restricted to `local` and `testing` environments.
The Development Solar Tracker is placed in central Los Angeles at latitude
`34.0522`, longitude `-118.2437`. Repeating `DevelopmentDataSeeder` also updates
those coordinates on the existing demo device.

For the local Docker installation:

```sh
docker compose --env-file .env.docker exec -T --user www-data -e APP_ENV=local app php artisan db:seed --class=DevelopmentDataSeeder --force
```
