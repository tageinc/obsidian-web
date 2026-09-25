<?php

namespace Tests\Feature;

use App\Models\Api\DeviceLog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeviceLogMigrationTest extends TestCase
{
    public function test_upgrade_preserves_all_rows_values_metadata_and_indexes(): void
    {
        (require database_path('migrations/2026_09_20_000000_create_device_application_tables.php'))->up();
        Schema::table('solar_tracker_logs', function (Blueprint $table) {
            $table->string('firmware_version')->nullable();
            $table->string('config_version')->nullable();
        });
        $rows = [];
        for ($id = 1; $id <= 501; $id++) {
            $rows[] = [
                'id' => $id, 'serial_no' => 'migration-test', 'ps1' => 0, 'ps2' => -1.125,
                'temp' => null, 'cts' => 0, 'state' => 'solar track', 'firmware_version' => '001.230', 'config_version' => '002.010',
                'created_at' => '2026-09-20 01:00:00', 'updated_at' => '2026-09-20 02:00:00',
            ];
        }
        foreach (array_chunk($rows, 50) as $chunk) {
            DB::table('solar_tracker_logs')->insert($chunk);
        }
        $before = DB::table('solar_tracker_logs')->orderBy('id')->get();
        $migration = require database_path('migrations/2026_09_24_000000_convert_solar_tracker_logs_to_device_logs.php');
        $migration->up();
        $migration->up(); // Safe after a completed backfill/DDL retry.
        $this->assertFalse(Schema::hasTable('solar_tracker_logs'));
        $this->assertEqualsCanonicalizing(['id', 'serial_no', 'data', 'created_at', 'updated_at'], Schema::getColumnListing('device_logs'));
        $this->assertSame(501, DeviceLog::count());
        foreach ($before as $original) {
            $row = DB::table('device_logs')->where('id', $original->id)->first();
            $this->assertSame($original->serial_no, $row->serial_no);
            $this->assertSame($original->created_at, $row->created_at);
            $this->assertSame($original->updated_at, $row->updated_at);
            $expected = (array) $original;
            foreach (['id', 'serial_no', 'created_at', 'updated_at'] as $key) {
                unset($expected[$key]);
            }
            $this->assertSame($expected, json_decode($row->data, true));
        }
        $this->artisan('device:audit-telemetry')->assertExitCode(0);
        $this->assertGreaterThan(501, DeviceLog::factory()->create()->id);
    }

    public function test_retry_preserves_json_for_columns_already_removed(): void
    {
        Schema::create('device_logs', function (Blueprint $table) {
            $table->id();
            $table->string('serial_no');
            $table->json('data')->nullable();
            $table->float('temp')->nullable();
            $table->timestamps();
        });
        DB::table('device_logs')->insert([
            'serial_no' => 'retry', 'temp' => 12.5,
            'data' => json_encode(['ps1' => 0, 'firmware_version' => '001.020', 'config_version' => '002.001']),
        ]);
        (require database_path('migrations/2026_09_24_000000_convert_solar_tracker_logs_to_device_logs.php'))->up();
        $this->assertSame([
            'ps1' => 0, 'firmware_version' => '001.020', 'config_version' => '002.001', 'temp' => 12.5,
        ], DeviceLog::firstOrFail()->data);
    }

    public function test_rollback_refuses_to_discard_json_extension_data(): void
    {
        $migration = require database_path('migrations/2026_09_24_000000_convert_solar_tracker_logs_to_device_logs.php');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cannot be safely reduced');
        $migration->down();
    }
}
