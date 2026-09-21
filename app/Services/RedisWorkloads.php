<?php

namespace App\Services;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class RedisWorkloads
{
    private const UNAVAILABLE = 'obsidian.redis_workloads_unavailable';

    private $store;
    private $request;

    public function __construct(RedisWorkloadStore $store, Request $request)
    {
        $this->store = $store;
        $this->request = $request;
    }

    public function version(string $kind, ?string $prefix, Closure $readDatabase): ?string
    {
        if ($prefix === null) {
            return null;
        }

        if (!$this->enabled('versions_enabled')) {
            return $readDatabase();
        }

        $key = $this->versionKey($kind, $prefix);
        $cached = $this->attempt(fn () => $this->store->get($key));
        if (is_string($cached) || is_numeric($cached)) {
            return (string) $cached;
        }

        // Database failures must propagate, and missing deployments are never cached.
        $version = $readDatabase();
        if ($version !== null) {
            $this->attempt(fn () => $this->store->put($key, $version, config('redis-workloads.version_ttl')));
        }

        return $version;
    }

    public function invalidateVersion(string $kind, ?string $prefix): void
    {
        if ($prefix !== null && $this->enabled('versions_enabled')) {
            $key = $this->versionKey($kind, $prefix);
            $this->attempt(fn () => $this->store->forget($key));
        }
    }

    public function countRoute(?string $route): void
    {
        if (!$this->enabled('metrics_enabled') || !in_array($route, config('redis-workloads.metric_routes'), true)) {
            return;
        }

        $now = now('UTC');
        $base = config('redis-workloads.prefix').'metrics:'.$route.':';
        $this->attempt(fn () => $this->store->incrementBuckets(
            [$base.'minute:'.$now->format('YmdHi'), $base.'day:'.$now->format('Ymd')],
            [config('redis-workloads.minute_ttl'), config('redis-workloads.day_ttl')]
        ));
    }

    private function enabled(string $workload): bool
    {
        return config('redis-workloads.enabled') && config('redis-workloads.'.$workload);
    }

    private function versionKey(string $kind, string $prefix): string
    {
        if (!in_array($kind, ['firmware', 'config'], true)) {
            throw new InvalidArgumentException('Unsupported version cache kind.');
        }

        return config('redis-workloads.prefix').'software-version:'.$kind.':'.hash('sha256', $prefix);
    }

    private function attempt(Closure $operation)
    {
        if ($this->request->attributes->get(self::UNAVAILABLE, false)) {
            return null;
        }

        try {
            return $operation();
        } catch (RedisWorkloadUnavailable $exception) {
            // One unavailable connection attempt per request, shared across services.
            $this->request->attributes->set(self::UNAVAILABLE, true);
            if (Cache::store('file')->add(config('redis-workloads.prefix').'redis-unavailable-log', true, 60)) {
                Log::warning('redis_workloads.unavailable');
            }

            return null;
        }
    }
}
