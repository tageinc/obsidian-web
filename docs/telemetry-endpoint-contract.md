# OB-5: ESP32 Telemetry Ingestion – Duplicate Detection & Endpoint Contract

## Summary

Added server-side bounded duplicate detection to `/api/log` so that identical telemetry
(POST `application/x-www-form-urlencoded`, same `serial_no` + `data` payload) arriving
within the dedup window returns **409** instead of being silently accepted as success.
The device firmware can observe 409 and avoid false-positive "success" for its network retry.

## Changed Files (exact list)

| # | File | Action | Purpose |
|---|------|--------|---------|
| 1 | `app/Services/TelemetryDuplicateDetector.php` | **Created** | Redis `Cache::add` sliding-window dedup keyed on md5(serial_no + data), TTL = `devices.telemetry_freshness_seconds`. Falls through (no crash) if Redis is unavailable. |
| 2 | `app/Http/Controllers/Api/DeviceLogController.php` | **Modified** | Inserted `isDuplicate()` call between schema validation + device lookup and the telemetry write. On duplicate → returns 409 JSON with `retry_after`. Imports updated (`TelemetryDuplicateDetector`, `Config`). |
| 3 | `tests/Feature/TelemetryDedupTest.php` | **Created** | Feature tests: first-arrival 200/write, duplicate 409/no-write, retry_after present, different serial/payload not-duplicate, degraded cache fallback, unregistered-device 422, route exists on POST. |
| 4 | `docs/telemetry-endpoint-contract.md` | **Created** | This document: full endpoint contract, auth boundary, firmware retry guidance. |

## Endpoint Contract (`POST /api/log`)

### Request
- Content-Type: `application/x-www-form-urlencoded`
- Form fields: `serial_no` (string ≤255), `data` (JSON object as string ≤16384 chars)

### Responses

| Status | Body | Meaning | Device should |
|--------|------|---------|---------------|
| **200** | `{"msg":"success"}` | New telemetry accepted, written to DB. | Continue normal cadence. |
| **409** | `{"msg":"duplicate","retry_after":N}` | Identical payload within dedup window (default 600 s). No write performed. | Treat as "already seen"; retry per `retry_after` or next scheduled cycle. **Not an error to alert on.** |
| **422** | `{"msg":"invalid telemetry"}` | Missing/invalid fields, invalid JSON, null-only report. | **Do not retry** (non-retryable 4xx). Check firmware payload format. |
| **422** | `{"msg":"device is not registered"}` | Device identity is not registered. | **Do not retry** — device must complete registration flow first. |

> Note: unregistered-device returns 422 with `{"msg":"device is not registered"}`. This is a non-retryable condition.

### Dedup Window
- Config: `DEVICE_TELEMETRY_FRESHNESS_SECONDS` (env), default **600 seconds**.
- Identical to the freshness window used by `DeviceCommunicationStatus::freshnessCutoff()` — telemetry accepted within this window is treated as "online".
- Keyed on `md5(serial_no) . ':' . md5(data)` in Redis.

### Authentication Boundary
- `/api/log` has **no Sanctum auth** and no CSRF middleware (public IoT endpoint).
- Device identity is proven only by registration in the `devices` table (`serial_no`).
- Per-IP throttle: `throttle:device-telemetry` (default 1000 req/min per IP).
- Per-serial limiter: also partitioned via config (`devices.telemetry_per_minute`, default 1000/min).

### Backward Compatibility Guarantees
- Same request envelope (`POST`, form fields, JSON-as-string `data`).
- 200 body unchanged for valid new telemetry.
- 422 body unchanged for invalid payloads and unregistered devices.
- If Redis is unavailable, duplicate detection **falls through** (graceful degradation) — normal writes proceed; no outage caused by dedup failure.
- No migration changes to existing tables. `(serial_no, created_at)` composite index already defined in `2026_09_20_000000_create_device_application_tables.php`.

## Firmware Contract Requirements

For the 409 behavior to work correctly with ESP32 firmware:

1. **Observe HTTP status** before parsing response body — 409 means "don't write again."
2. **Do not treat 409 as an error** — it's a normal part of retry-aware cadence, not a fault condition.
3. **Retry after `retry_after` seconds or next scheduled cycle** — the payload was already written by the first arrival.
4. **Payload stability** — if firmware randomizes any field between retries (timestamps, IDs), dedup will not match. Ensure identical payloads are used for retry attempts of the same reading.
5. **4xx = do not retry** — invalid or unregistered-device responses should trigger a configuration/registration check, not exponential backoff to the same endpoint.

## Test Command

```bash
php vendor/phpunit/phpunit/phpunit --filter TelemetryDedupTest --do-not-cache-result
```

Tests use SQLite `:memory:` with explicit schema creation; no production data touched.
