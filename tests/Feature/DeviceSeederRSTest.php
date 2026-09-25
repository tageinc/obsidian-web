<?php

namespace Tests\Feature;

use App\Services\SolarTrackerGraphData;
use Database\Seeders\DeviceSeederRS;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeviceSeederRSTest extends TestCase
{
    use RefreshDatabase;

    private string $csv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->csv = tempnam(sys_get_temp_dir(), 'sensor-test-');
        config(['device-seeder-rs.csv_path' => $this->csv]);
    }

    protected function tearDown(): void
    {
        unlink($this->csv);
        parent::tearDown();
    }

    public function test_recorded_readings_and_duplicate_timestamps_reach_graphs_without_duplicate_imports(): void
    {
        // Deliberately constructed test data, not real sensor measurements.
        file_put_contents($this->csv, "timestamp,motor_speed,temperature,ps1,ps2\n2026-09-20T12:00:00-07:00,-12.375,23.625,81.125,79.875\n2026-09-20T12:00:00-07:00,0,,82,80\n");
        $this->insertDeviceLogs(['serial_no' => 'unrelated', 'temp' => 11]);
        $this->seed(DeviceSeederRS::class);
        $this->seed(DeviceSeederRS::class);
        $this->assertSame(1, DB::table('devices')->count());
        $this->assertSame('andre.troncoso@tezca.net', DB::table('users')->where('id', DB::table('devices')->value('user_id'))->value('email'));
        $this->assertSame(3, DB::table('device_logs')->count());
        $points = app(SolarTrackerGraphData::class)->forSerial(DeviceSeederRS::SERIAL)['points'];
        $this->assertCount(2, $points);
        $this->assertSame(-12.375, $points[0]['motor_speed']);
        $this->assertSame(23.625, $points[0]['temp']);
        $this->assertSame(81.125, $points[0]['ps1']);
        $this->assertSame(79.875, $points[0]['ps2']);
        $this->assertNull($points[1]['temp']);
        $this->assertSame('2026-09-20 19:00:00', DB::table('device_logs')->where('serial_no', DeviceSeederRS::SERIAL)->value('updated_at'));
    }

    public function test_seeders_use_only_existing_target_account(): void
    {
        $target = \App\Models\User::factory()->create(['email' => 'andre.troncoso@tezca.net']);
        DB::table('devices')->insert([
            'serial_no' => DeviceSeederRS::SERIAL, 'order_no' => 'DEV-RS', 'user_id' => $target->id,
        ]);
        file_put_contents($this->csv, "timestamp,motor_speed,temperature,ps1,ps2\n2026-09-20 12:00:00,1,2,3,4\n");
        $this->seed(\Database\Seeders\DevelopmentDataSeeder::class);
        $this->seed(DeviceSeederRS::class);
        $this->assertSame(16, DB::table('devices')->where('user_id', $target->id)->count());
        $this->assertSame($target->password, $target->fresh()->password);
        $this->assertSame(1, DB::table('users')->count());
    }

    public function test_bad_csv_preserves_existing_history(): void
    {
        $this->insertDeviceLogs(['serial_no' => DeviceSeederRS::SERIAL, 'temp' => 17]);
        file_put_contents($this->csv, "timestamp,motor_speed,temperature,ps1,ps2\n2026-09-20 12:00:00,1,2,3,4\n2026-09-20 12:01:00,bad,2,3,4\n");
        try {
            $this->seed(DeviceSeederRS::class);
            $this->fail('Invalid sensor data must fail.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('invalid motor_speed', $error->getMessage());
        }
        $this->assertSame(0, DB::table('devices')->count());
        $this->assertSame(1, DB::table('device_logs')->count());
        $this->assertEquals(17, DB::table('device_logs')->value('data->temp'));
    }

    public function test_missing_csv_fails_without_creating_a_device(): void
    {
        config(['device-seeder-rs.csv_path' => $this->csv.'.missing']);
        $this->expectExceptionMessage('Sensor CSV not found/readable');
        $this->seed(DeviceSeederRS::class);
    }

    public function test_import_is_disabled_in_production(): void
    {
        $this->app->instance('env', 'production');
        $this->artisan('db:seed', ['--class' => DeviceSeederRS::class, '--force' => true])->assertExitCode(0);
        $this->assertSame(0, DB::table('devices')->count());
    }

    public function test_supplied_sensor_export_imports_all_rows_and_extra_readings(): void
    {
        config(['device-seeder-rs.csv_path' => database_path('data/device-real-scenario.csv')]);
        $this->seed(DeviceSeederRS::class);
        $logs = \App\Models\Api\DeviceLog::query()->where('serial_no', DeviceSeederRS::SERIAL);
        $this->assertSame(30491, $logs->count());
        $first = (clone $logs)->orderBy('updated_at')->first();
        $this->assertSame('2026-02-10 00:00:03', $first->updated_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-02-13 17:16:52', $logs->max('updated_at'));
        $this->assertEqualsWithDelta(76.4531, $first->ps1, 0.001);
        $this->assertEqualsWithDelta(72.7205, $first->ps2, 0.001);
        $this->assertEqualsWithDelta(37.4841, $first->temp, 0.001);
        $this->assertEqualsWithDelta(-0.373267, $first->motor_speed, 0.001);
        $this->assertEqualsWithDelta(74.5868, $first->ps_avg, 0.001);
        $this->assertEqualsWithDelta(-3.73267, $first->pds, 0.001);
        $this->assertEquals(0, $first->cts);
        $this->assertSame('solar track', $first->state);
    }
    private function insertDeviceLogs(array $rows): void
    {
        foreach (isset($rows['serial_no']) ? [$rows] : $rows as $row) {
            $log = (new \App\Models\Api\DeviceLog)->forceFill($row);
            DB::table('device_logs')->insert($log->getAttributes());
        }
    }
}
