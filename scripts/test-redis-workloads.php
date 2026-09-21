<?php

// Run only against the opt-in disposable local/CI Redis service. Never FLUSHDB.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\RedisWorkloadStore;
use App\Services\RedisWorkloads;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Facades\Cache;

if (getenv('REDIS_WORKLOADS_INTEGRATION') !== '1' || !$app->environment('testing') || !extension_loaded('redis')) {
    fwrite(STDERR, "Requires APP_ENV=testing, REDIS_WORKLOADS_INTEGRATION=1 and phpredis against disposable Redis.\n");
    exit(2);
}

if (($argv[1] ?? '') === '--timeout-worker') {
    // Accept only local test traffic, then withhold a Redis response.
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    if (!$socket) {
        exit(3);
    }
    fwrite(STDOUT, stream_socket_get_name($socket, false)."\n");
    fflush(STDOUT);
    $connection = stream_socket_accept($socket, 5);
    if ($connection) {
        sleep(2);
        fclose($connection);
    }
    fclose($socket);
    exit(0);
}

$worker = ($argv[1] ?? '') === '--worker';
$runId = $worker ? ($argv[2] ?? '') : bin2hex(random_bytes(12));
if (!preg_match('/^[a-f0-9]{24}$/', $runId)) {
    throw new RuntimeException('Invalid isolated test namespace.');
}
$namespace = 'obsidian:testing:v1:integration:'.$runId.':';
config(['redis-workloads.enabled' => true, 'redis-workloads.prefix' => $namespace]);
Carbon::setTestNow(Carbon::parse('2026-09-21T00:01:00Z'));
$store = $app->make(RedisWorkloadStore::class);
$service = $app->make(RedisWorkloads::class);

if ($worker) {
    for ($i = 0; $i < 50; $i++) {
        $service->countRoute('api.login');
    }
    exit(0);
}

function check($condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$versionKey = $namespace.'software-version:firmware:'.hash('sha256', 'synthetic');
$expiryKey = $namespace.'expiry';
$otherNamespaceKey = $namespace.'other:expiry';
$metricKeys = [$namespace.'metrics:api.login:minute:202609210001', $namespace.'metrics:api.login:day:20260921'];
$ownedKeys = array_merge([$versionKey, $expiryKey, $otherNamespaceKey], $metricKeys);
$originalManager = $app->make('redis');
$processes = [];

try {
    $client = Cache::store('redis-workloads')->getStore()->connection()->client();
    $reads = 0;
    $readDatabase = function () use (&$reads) {
        $reads++;
        return '001.20';
    };
    $started = hrtime(true);
    for ($i = 0; $i < 100; $i++) {
        check($service->version('firmware', 'synthetic', $readDatabase) === '001.20', 'Version text changed.');
    }
    check($reads === 1, 'Cache hit must skip the authoritative reader.');
    check($client->ttl($versionKey) > 0 && $client->ttl($versionKey) <= 60, 'Version TTL is not bounded.');
    $cacheMilliseconds = (hrtime(true) - $started) / 1e6;
    $service->invalidateVersion('firmware', 'synthetic');
    check($store->get($versionKey) === null, 'Invalidation failed.');
    check($service->version('firmware', 'missing', fn () => null) === null, 'Missing version was changed.');
    check(!$client->exists($namespace.'software-version:firmware:'.hash('sha256', 'missing')), 'Negative result was cached.');

    $store->put($expiryKey, '1', 1);
    $store->put($otherNamespaceKey, '2', 30);
    usleep(1100000);
    check($store->get($expiryKey) === null, 'Expired value remained visible.');
    check($store->get($otherNamespaceKey) === '2', 'Unrelated key was altered.');

    for ($i = 0; $i < 6; $i++) {
        $pipes = [];
        $process = proc_open([PHP_BINARY, __FILE__, '--worker', $runId], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        check(is_resource($process), 'Unable to start concurrent counter worker.');
        $processes[] = [$process, $pipes];
    }
    foreach ($processes as [$process, $pipes]) {
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        check(proc_close($process) === 0, 'Concurrent counter worker failed.');
    }
    $processes = [];
    foreach ($metricKeys as $index => $key) {
        check($client->get($key) === '300', 'Concurrent increments were lost.');
        check($client->ttl($key) > 0 && $client->ttl($key) <= [120, 172800][$index], 'Metric expiry is missing.');
    }

    // Force a local refused connection; preserve the real test service for cleanup.
    $failedConfig = config('database.redis');
    $failedConfig['workloads']['host'] = '127.0.0.1';
    $failedConfig['workloads']['port'] = 1;
    $app->instance('redis', new RedisManager($app, 'phpredis', $failedConfig));
    Cache::purge('redis-workloads');
    $app->instance('request', Request::create('/'));
    $failedService = $app->make(RedisWorkloads::class);
    $started = hrtime(true);
    check($failedService->version('firmware', 'synthetic', fn () => 'fallback') === 'fallback', 'Outage did not use authoritative reader.');
    $failedService->countRoute('api.login');
    $outageMilliseconds = (hrtime(true) - $started) / 1e6;
    check($outageMilliseconds < 1000, 'Refused connection exceeded the test latency ceiling.');

    $timeoutPipes = [];
    $timeoutProcess = proc_open([PHP_BINARY, __FILE__, '--timeout-worker'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $timeoutPipes);
    check(is_resource($timeoutProcess), 'Unable to start timeout fixture.');
    $processes[] = [$timeoutProcess, $timeoutPipes];
    $address = trim(fgets($timeoutPipes[1]));
    check((bool) preg_match('/^127\.0\.0\.1:([0-9]+)$/', $address, $matches), 'Invalid local timeout fixture.');
    $failedConfig['workloads']['port'] = (int) $matches[1];
    $app->instance('redis', new RedisManager($app, 'phpredis', $failedConfig));
    Cache::purge('redis-workloads');
    $app->instance('request', Request::create('/'));
    $started = hrtime(true);
    check($app->make(RedisWorkloads::class)->version('firmware', 'synthetic', fn () => 'timeout-fallback') === 'timeout-fallback', 'Read timeout did not use authoritative reader.');
    $timeoutMilliseconds = (hrtime(true) - $started) / 1e6;
    check($timeoutMilliseconds < 1000, 'Read timeout exceeded the test latency ceiling.');

    $app->instance('redis', $originalManager);
    Cache::purge('redis-workloads');
    $app->instance('request', Request::create('/'));
    check($app->make(RedisWorkloads::class)->version('firmware', 'synthetic', fn () => 'recovered') === 'recovered', 'Next request did not recover.');

    printf("PASS: Redis hit/miss, TTL, invalidation, namespace, 300 parallel increments, outage, timeout and recovery.\n100 cached reads: %.2f ms; refused-connection fallback: %.2f ms; read-timeout fallback: %.2f ms.\n", $cacheMilliseconds, $outageMilliseconds, $timeoutMilliseconds);
} finally {
    foreach ($processes as [$process, $pipes]) {
        if (is_resource($process)) {
            proc_terminate($process);
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            proc_close($process);
        }
    }
    $app->instance('redis', $originalManager);
    Cache::purge('redis-workloads');
    foreach ($ownedKeys as $key) {
        $store->forget($key);
    }
    Carbon::setTestNow();
}
