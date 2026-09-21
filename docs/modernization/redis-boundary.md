# Redis boundary and local validation

Status: **workloads and local/test provisioning approved on 2026-09-21**. Production
provisioning, data migration and enabling Redis in production are not authorized
by this plan. Redis is an optional server optimization in the first increment,
not a browser state store or source of truth.

## Why these workloads

Before this increment, `Api/DeviceSoftwareController::version` queried the database for every firmware
or config version check. `LogRequests` performed repeated file-cache counter
operations, including an unused scan across route totals. These are concrete
bounded candidates that do not require changing authentication or device control.
Benchmark repeated version reads and metrics operations before/after; keep the
feature disabled if it adds overhead without a useful hit rate/latency reduction.

| Implemented workload | Stored data / key | TTL and invalidation | Unavailable Redis behavior |
| --- | --- | --- | --- |
| Firmware/config latest-version lookup | Only kind and version string; `obsidian:<env>:v1:software-version:<kind>:<sha256(prefix)>` | Positive results 60 seconds; no negative caching. Delete affected old/new-prefix keys after successful version create/update/delete and uploads; TTL bounds recovery from failed invalidation | Read authoritative DB and return the unchanged JSON/status contract; cache failures must not fail version/download requests |
| Anonymous route counters | Allowlisted route name + UTC minute/day count; `obsidian:<env>:v1:metrics:<route>:<bucket>` | Minute buckets 120 seconds; daily totals 48 hours. Atomic increment + expiry; no permanent cumulative keys | Skip metrics on operational failure; socket timeouts bound connection/read latency, while DNS follows the resolver policy documented below |

Version caching does **not** cache configuration/firmware file contents,
descriptions, file paths, full records, missing-result logs, or credentials.
Downloads continue checking actual storage and DB records. No remote-control
command/status cache or stale telemetry fallback is introduced. An accepted
tradeoff is at most 60 seconds of version staleness after an unavailable
invalidation path, a concurrent read repopulating an invalidated key, or a raw
SQL update that bypasses model events. Deployment approval must accept that bound.
Model writes invalidate old/new prefixes only after the database transaction
commits; rolled-back writes leave the existing cache intact. Successful upload
model creation uses the same invalidation, without a second upload-specific cache.

Metrics contain no raw URL/query, IP, serial, user identity, request body or
response body. The old full-URL logs, permanent counters and unused cache scan
are removed. Optional aggregate counters replace them; disabled mode skips metrics.
Metrics can be discarded; they are not billing,
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
  The image installs phpredis 6.3.0. The opt-in service uses Redis 8.2.5 Alpine,
  pinned to an immutable manifest digest in `compose.yaml`.
- Local Redis is opt-in via a Compose test/development profile, on the private
  Compose network with **no published host port**. Integration tests use synthetic
  fixtures and a separate namespace/database, with no production credentials. Starting it
  is covered by the explicit local/test provisioning approval.
- Unit tests use fakes/array stores. Integration tests use a dedicated disposable
  Redis service and isolated namespace, never `FLUSHALL` on a shared instance.
- Production configuration belongs in server environment/secret management.
  No Redis URLs/passwords, APP_KEY, session material or backend environment
  values may enter `VITE_*`, frontend props, manifests, logs or documentation.
- Catch expected Redis transport failures around these two optional workloads;
  do not swallow database failures or application programming errors. Emit
  rate-limited generic failure codes without connection strings or exception
  payloads. Redis failure must not turn a missing DB record into a false success.

## Implemented configuration and commands

`config/redis-workloads.php` defines the master `REDIS_WORKLOADS_ENABLED` flag
(off by default), independent `REDIS_WORKLOADS_VERSIONS_ENABLED` and
`REDIS_WORKLOADS_METRICS_ENABLED` flags, namespace, fixed TTLs and route allowlist.
The dedicated `redis-workloads` cache store uses only the `workloads` connection.
Configure `REDIS_WORKLOADS_HOST`, `REDIS_WORKLOADS_PORT`,
`REDIS_WORKLOADS_PASSWORD` and `REDIS_WORKLOADS_DB` server-side.
No global cache, session, queue, rate-limit or scheduler setting changes.

