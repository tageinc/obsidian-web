<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\DeviceSoftwareController;
use App\Http\Controllers\AdminControlCenterController;
use App\Http\Middleware\LogRequests;
use App\Models\ConfigVersions;
use App\Models\FirmwareVersions;
use App\Services\RedisWorkloadStore;
use App\Services\RedisWorkloadUnavailable;
use App\Services\RedisWorkloads;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class RedisWorkloadsTest extends TestCase
{
    private $store;
    private $cache;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'redis-workloads.enabled' => true,
            'redis-workloads.prefix' => 'obsidian:testing:v1:',
            'cache.stores.file' => ['driver' => 'array'],
        ]);
        Carbon::setTestNow(Carbon::parse('2026-09-21T00:01:00Z'));
        foreach (['firmware_versions', 'config_versions'] as $table) {
            Schema::create($table, function (Blueprint $table) {
                $table->id();
                $table->string('version');
                $table->string('prefix')->nullable();
                $table->string('file_path')->nullable();
                $table->string('description')->nullable();
                $table->timestamps();
            });
        }

        $this->cache = Cache::store('array');
        $this->store = Mockery::mock(RedisWorkloadStore::class);
        $this->store->shouldReceive('get')->byDefault()->andReturnUsing(fn ($key) => $this->cache->get($key));
        $this->store->shouldReceive('put')->byDefault()->andReturnUsing(fn ($key, $value, $ttl) => $this->cache->put($key, $value, $ttl));
        $this->store->shouldReceive('forget')->byDefault()->andReturnUsing(fn ($key) => $this->cache->forget($key));
        $this->store->shouldReceive('incrementBuckets')->byDefault();
        $this->app->instance(RedisWorkloadStore::class, $this->store);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_version_cache_preserves_json_avoids_repeated_queries_and_expires(): void
    {
        FirmwareVersions::create(['version' => '001.20', 'prefix' => 'solar', 'description' => 'must not enter cache']);
        DB::enableQueryLog();
        $this->assertVersion('firmware', 'solar', '001.20');
        $queryCount = count(DB::getQueryLog());
        $this->assertVersion('firmware', 'solar', '001.20');
        $this->assertCount($queryCount, DB::getQueryLog());
        $this->assertSame('001.20', $this->cache->get($this->key('firmware', 'solar')));

        // A raw SQL write deliberately bypasses model events: TTL is the safety bound.
        DB::table('firmware_versions')->where('prefix', 'solar')->update(['version' => '002.00']);
        $this->assertVersion('firmware', 'solar', '001.20');
        Carbon::setTestNow(now()->addSeconds(61));
        $this->assertVersion('firmware', 'solar', '002.00');
    }

    public function test_kind_prefix_and_environment_are_isolated_and_missing_results_are_not_cached(): void
    {
        FirmwareVersions::create(['version' => '1', 'prefix' => 'solar']);
        FirmwareVersions::create(['version' => '2', 'prefix' => 'other']);
        ConfigVersions::create(['version' => '3', 'prefix' => 'solar']);
        $this->assertVersion('firmware', 'solar', '1');
        $this->assertVersion('firmware', 'other', '2');
        $this->assertVersion('config', 'solar', '3');

        $response = app(DeviceSoftwareController::class)->getLatestFirmwareVersionNumber('missing');
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(['version' => '0', 'error' => 'no_matching_version'], $response->getData(true));
        $this->assertNull($this->cache->get($this->key('firmware', 'missing')));
        DB::table('firmware_versions')->insert(['version' => '4', 'prefix' => 'missing']);
        $this->assertVersion('firmware', 'missing', '4');

        config(['redis-workloads.prefix' => 'obsidian:another-test:v1:']);
        DB::table('firmware_versions')->where('prefix', 'solar')->update(['version' => '5']);
        $this->assertVersion('firmware', 'solar', '5');
    }

    public function test_models_invalidate_create_update_old_and_new_prefixes_and_delete(): void
    {
        $record = FirmwareVersions::create(['version' => '1', 'prefix' => 'old']);
        $this->assertVersion('firmware', 'old', '1');
        $this->cache->put($this->key('firmware', 'new'), 'stale', 60);
        $record->update(['version' => '2', 'prefix' => 'new']);
        $this->assertNull($this->cache->get($this->key('firmware', 'old')));
        $this->assertNull($this->cache->get($this->key('firmware', 'new')));
        $this->assertVersion('firmware', 'new', '2');
        FirmwareVersions::create(['version' => '3', 'prefix' => 'new']);
        $this->assertVersion('firmware', 'new', '3');
        FirmwareVersions::where('version', '3')->first()->delete();
        $this->assertVersion('firmware', 'new', '2');

        $config = ConfigVersions::create(['version' => '4', 'prefix' => 'new']);
        $this->assertVersion('config', 'new', '4');
        $config->delete();
        $this->assertNull($this->cache->get($this->key('config', 'new')));
    }

    public function test_invalidation_runs_only_after_commit_and_not_after_rollback(): void
    {
        $record = FirmwareVersions::create(['version' => '1', 'prefix' => 'solar']);
        $this->assertVersion('firmware', 'solar', '1');
        DB::beginTransaction();
        $record->update(['version' => '2']);
        $this->assertSame('1', $this->cache->get($this->key('firmware', 'solar')));
        DB::rollBack();
        $this->assertSame('1', $this->cache->get($this->key('firmware', 'solar')));

        DB::beginTransaction();
        $record->refresh()->update(['version' => '3']);
        $this->assertSame('1', $this->cache->get($this->key('firmware', 'solar')));
        DB::commit();
        $this->assertNull($this->cache->get($this->key('firmware', 'solar')));
        $this->assertVersion('firmware', 'solar', '3');
    }

    public function test_disabled_workloads_and_null_prefix_never_connect(): void
    {
        $store = Mockery::mock(RedisWorkloadStore::class);
        $this->app->instance(RedisWorkloadStore::class, $store);
        config(['redis-workloads.enabled' => false]);
        FirmwareVersions::create(['version' => '1', 'prefix' => 'solar']);
        $this->assertVersion('firmware', 'solar', '1');
        app(RedisWorkloads::class)->countRoute('api.login');
        $this->assertSame(404, app(DeviceSoftwareController::class)->getLatestFirmwareVersionNumber()->getStatusCode());
        $this->assertSame('array', config('cache.default'));
        $this->assertSame('array', config('session.driver'));
        $this->assertSame('sync', config('queue.default'));
    }

    public function test_outage_falls_back_to_database_skips_other_redis_operations_and_redacts_logs(): void
    {
        FirmwareVersions::create(['version' => '1', 'prefix' => 'solar']);
        $store = Mockery::mock(RedisWorkloadStore::class);
        $store->shouldReceive('get')->once()->andThrow(new RedisWorkloadUnavailable('sentinel-connection-password'));
        $this->app->instance(RedisWorkloadStore::class, $store);
        Log::spy();

        $this->assertVersion('firmware', 'solar', '1');
        $this->assertVersion('firmware', 'solar', '1');
        app(RedisWorkloads::class)->countRoute('api.login');
        app(RedisWorkloads::class)->invalidateVersion('firmware', 'solar');
        Log::shouldHaveReceived('warning')->once()->with('redis_workloads.unavailable');

        // The next request can recover; the unavailable flag is not process-global.
        $this->app->instance('request', Request::create('/'));
        $this->app->instance(RedisWorkloadStore::class, $this->store);
        $this->assertVersion('firmware', 'solar', '1');
    }

    public function test_database_and_programming_errors_are_not_hidden_as_cache_outages(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('database failure');
        app(RedisWorkloads::class)->version('firmware', 'solar', function () {
            throw new RuntimeException('database failure');
        });
    }

    public function test_successful_upload_invalidates_version_and_failed_validation_publishes_nothing(): void
    {
        Storage::fake('local');
        FirmwareVersions::create(['version' => '1', 'prefix' => 'solar']);
        $this->assertVersion('firmware', 'solar', '1');
        $request = Request::create('/upload-firmware', 'POST', ['prefix' => 'solar', 'description' => 'synthetic']);
        $request->files->set('firmware', UploadedFile::fake()->create('firmware.bin', 1));
        app(AdminControlCenterController::class)->uploadFirmware($request);
        $this->assertVersion('firmware', 'solar', '2');
        $this->assertSame(2, FirmwareVersions::count());

        try {
            app(AdminControlCenterController::class)->uploadFirmware(Request::create('/upload-firmware', 'POST', ['prefix' => 'solar']));
            $this->fail('An invalid upload must fail validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('firmware', $exception->errors());
        }
        $this->assertVersion('firmware', 'solar', '2');
        $this->assertSame(2, FirmwareVersions::count());
    }

    public function test_a_cache_programming_error_remains_visible(): void
    {
        $this->store->shouldReceive('get')->once()->andThrow(new \LogicException('invalid cache operation'));
        $this->expectException(\LogicException::class);
        app(RedisWorkloads::class)->version('firmware', 'solar', fn () => '1');
    }

    public function test_missing_phpredis_extension_falls_back_without_harming_other_backends(): void
    {
        if (extension_loaded('redis')) {
            $this->markTestSkipped('The real missing-extension path applies only to a host without phpredis.');
        }
        $this->app->instance(RedisWorkloadStore::class, new RedisWorkloadStore());
        Log::spy();
        $this->assertSame('1', app(RedisWorkloads::class)->version('firmware', 'solar', fn () => '1'));
        $this->assertSame('array', config('cache.default'));
        Log::shouldHaveReceived('warning')->once()->with('redis_workloads.unavailable');
    }

    public function test_metrics_use_only_allowlisted_names_and_utc_bounded_buckets(): void
    {
        config(['app.timezone' => 'America/Los_Angeles']);
        $this->store->shouldReceive('incrementBuckets')->once()->with(
            ['obsidian:testing:v1:metrics:api.login:minute:202609210001', 'obsidian:testing:v1:metrics:api.login:day:20260921'],
            [120, 172800]
        );
        $service = app(RedisWorkloads::class);
        $service->countRoute('api.login');
        $service->countRoute('/secret?token=sentinel');
        $service->countRoute(null);
    }

    public function test_request_metrics_preserve_response_and_never_log_private_url_or_body(): void
    {
        Log::spy();
        $request = Request::create('/api/login?token=sentinel-token', 'POST', ['password' => 'sentinel-password']);
        $request->setRouteResolver(fn () => (new Route('POST', 'api/login', fn () => null))->name('api.login'));
        $this->app->instance('request', $request);
        $response = (new LogRequests())->handle($request, fn () => response()->json(['ok' => true], 201));
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(['ok' => true], $response->getData(true));
        Log::shouldNotHaveReceived('info');
        $this->store->shouldHaveReceived('incrementBuckets')->once();
    }

    private function key(string $kind, string $prefix): string
    {
        return config('redis-workloads.prefix').'software-version:'.$kind.':'.hash('sha256', $prefix);
    }

    private function assertVersion(string $kind, string $prefix, string $version): void
    {
        $controller = app(DeviceSoftwareController::class);
        $response = $kind === 'firmware'
            ? $controller->getLatestFirmwareVersionNumber($prefix)
            : $controller->getLatestConfigVersionNumber($prefix);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['version' => $version], $response->getData(true));
    }
}
