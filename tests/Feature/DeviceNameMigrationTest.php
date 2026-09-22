<?php
namespace Tests\Feature;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
class DeviceNameMigrationTest extends TestCase
{
    use RefreshDatabase;
    public function test_rename_preserves_existing_device_names(): void
    {
        $device = Device::factory()->create(['name' => 'Existing tracker']);
        $migration = require database_path('migrations/2026_09_22_000002_rename_device_alias_to_name.php');
        $migration->down();
        $this->assertSame('Existing tracker', DB::table('devices')->where('id', $device->id)->value('alias'));
        $migration->up();
        $this->assertFalse(Schema::hasColumn('devices', 'alias'));
        $this->assertSame('Existing tracker', $device->fresh()->name);
    }
}
