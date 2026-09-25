<?php

namespace Tests\Unit;

use App\Services\SolarTrackerGraphData;
use App\Http\Controllers\ViewDeviceController;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SolarTrackerGraphDataTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('solar_tracker_logs', function (Blueprint $table) {
            $table->id(); $table->string('serial_no'); $table->float('temp')->nullable();
            $table->float('ps1')->nullable(); $table->float('ps2')->nullable();
            $table->float('ps_avg')->nullable(); $table->float('motor_speed')->nullable(); $table->timestamps();
        });
        (require database_path('migrations/2026_09_24_000000_convert_solar_tracker_logs_to_device_logs.php'))->up();
    }

    public function test_it_sorts_readings_and_uses_pacific_timestamps_for_duplicate_samples(): void
    {
        $later = Carbon::parse('2026-11-01 09:30:00', 'UTC'); // 1:30 AM PST after DST ends
        $earlier = Carbon::parse('2026-11-01 08:30:00', 'UTC'); // 1:30 AM PDT before DST ends
        $this->insertDeviceLogs([
            ['serial_no' => 'SP1', 'temp' => 20, 'updated_at' => $later, 'created_at' => $later],
            ['serial_no' => 'SP1', 'temp' => 10, 'updated_at' => $earlier, 'created_at' => $earlier],
            ['serial_no' => 'SP1', 'temp' => 30, 'updated_at' => $later, 'created_at' => $later],
        ]);

        $graph = app(SolarTrackerGraphData::class)->forSerial('SP1');

        $this->assertSame('America/Los_Angeles', $graph['timezone']);
        $this->assertCount(3, $graph['points']);
        $this->assertSame([2, 1, 3], array_column($graph['points'], 'id'));
        $this->assertLessThan($graph['points'][1]['epoch_ms'], $graph['points'][0]['epoch_ms']);
        $this->assertSame($graph['points'][1]['epoch_ms'], $graph['points'][2]['epoch_ms']);
        $this->assertSame([10.0, 20.0, 30.0], array_column($graph['points'], 'temp'));
        $this->assertStringContainsString('PDT', $graph['points'][0]['label']);
        $this->assertStringContainsString('PST', $graph['points'][1]['label']);
    }

    public function test_it_returns_an_empty_graph_without_inventing_time_labels(): void
    {
        $graph = app(SolarTrackerGraphData::class)->forSerial('missing');
        $this->assertSame([], $graph['points']);
    }

    public function test_it_preserves_raw_sensor_values_zero_null_and_precision(): void
    {
        $this->insertDeviceLogs([
            'serial_no' => 'SP1', 'temp' => 21.123456, 'ps1' => 0, 'ps2' => 52.789,
            'ps_avg' => 999, 'pds' => -3.73267, 'motor_speed' => null, 'updated_at' => '2026-09-21 07:00:00',
        ]);
        $point = app(SolarTrackerGraphData::class)->forSerial('SP1')['points'][0];
        $this->assertSame(21.123456, $point['temp']);
        $this->assertSame(0.0, $point['ps1']);
        $this->assertSame(52.789, $point['ps2']);
        $this->assertNull($point['motor_speed']);
        $this->assertSame(999.0, $point['ps_avg']);
        $this->assertSame(-3.73267, $point['pds']);
        $this->assertSame('2026-09-21T00:00:00-07:00', $point['timestamp']);
    }

    public function test_it_preserves_missing_and_zero_average_and_pds_readings(): void
    {
        $this->insertDeviceLogs([
            ['serial_no' => 'SP1', 'ps1' => 10, 'ps2' => 20, 'updated_at' => '2026-09-21 07:00:00'],
            ['serial_no' => 'SP1', 'ps_avg' => null, 'pds' => null, 'updated_at' => '2026-09-21 08:00:00'],
            ['serial_no' => 'SP1', 'ps_avg' => 0, 'pds' => 0, 'updated_at' => '2026-09-21 09:00:00'],
        ]);

        $points = app(SolarTrackerGraphData::class)->forSerial('SP1')['points'];
        $this->assertSame([null, null, 0.0], array_column($points, 'ps_avg'));
        $this->assertSame([null, null, 0.0], array_column($points, 'pds'));
    }

    public function test_limit_keeps_latest_raw_readings_in_chronological_order_for_only_this_device(): void
    {
        $this->insertDeviceLogs([
            ['serial_no' => 'SP1', 'temp' => 10, 'updated_at' => '2026-09-21 01:00:00'],
            ['serial_no' => 'SP1', 'temp' => 20, 'updated_at' => '2026-09-21 02:00:00'],
            ['serial_no' => 'SP1', 'temp' => 30, 'updated_at' => '2026-09-21 02:00:00'],
            ['serial_no' => 'other', 'temp' => 99, 'updated_at' => '2026-09-21 03:00:00'],
            ['serial_no' => 'SP1', 'temp' => 40, 'updated_at' => null],
        ]);
        $points = app(SolarTrackerGraphData::class)->forSerial('SP1', 2)['points'];
        $this->assertSame([2, 3], array_column($points, 'id'));
        $this->assertSame([20.0, 30.0], array_column($points, 'temp'));
    }

    public function test_graph_response_has_raw_points_without_legacy_averages(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id(); $table->string('serial_no'); $table->integer('hardware_id');
        });
        DB::table('devices')->insert(['id' => 1, 'serial_no' => 'SP1', 'hardware_id' => 1]);
        $this->insertDeviceLogs([
            'serial_no' => 'SP1', 'temp' => 12.5, 'ps_avg' => 42.5, 'pds' => -1.25,
            'updated_at' => '2026-09-21 01:00:00',
        ]);
        $response = app(ViewDeviceController::class)->getDeviceData(1);
        $data = $response->getData(true);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['graph'], array_keys($data));
        $this->assertEquals(12.5, $data['graph']['points'][0]['temp']);
        $this->assertEquals(42.5, $data['graph']['points'][0]['ps_avg']);
        $this->assertEquals(-1.25, $data['graph']['points'][0]['pds']);
    }
    private function insertDeviceLogs(array $rows): void
    {
        foreach (isset($rows['serial_no']) ? [$rows] : $rows as $row) {
            $log = (new \App\Models\Api\DeviceLog)->forceFill($row);
            DB::table('device_logs')->insert($log->getAttributes());
        }
    }
}
