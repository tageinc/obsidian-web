# Proposed Redis boundary

Status: **workloads and local/test provisioning approved on 2026-09-21**. Production
provisioning, data migration and enabling Redis in production are not authorized
by this plan. Redis is an optional server optimization in the first increment,
not a browser state store or source of truth.

## Why these workloads

`Api/DeviceSoftwareController::version` queries the database for every firmware
or config version check. `LogRequests` performs repeated file-cache counter
operations, including an unused scan across route totals. These are concrete
bounded candidates that do not require changing authentication or device control.
Benchmark repeated version reads and metrics operations before/after; keep the
feature disabled if it adds overhead without a useful hit rate/latency reduction.

| Proposed workload | Stored data / key | TTL and invalidation | Unavailable Redis behavior |
| --- | --- | --- | --- |
| Firmware/config latest-version lookup | Only kind and version string; `obsidian:<env>:v1:software-version:<kind>:<sha256(prefix)>` | Positive results 60 seconds; no negative caching. Delete affected old/new-prefix keys after successful version create/update/delete and uploads; TTL bounds recovery from failed invalidation | Read authoritative DB and return the unchanged JSON/status contract; cache failures must not fail version/download requests |
| Anonymous route counters | Allowlisted route name + UTC minute/day count; `obsidian:<env>:v1:metrics:<route>:<bucket>` | Minute buckets 120 seconds; daily totals 48 hours. Atomic increment + expiry; no permanent cumulative keys | Skip metrics on failure; bounded connection/read latency only, never fail the actual API workflow |

Version caching does **not** cache configuration/firmware file contents,
descriptions, file paths, full records, missing-result logs, or credentials.
Downloads continue checking actual storage and DB records. No remote-control
command/status cache or stale telemetry fallback is introduced. An accepted
tradeoff is at most 60 seconds of version staleness after an unavailable
invalidation path; deployment approval must accept that bound.

Metrics contain no raw URL/query, IP, serial, user identity, request body or
response body. Remove existing full-URL logging while preserving useful
route/status/timing diagnostics. Metrics can be discarded; they are not billing,
audit or rate-limit records.

## Configuration and implementation rules

- Use a dedicated named Redis cache store/connection, not a global
  `CACHE_DRIVER=redis` switch. Keep current session, queue, limiter and scheduler
  lock backends unchanged; this avoids accidentally coupling login or cron to
  the optional cache service.
- Add an explicit enable flag, key prefix including application+environment+
  schema version, and bounded connect/read timeouts (initial targets 200 ms
  connect and 200 ms read, no automatic retry per request). Use a request-scoped
  availability result so one failed connection does not repeat for every metric.
- Use the PHP Redis extension in Docker, pin its compatible stable release and
  the Redis 8 image by reviewed version/digest in the implementation increment.
  Do not require a developer's host PHP to have Redis for ordinary unit tests.
  Laravel already has Redis configuration/provider hooks; it currently has no
  Redis service or client installed in the image.
- Local Redis is opt-in via a Compose test/development profile, on the private
  Compose network with **no published host port**, synthetic fixtures only,
  separate test namespace/database, and no production credentials. Starting it
  is part of the explicit local/test provisioning approval, not implicit here.
- Unit tests use fakes/array stores. Integration tests use a dedicated disposable
  Redis service and isolated namespace, never `FLUSHALL` on a shared instance.
- Production configuration belongs in server environment/secret management.
  No Redis URLs/passwords, APP_KEY, session material or backend environment
  values may enter `VITE_*`, frontend props, manifests, logs or documentation.
- Catch expected Redis transport failures around these two optional workloads;
  do not swallow database failures or application programming errors. Emit
  rate-limited generic failure codes without connection strings or exception
  payloads. Redis failure must not turn a missing DB record into a false success.

## Explicitly deferred workloads

| Workload | Reason / required follow-up |
| --- | --- |
| Redis sessions | Changes session availability, logout/migration behavior and stores sensitive session material; requires explicit data/security/operational approval |
| Redis queues/email | Currently synchronous; needs worker supervision, retry/idempotency/failed-job policy and permission for changed delivery behavior |
| Rate limiting | Security-sensitive fail-open/fail-closed decision and firmware availability consequences; retain current limits/backends until separately approved |
| Scheduler/distributed locks | Existing host flock + Laravel file-cache overlap handling works for one server; multi-server coordination is a separate deployment design |
| User/device graphs, dashboard data, control state | Authorization/freshness/location sensitivity and cache invalidation outweigh first-stage benefit; no approval to cache these |

## Tests and operational release checklist

Test cache hit/miss and matching legacy JSON, TTL expiry, prefix isolation,
old/new-prefix invalidation, upload invalidation after successful persistence,
missing versions, parallel counter increments, bounded metric expiry, disabled
mode, timeout/outage recovery and no secret logging. Check that failed uploads
do not publish new versions and Redis outages leave existing workflows usable.

Production enablement requires its own review of private connectivity, ACLs/TLS
where needed, memory limits/eviction suitable for disposable cache data,
monitoring (hit/miss, error/timeout, latency, memory, evictions), log redaction,
and the chosen version staleness bound. Disable the cache feature flag to roll
back immediately to DB reads and skipped optional metrics. No database data or
schema migration is required; remove expired cache keys only within the owned
namespace if cleanup is necessary.

References: [Laravel 8 Redis integration](https://laravel.com/docs/8.x/redis),
[Redis security guidance](https://redis.io/docs/latest/operate/oss_and_stack/management/security/).
