<?php

namespace Tests\Feature;

use App\Models\Api\SolarTrackerLog;
use App\Models\Device;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * OB-5: Server-side duplicate detection for ESP32 telemetry.
 *
 * Tests validate that identical serial_no + data payloads arriving within
 * the dedup window return 409 with retry_after, while new payloads return
 * 200 {"msg":"success"}. No extra writes occur for duplicates.
 */
class TelemetryDedupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure the dedup window is small for tests.
        Config::set('devices.telemetry_freshness_seconds', 600);

        Cache::flush();

        Schema::create('devices', function ($table) {
            $table->id();
            $table->string('state')->default('active');
            $table->string('serial_no');
            $table->integer('user_id')->nullable();
            $table->boolean('status_notification')->default(false);
        });
        Schema::create('solar_tracker_logs', function ($table) {
            $table->id();
            $table->string('serial_no')->index();
            $table->float('ps1')->nullable();
            $table->float('ps2')->nullable();
            $table->float('ps_avg')->nullable();
            $table->float('pds')->nullable();
            $table->float('motor_speed')->nullable();
            $table->float('temp')->nullable();
            $table->integer('cts')->nullable();
            $table->string('state')->nullable();
            $table->timestamps();
        });
        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Cache::flush();
        Schema::dropIfExists('solar_tracker_logs');
        Schema::dropIfExists('devices');
        Schema::dropIfExists('users');
        parent::tearDown();
    }

    // -- First arrival: 200, write created ----------------------------------

    public function test_first_arrival_returns_200_and_writes(): void
    {
        DB::table('devices')->insert(['serial_no' => 'dedup-test-1', 'state' => 'active']);

        $response = $this->post('/api/log', [
            'serial_no' => 'dedup-test-1',
            'data'      => '{"ps1":10.5,"ps2":9.8,"state":"online"}',
        ]);

        $response->assertStatus(200)
                 ->assertExactJson(['msg' => 'success']);

        $this->assertSame(1, SolarTrackerLog::count());
    }

    // -- Duplicate within window: 409 ----------------------------------------

    public function test_duplicate_within_window_returns_409(): void
    {
        DB::table('devices')->insert(['serial_no' => 'dedup-test-2', 'state' => 'active']);

        $payload = '{"ps1":10.5,"ps2":9.8,"state":"online"}';

        // First arrival → 200, 1 row
        $this->post('/api/log', ['serial_no' => 'dedup-test-2', 'data' => $payload])
             ->assertStatus(200);
        $this->assertSame(1, SolarTrackerLog::count());

        // Duplicate → 409, no new write
        $this->post('/api/log', ['serial_no' => 'dedup-test-2', 'data' => $payload])
             ->assertStatus(409)
             ->assertJsonStructure(['msg', 'retry_after'])
             ->assertJsonFragment(['msg' => 'duplicate']);

        $this->assertSame(1, SolarTrackerLog::count());
    }

    public function test_409_contains_retry_after_within_window_seconds(): void
    {
        Config::set('devices.telemetry_freshness_seconds', 600);

        DB::table('devices')->insert(['serial_no' => 'dedup-test-3', 'state' => 'active']);
        $payload = '{"ps1":5.0}';

        $this->post('/api/log', ['serial_no' => 'dedup-test-3', 'data' => $payload])
             ->assertStatus(200);

        $response = $this->post('/api/log', ['serial_no' => 'dedup-test-3', 'data' => $payload])
                         ->assertStatus(409)
                         ->assertJson(['retry_after' => 600]);
    }

    // -- Different serial or payload: 200 ------------------------------------

    public function test_different_serial_is_not_a_duplicate(): void
    {
        DB::table('devices')->insert([
            ['serial_no' => 'dedup-a', 'state' => 'active'],
            ['serial_no' => 'dedup-b', 'state' => 'active'],
        ]);

        $payload = '{"ps1":5.0}';

        $this->post('/api/log', ['serial_no' => 'dedup-a', 'data' => $payload])->assertStatus(200);
        $this->post('/api/log', ['serial_no' => 'dedup-b', 'data' => $payload])->assertStatus(200);

        $this->assertSame(2, SolarTrackerLog::count());
    }

    public function test_different_payload_is_not_a_duplicate(): void
    {
        DB::table('devices')->insert(['serial_no' => 'dedup-test-4', 'state' => 'active']);

        $this->post('/api/log', ['serial_no' => 'dedup-test-4', 'data' => '{"ps1":5.0}'])->assertStatus(200);
        $this->post('/api/log', ['serial_no' => 'dedup-test-4', 'data' => '{"ps1":6.0}'])->assertStatus(200);

        $this->assertSame(2, SolarTrackerLog::count());
    }

    // -- Backward compatibility: Redis unavailable ---------------------------

    public function test_falls_through_when_cache_is_unavailable(): void
    {
        DB::table('devices')->insert(['serial_no' => 'dedup-test-5', 'state' => 'active']);
        $payload = '{"ps1":3.0}';

        // Cache.add throws → code returns false (not dup) → normal flow continues.
        Config::set('cache.default', 'dummy');

        try {
            // First call: writes the log. On a second identical call, without a
            // functioning cache the code path falls through and writes again —
            // acceptable for degraded mode; the key point is it does NOT crash.
            $response1 = $this->post('/api/log', ['serial_no' => 'dedup-test-5', 'data' => $payload]);
            $response1->assertOk()->assertExactJson(['msg' => 'success']);
            $this->assertSame(1, SolarTrackerLog::count());
        } finally {
            Config::set('cache.default', 'array');
        }
    }

    // -- 4xx is non-retryable (already validated by existing tests) -----------

    public function test_unregistered_device_returns_422(): void
    {
        $response = $this->post('/api/log', [
            'serial_no' => 'unknown-device',
            'data'      => '{"ps1":1.0}',
        ]);

        $response->assertStatus(422)->assertJsonFragment(['msg' => 'device is not registered']);
    }

    // -- Route exists on the correct middleware group --------------------------

    public function test_telemetry_route_exists_and_is_post_only(): void
    {
        $this->assertTrue(Route::has('device.log-data'));
        $route = Route::getRoutes()->getByName('device.log-data');
        $this->assertSame(['POST'], $route->methods());
    }
}
