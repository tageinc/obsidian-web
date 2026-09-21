<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;
use RedisException;

/** Transport for the two disposable workloads; never selects the default cache. */
class RedisWorkloadStore
{
    public function get(string $key)
    {
        $value = $this->execute(fn ($client) => $client->get($key));

        return $value === false ? null : $value;
    }

    public function put(string $key, string $value, int $ttl): void
    {
        $this->execute(fn ($client) => $client->setex($key, $ttl, $value));
    }

    public function forget(string $key): void
    {
        $this->execute(fn ($client) => $client->del($key));
    }

    public function incrementBuckets(array $keys, array $ttls): void
    {
        // A single atomic operation avoids lost increments and permanent keys.
        $script = <<<'LUA'
for i, key in ipairs(KEYS) do
    redis.call('INCR', key)
    if redis.call('TTL', key) < 0 then
        redis.call('EXPIRE', key, ARGV[i])
    end
end
return 1
LUA;
        $this->execute(fn ($client) => $client->eval(
            $script, array_merge($keys, $ttls), count($keys)
        ));
    }

    private function execute(Closure $operation)
    {
        try {
            // Native commands avoid Laravel 8's implicit reconnect on "went away".
            return $operation($this->client());
        } catch (RedisException | \ErrorException $exception) {
            // Do not hide wrong-type, Lua, or other programming errors.
            // phpredis can report DNS resolution as a PHP warning/ErrorException.
            $transport = '/connection|socket|read error|went away|timed? out|timeout|refused|noauth|wrongpass|noperm|auth failed|php_network_getaddresses|getaddrinfo/i';
            $operational = '/^(?:READONLY|LOADING|OOM|MISCONF|MASTERDOWN|CLUSTERDOWN|TRYAGAIN|BUSY|NOREPLICAS)\b/i';
            if (!preg_match($transport, $exception->getMessage()) && !preg_match($operational, $exception->getMessage())) {
                throw $exception;
            }

            // Neither the connection string nor the original exception is retained.
            throw new RedisWorkloadUnavailable('transport_unavailable');
        }
    }

    protected function client()
    {
        if (!extension_loaded('redis')) {
            throw new RedisWorkloadUnavailable('extension_unavailable');
        }

        $cache = Cache::store(config('redis-workloads.store'));
        $client = $cache->getStore()->connection()->client();
        if (defined('Redis::OPT_MAX_RETRIES')) {
            $client->setOption(\Redis::OPT_MAX_RETRIES, 0);
        }

        return $client;
    }
}
