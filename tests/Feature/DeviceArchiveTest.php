<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\GeoCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeviceArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_retire_preserves_device_address_and_history_and_hides_it_from_active_lists(): void
    {
        $owner = User::factory()->create();
        $device = Device::factory()->create(['user_id' => $owner->id, 'address_state' => 'CA']);
        GeoCode::create(['serial_no' => $device->serial_no, 'status' => 'sleep']);
        DB::table('solar_tracker_logs')->insert(['serial_no' => $device->serial_no, 'temp' => 23]);
        $this->assertSame('active', $device->state);
        $this->actingAs($owner)->post('/retire-device/'.$device->id)->assertRedirect('/dashboard');
        $this->assertSame('inactive', $device->fresh()->state);
        $this->assertSame('CA', $device->fresh()->address_state);
        $this->assertDatabaseHas('geocode', ['serial_no' => $device->serial_no, 'status' => 'sleep']);
        $this->assertDatabaseHas('solar_tracker_logs', ['serial_no' => $device->serial_no, 'temp' => 23]);
        $this->get('/all-devices')->assertOk()->assertExactJson([]);
        $this->get('/paginated-devices')->assertOk()->assertJsonCount(0, 'data');
        $this->post('/retire-device/'.$device->id)->assertRedirect('/dashboard');
        $this->assertDatabaseCount('devices', 1);
    }

    public function test_other_users_cannot_retire_and_legacy_delete_route_is_gone(): void
    {
        $device = Device::factory()->create(['user_id' => User::factory()->create()->id]);
        $this->actingAs(User::factory()->create())->post('/retire-device/'.$device->id)->assertForbidden();
        $this->get('/delete-device/'.$device->id)->assertNotFound();
        $this->get('/retire-device/'.$device->id)->assertStatus(405);
        $this->assertSame('active', $device->fresh()->state);
    }

    public function test_owner_can_reactivate_an_inactive_device_without_losing_history(): void
    {
        $owner = User::factory()->create();
        $device = Device::factory()->create(['user_id' => $owner->id, 'state' => 'inactive']);
        GeoCode::create(['serial_no' => $device->serial_no, 'status' => 'sleep']);

        $this->actingAs($owner)->post('/reactivate-device/'.$device->id)
            ->assertRedirect('/dashboard')->assertSessionHas('success', 'Device reactivated successfully.');

        $this->assertDatabaseHas('devices', ['id' => $device->id, 'state' => 'active']);
        $this->assertDatabaseHas('geocode', ['serial_no' => $device->serial_no, 'status' => 'sleep']);
        $this->get('/all-devices')->assertOk()->assertJsonPath('0.id', $device->id);
        $this->post('/reactivate-device/'.$device->id)->assertRedirect('/dashboard');
        $this->actingAs(User::factory()->create())->post('/reactivate-device/'.$device->id)->assertForbidden();
    }

    public function test_model_disallows_deleting_a_device(): void
    {
        $device = Device::factory()->create();
        $this->expectException(\LogicException::class);
        $device->delete();
    }

    public function test_api_retires_only_owned_devices_and_has_no_delete_endpoint(): void
    {
        $owner = User::factory()->create();
        $device = Device::factory()->create(['user_id' => $owner->id]);
        $this->actingAs(User::factory()->create(), 'sanctum')->postJson('/api/device/'.$device->id.'/retire')->assertForbidden();
        $this->actingAs($owner, 'sanctum')->postJson('/api/device/'.$device->id.'/retire')
            ->assertOk()->assertJsonPath('state', 'inactive');
        $this->deleteJson('/api/device/'.$device->id)->assertNotFound();
        $this->assertDatabaseHas('devices', ['id' => $device->id, 'state' => 'inactive']);
    }

    public function test_upgrade_preserves_address_regions_and_defaults_existing_devices_to_active(): void
    {
        $migration = require database_path('migrations/2026_09_22_000001_add_device_archive_state.php');
        $migration->down();
        DB::table('devices')->insert(['serial_no' => 'old-device', 'state' => 'California']);
        $migration->up();
        $this->assertDatabaseHas('devices', [
            'serial_no' => 'old-device', 'address_state' => 'California', 'state' => 'active',
        ]);
    }

    public function test_device_status_is_separate_from_its_archive_state(): void
    {
        $owner = User::factory()->create();
        $device = Device::factory()->create(['user_id' => $owner->id]);
        GeoCode::create(['serial_no' => $device->serial_no, 'status' => 'sleep']);
        $this->actingAs($owner)->get('/all-devices')->assertOk()
            ->assertJsonPath('0.state', 'active')->assertJsonPath('0.status', 'sleep');
    }
}