Connect and each read have 200 ms timeouts. Native phpredis commands avoid
Laravel 8's implicit reconnect; phpredis command retries are disabled. A failed
connection is not attempted again within the same request. Unavailable service
diagnostics contain only `redis_workloads.unavailable`, limited to once per
minute per application's local file cache. Later requests can recover normally.
Socket connection/read latency is bounded; an outage does not promise zero delay.
DNS resolution is controlled by the operating-system resolver and is not bounded
by those socket timeouts. Use a reliable private resolver or fixed service address
and validate its failure timing before production enablement. DNS errors fall
back after resolution fails; they are not retried within the same request.
Recognized unavailable/authentication/resource states (including READONLY,
LOADING, OOM, MISCONF, MASTERDOWN and CLUSTERDOWN) also fall back safely. A failed
version-cache write still returns the authoritative version, and failed metrics
cannot replace a successful command response. WRONGTYPE and Lua/programming
errors remain visible instead of being mislabeled as cache outages.

Docker builds frontend assets from a clean `npm ci` using Node 24.15.0 and
npm 11.8.0, before copying only generated assets to the PHP image. The build
context excludes environment files, host dependencies and `public/build`.
The Redis profile has no published host port or persistent volume; it is limited
to 128 MB with disposable-key eviction. Existing application/database volumes
and mail settings are preserved. Ignored local environment settings explicitly
enable the approved workloads; committed defaults remain off.

```sh
docker compose --env-file .env.docker build --no-cache app
docker compose --env-file .env.docker --profile redis up -d --wait redis
docker compose --env-file .env.docker --profile redis run --rm --no-deps \
  -e APP_ENV=testing -e REDIS_WORKLOADS_INTEGRATION=1 \
  -e REDIS_WORKLOADS_HOST=redis -e REDIS_WORKLOADS_DB=15 \
  app php scripts/test-redis-workloads.php
docker compose --env-file .env.docker --profile redis stop redis
```

The integration runner requires the explicit testing environment and opt-in,
uses synthetic values and a random owned namespace, and touches no database
records. It checks real TTLs, hit/miss, invalidation, 300 increments across six
parallel workers, connection failure and next-request recovery. Cleanup deletes
only the exact keys created by that run; it never flushes a shared database.
These build/integration commands do not replace the running app. The final local
app was separately recreated from the verified image and is running at
`http://localhost:8080` with local frontend/workspace/Redis flags enabled.

Host verification on 2026-09-21: 12 focused tests / 79 assertions passed without
the Redis extension; the full suite at that point passed 84 tests / 8,606
assertions. Compose validation (`config --quiet`) passed.

Local Docker verification on 2026-09-21:

- The final Docker build passed clean `npm ci` on pinned Node 24.15.0/npm
  11.8.0, production compilation, the PHP/Redis extension build, locked Composer
  installation and final image export. Frontend dependencies/assets came from
  the container; emitted asset hashes matched the host build.
- `scripts/test-redis-workloads.php` passed against Redis 8.2.5 / phpredis 6.3.0:
  real hit/miss, TTL, invalidation, namespace isolation, 300 parallel increments,
  refused connection, read timeout and recovery. It measured 42.84 ms for 100
  cached reads, 10.77 ms for refused-connection fallback and 201.90 ms for the
  read-timeout fallback. These are local synthetic integration timings, not a
  production database speedup claim.
- The isolated Redis verification did not replace the application container or
  change database/mail settings. Redis has no published host port. The final
  app recreation and read-only browser verification are recorded separately in
  the delivery plan.
- The operational-error follow-up passed 35 focused host tests / 144 assertions
  without phpredis, covering native exception classification, redaction, a
  successful version lookup despite READONLY, a preserved command response
  despite OOM metrics, and DNS warning conversion. A repeat isolated Docker run
  with the updated transport/runner mounted read-only also passed real DNS
  failure and all earlier checks: 100 cached reads 71.46 ms, refused connection
  15.15 ms, DNS failure 69.68 ms and read timeout 201.62 ms. DNS timing is only
  a measured local result, not a guaranteed production deadline.

The final combined backend suite subsequently passed 131 tests / 9,104
assertions. All final frontend, browser/accessibility and production-build
checks also passed locally; GitHub CI is configured but was not executed on
GitHub. No production Redis provisioning or enablement occurred.

## Explicitly deferred workloads

| Workload | Reason / required follow-up |
| --- | --- |
| Redis sessions | Changes session availability, logout/migration behavior and stores sensitive session material; requires explicit data/security/operational approval |
| Redis queues/email | Application emails now use a separate approved [database queue](../application-email.md); moving delivery to Redis remains deferred and needs separate provisioning/durability approval |
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
[Redis security guidance](https://redis.io/docs/latest/operate/oss_and_stack/management/security/),
[phpredis 6.3.0 compatibility](https://pecl.php.net/package/redis/6.3.0),
[Redis 8.2.5 release](https://redis.io/docs/latest/operate/oss_and_stack/stack-with-enterprise/release-notes/redisce/redisos-8.2-release-notes/).
