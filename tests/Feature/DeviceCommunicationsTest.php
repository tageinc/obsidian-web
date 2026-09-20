<?php

namespace Tests\Feature;

use Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use App\Models\Api\SolarTrackerLog;
use App\Models\Api\EnergyMonitorLog;
use App\Services\DeviceCommunicationStatus;

class DeviceCommunicationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        config(['devices.telemetry_freshness_seconds' => 600]);
        Carbon::setTestNow(Carbon::parse('2026-09-20 12:00:00'));
        foreach (['firmware_versions', 'config_versions'] as $table) {
            Schema::create($table, function (Blueprint $table) {
                $table->id();
                $table->string('prefix');
                $table->string('version');
                $table->string('file_path')->nullable();
                $table->timestamps();
            });
        }
        Schema::create('device_registers', function (Blueprint $table) {
            $table->id();
            $table->string('serial_no');
            $table->integer('hardware_id');
        });
        foreach ([new SolarTrackerLog, new EnergyMonitorLog] as $model) {
            Schema::create($model->getTable(), function (Blueprint $table) use ($model) {
                $table->id();
                foreach ($model->getFillable() as $field) {
                    $table->string($field)->nullable();
                }
                $table->timestamps();
                $table->index(['serial_no', 'created_at']);
            });
        }
        DB::table('device_registers')->insert([
            ['serial_no' => 'solar', 'hardware_id' => 1],
            ['serial_no' => 'energy', 'hardware_id' => 2],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_missing_software_records_and_prefixes_are_deliberate_404s(): void
    {
        foreach (['firmware', 'config'] as $kind) {
            foreach (['version', 'version/123', 'prefix', 'prefix/missing'] as $selector) {
                $this->getJson('/api/'.$kind.'-file/'.$selector)->assertNotFound();
            }
            foreach (['', '/missing'] as $prefix) {
                $this->getJson('/api/'.$kind.'-version'.$prefix)->assertNotFound()
                    ->assertExactJson(['version' => '0', 'error' => 'no_matching_version']);
            }
        }
    }

    public function test_matching_versions_and_downloads_preserve_contract(): void
    {
        Storage::fake();
        Storage::put('test.bin', 'firmware-content');
        foreach (['firmware', 'config'] as $kind) {
            DB::table($kind.'_versions')->insert(['prefix' => 'panel', 'version' => '7', 'file_path' => 'test.bin']);
            $this->getJson('/api/'.$kind.'-version/panel')->assertOk()->assertExactJson(['version' => '7']);
            $this->getJson('/api/'.$kind.'-version/wrong')->assertNotFound();
            DB::table($kind.'_versions')->insert(['prefix' => 'zero', 'version' => '0', 'file_path' => 'test.bin']);
            $this->getJson('/api/'.$kind.'-version/zero')->assertOk()->assertExactJson(['version' => '0']);
            $this->get('/api/'.$kind.'-file/prefix/panel')->assertOk();
            $this->get('/api/'.$kind.'-file/version/7')->assertOk();
            DB::table($kind.'_versions')->update(['file_path' => 'absent.bin']);
            $this->getJson('/api/'.$kind.'-file/version')->assertNotFound();
        }
    }

    public function test_malformed_and_unsupported_telemetry_never_writes(): void
    {
        foreach (['{', 'null', '[]', '123', '{}', '{"unknown":1}', '{"temp":null}',
            '{"temp":"bad"}', '{"temp":true}', '{"temp":[]}', '{"temp":"1e999"}',
            '{"cts":1.5}', '{"state":[]}', '{"state":""}'] as $data) {
            $this->postJson('/api/log', ['serial_no' => 'solar', 'data' => $data])->assertStatus(422);
        }
        $this->postJson('/api/log', ['serial_no' => [], 'data' => '{}'])->assertStatus(422);
        $this->postJson('/api/log', ['serial_no' => 'solar', 'data' => ['temp' => 2]])->assertStatus(422);
        $this->assertSame(0, SolarTrackerLog::count());
        $this->assertSame(0, EnergyMonitorLog::count());
    }

    public function test_valid_legacy_partial_and_extended_payloads_use_correct_writer(): void
    {
        $this->post('/api/log', ['serial_no' => 'solar', 'data' => '{"ps1":"0","cts":1,"state":"online","extra":42}'])
            ->assertOk()->assertExactJson(['msg' => 'success']);
        $this->postJson('/api/log', ['serial_no' => 'energy', 'data' => '{"v_batt":"12.5","temp":0}'])
            ->assertOk()->assertExactJson(['msg' => 'success']);
        $this->assertDatabaseHas('solar_tracker_logs', ['serial_no' => 'solar', 'ps1' => 0, 'state' => 'online']);
        $this->assertDatabaseHas('energy_monitor_logs', ['serial_no' => 'energy', 'v_batt' => 12.5]);
        $this->assertSame(1, SolarTrackerLog::count());
        $this->assertSame(1, EnergyMonitorLog::count());
    }

    public function test_freshness_boundary_missing_and_future_telemetry(): void
    {
        $service = new DeviceCommunicationStatus;
        foreach ([1, 2] as $hardware) {
            $device = (object) ['hardware_id' => $hardware, 'serial_no' => $hardware === 1 ? 'solar' : 'energy', 'latitude' => 0, 'longitude' => 0];
            $geo = (object) ['latitude' => 0, 'longitude' => 0];
            $this->assertSame('offline', $service->classify($device, $geo, null));
            foreach ([599 => 'online', 600 => 'offline', 601 => 'offline', -1 => 'offline'] as $age => $expected) {
                $log = new SolarTrackerLog(['state' => 'online']);
                $log->created_at = now()->subSeconds($age);
                $this->assertSame($expected, $service->classify($device, $geo, $log));
            }
        }
    }

    public function test_source_selection_does_not_use_other_hardware_telemetry(): void
    {
        EnergyMonitorLog::create(['serial_no' => 'solar', 'temp' => 20]);
        $service = new DeviceCommunicationStatus;
        $device = (object) ['hardware_id' => 1, 'serial_no' => 'solar'];
        $this->assertNull($service->latest($device));
        SolarTrackerLog::create(['serial_no' => 'solar', 'state' => 'online']);
        $this->assertInstanceOf(SolarTrackerLog::class, $service->latest($device));
    }

    public function test_rate_limits_are_isolated_and_prevent_writes(): void
    {
        config(['devices.telemetry_per_minute' => 1]);
        $payload = ['serial_no' => 'solar', 'data' => '{"temp":20}'];
        $this->postJson('/api/log', $payload)->assertOk();
        $this->postJson('/api/log', $payload)->assertStatus(429)->assertHeader('Retry-After');
        $this->postJson('/api/log', ['serial_no' => 'energy', 'data' => '{"temp":20}'])->assertOk();
        $this->assertSame(1, SolarTrackerLog::count());
        $limiter = \Illuminate\Support\Facades\RateLimiter::limiter('api');
        $a = \Illuminate\Http\Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '192.0.2.1']);
        $b = \Illuminate\Http\Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '192.0.2.2']);
        $this->assertNotSame($limiter($a)->key, $limiter($b)->key);
    }

    public function test_read_only_schema_audit_checks_tables_columns_and_indexes(): void
    {
        $this->artisan('device:audit-telemetry')->assertExitCode(0);
        Schema::table('solar_tracker_logs', function (Blueprint $table) {
            $table->dropIndex(['serial_no', 'created_at']);
        });
        $this->artisan('device:audit-telemetry')->assertExitCode(1);
        Schema::drop('energy_monitor_logs');
        $this->artisan('device:audit-telemetry')->assertExitCode(1);
        $this->assertSame(0, SolarTrackerLog::count());
    }

    public function test_status_command_persists_offline_for_stale_and_missing_telemetry(): void
    {
        Schema::create('geocode', function (Blueprint $table) {
            $table->id();
            $table->string('serial_no');
            $table->string('status');
            $table->timestamps();
        });
        DB::table('geocode')->insert([
            ['serial_no' => 'solar', 'status' => 'online'],
            ['serial_no' => 'energy', 'status' => 'online'],
        ]);
        $log = new SolarTrackerLog(['serial_no' => 'solar', 'state' => 'online']);
        $log->created_at = now()->subMinutes(11);
        $log->save();
        // This report must not rescue a solar tracker from offline classification.
        EnergyMonitorLog::create(['serial_no' => 'solar', 'temp' => 20]);
        $this->artisan('device:check-status')->assertExitCode(0);
        $this->assertDatabaseHas('geocode', ['serial_no' => 'solar', 'status' => 'offline']);
        $this->assertDatabaseHas('geocode', ['serial_no' => 'energy', 'status' => 'offline']);
        SolarTrackerLog::create(['serial_no' => 'solar', 'state' => 'tracking']);
        $this->artisan('device:check-status')->assertExitCode(0);
        $this->assertDatabaseHas('geocode', ['serial_no' => 'solar', 'status' => 'tracking']);
    }

    public function test_software_diagnostics_do_not_log_request_secrets(): void
    {
        \Illuminate\Support\Facades\Log::spy();
        $this->getJson('/api/firmware-version/unknown?token=secret-value')->assertNotFound();
        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->once()->with(
            'device.software_unavailable', [
                'kind' => 'firmware', 'selector' => 'prefix', 'reason' => 'no_matching_version',
                'selector_hash' => hash('sha256', 'unknown'),
            ]);
    }

    public function test_missing_remote_serial_returns_existing_error_instead_of_exception(): void
    {
        $this->get('/api/remote-control')->assertOk()->assertExactJson(['msg' => 'serial number null or not found']);
    }
}
