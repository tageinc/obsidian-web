<?php

namespace Tests\Unit;

use App\Http\Middleware\LogRequests;
use App\Services\RedisWorkloadStore;
use App\Services\RedisWorkloadUnavailable;
use App\Services\RedisWorkloads;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class RedisWorkloadStoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Ordinary host tests need no extension; exercise the native exception boundary.
        if (!class_exists('RedisException')) {
            class_alias(RedisTransportTestException::class, 'RedisException');
        }
        config([
            'redis-workloads.enabled' => true,
            'cache.stores.file' => ['driver' => 'array'],
        ]);
    }

    /** @dataProvider operationalFailures */
    public function test_known_operational_failures_are_redacted_optional_outages(string $message): void
    {
        $client = Mockery::mock();
        $client->shouldReceive('get')->once()->andThrow(new \RedisException($message));

        try {
            $this->storeWith($client)->get('synthetic');
            $this->fail('The operational exception must be translated.');
        } catch (RedisWorkloadUnavailable $exception) {
            $this->assertSame('transport_unavailable', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
    }

    public static function operationalFailures(): array
    {
        return array_map(fn ($message) => [$message], [
            'READONLY You cannot write against a read only replica.',
            'LOADING Redis is loading the dataset in memory',
            "OOM command not allowed when used memory > 'maxmemory'.",
            'MISCONF Redis is configured to save RDB snapshots but is currently unable to persist to disk.',
            'MASTERDOWN Link with MASTER is down and replica-serve-stale-data is set to no.',
            'CLUSTERDOWN The cluster is down',
            'TRYAGAIN Multiple keys request during rehashing of slot',
            'BUSY Redis is busy running a script',
            'NOREPLICAS Not enough good replicas to write.',
            'php_network_getaddresses: getaddrinfo for sentinel-private-host failed: Name or service not known',
            'getaddrinfo failed: Temporary failure in name resolution',
            'Connection refused at sentinel-private-host',
            'read error on connection to sentinel-private-host',
            'NOAUTH Authentication required.',
            'WRONGPASS invalid username-password pair or user is disabled.',
            'NOPERM this user has no permissions to run the command',
        ]);
    }

    public function test_dns_warning_conversion_also_preserves_database_fallback(): void
    {
        $store = new class extends RedisWorkloadStore {
            protected function client()
            {
                throw new \ErrorException('Redis::connect(): php_network_getaddresses: getaddrinfo for sentinel-private-host failed');
            }
        };
        Log::spy();
        $service = new RedisWorkloads($store, Request::create('/'));
        $this->assertSame('001.20', $service->version('firmware', 'synthetic', fn () => '001.20'));
        Log::shouldHaveReceived('warning')->once()->with('redis_workloads.unavailable');
    }

    /** @dataProvider programmingFailures */
    public function test_wrongtype_and_lua_programming_errors_remain_visible(string $message): void
    {
        $native = new \RedisException($message);
        $client = Mockery::mock();
        $client->shouldReceive('get')->once()->andThrow($native);
        try {
            $this->storeWith($client)->get('synthetic');
            $this->fail('A programming error must remain visible.');
        } catch (\RedisException $exception) {
            $this->assertSame($native, $exception);
        }
    }

    public static function programmingFailures(): array
    {
        return [
            ['WRONGTYPE Operation against a key holding the wrong kind of value'],
            ['ERR Error compiling script (new function): user_script:1: unexpected symbol'],
            ['ERR Error running script: user_script:1: attempt to call a nil value'],
            ['NOSCRIPT No matching script. Please use EVAL.'],
        ];
    }

    public function test_readonly_cache_write_cannot_change_a_successful_version_response(): void
    {
        $client = Mockery::mock();
        $client->shouldReceive('get')->once()->andReturn(false);
        $client->shouldReceive('setex')->once()->andThrow(new \RedisException('READONLY You cannot write against a read only replica.'));
        $service = new RedisWorkloads($this->storeWith($client), Request::create('/'));
        $this->assertSame('001.20', $service->version('firmware', 'synthetic', fn () => '001.20'));
        // The same request must skip subsequent optional writes after that failure.
        $service->countRoute('api.login');
        $service->invalidateVersion('firmware', 'synthetic');
    }

    public function test_failed_metrics_cannot_replace_a_persisted_command_response(): void
    {
        $client = Mockery::mock();
        $client->shouldReceive('eval')->once()->andThrow(new \RedisException("OOM command not allowed when used memory > 'maxmemory'."));
        $this->app->instance(RedisWorkloadStore::class, $this->storeWith($client));
        $request = Request::create('/synthetic-command', 'POST');
        $request->setRouteResolver(fn () => (new Route('POST', 'synthetic-command', fn () => null))->name('device.update-solar-tracker'));
        $this->app->instance('request', $request);
        $writes = 0;
        $response = (new LogRequests())->handle($request, function () use (&$writes) {
            $writes++;
            return response()->json(['updated' => true], 201);
        });
        $this->assertSame(1, $writes);
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(['updated' => true], $response->getData(true));
    }

    private function storeWith($client): RedisWorkloadStore
    {
        return new class($client) extends RedisWorkloadStore {
            private $testClient;

            public function __construct($client)
            {
                $this->testClient = $client;
            }

            protected function client()
            {
                return $this->testClient;
            }
        };
    }
}

class RedisTransportTestException extends \RuntimeException
{
}
