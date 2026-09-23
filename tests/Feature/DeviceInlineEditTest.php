<?php
namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeviceInlineEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_inline_updates_preserve_history_and_reject_protected_fields(): void
    {
        $owner = User::factory()->create();
        $device = Device::factory()->create(['user_id' => $owner->id, 'serial_no' => 'original']);
        DB::table('solar_tracker_logs')->insert(['serial_no' => 'original', 'ps1' => 42]);
        DB::table('solar_tracker_remote_controls')->insert(['serial_no' => 'original', 'mode' => 1, 'motor_speed' => 10]);
        DB::table('geocode')->insert(['serial_no' => 'original', 'status' => 'sleep']);
        $this->actingAs($owner)->patchJson('/devices/'.$device->id, ['sku' => 'SP2', 'order_no' => 'ORDER'])
            ->assertOk()->assertJsonPath('values.serial_no', 'original');
        foreach (['solar_tracker_logs', 'solar_tracker_remote_controls', 'geocode'] as $table) {
            $this->assertDatabaseHas($table, ['serial_no' => 'original']);
        }
        $this->patchJson('/devices/'.$device->id, ['latitude' => 34.1, 'longitude' => -118.2])->assertOk();
        $this->assertDatabaseHas('geocode', ['serial_no' => 'original', 'status' => 'sleep', 'latitude' => 34.1]);
        $this->patchJson('/devices/'.$device->id, ['state' => 'inactive', 'status' => 'online'])->assertUnprocessable();
        $this->assertSame('active', $device->fresh()->state);
        $this->patchJson('/devices/'.$device->id, ['latitude' => 91])->assertUnprocessable();
        $this->actingAs(User::factory()->create())->patchJson('/devices/'.$device->id, ['name' => 'forbidden'])->assertForbidden();
    }

    public function test_modal_updates_identity_and_preserves_linked_history(): void
    {
        $owner = User::factory()->create();
        $device = Device::factory()->create(['user_id' => $owner->id, 'serial_no' => 'before']);
        DB::table('solar_tracker_logs')->insert(['serial_no' => 'before', 'ps1' => 42]);
        $fields = ['name' => 'Tracker', 'address_1' => '123 Main', 'address_2' => null,
            'city' => 'Los Angeles', 'address_state' => 'CA', 'zip_code' => '90012', 'country' => 'US',
            'serial_no' => 'before', 'sku' => 'SP2', 'order_no' => 'ORDER-2'];
        $this->actingAs($owner)->putJson('/edit-device/'.$device->id, $fields, ['X-Obsidian-Modal' => '1'])->assertOk();
        $this->assertDatabaseHas('devices', ['id' => $device->id, 'serial_no' => 'before', 'sku' => 'SP2', 'order_no' => 'ORDER-2']);
        $this->assertDatabaseHas('solar_tracker_logs', ['serial_no' => 'before', 'ps1' => 42]);
        config(['frontend.vue3.device_edit' => true]);
        $this->getJson('/edit-device/'.$device->id, ['X-Obsidian-Modal' => '1'])
            ->assertOk()->assertJsonPath('props.values.serial_no', 'before')->assertJsonPath('props.values.sku', 'SP2')->assertJsonPath('props.values.order_no', 'ORDER-2');
        $this->putJson('/edit-device/'.$device->id, array_merge($fields, ['serial_no' => 'unused']))->assertUnprocessable()->assertJsonValidationErrors('serial_no');
        $this->assertSame('before', $device->fresh()->serial_no);
    }

    public function test_serial_changes_are_rejected_and_edit_page_redirects_to_modal(): void
    {
        $owner = User::factory()->create();
        $device = Device::factory()->create(['user_id' => $owner->id, 'serial_no' => 'original']);
        $this->actingAs($owner)->patchJson('/devices/'.$device->id, ['serial_no' => 'unused'])->assertUnprocessable()->assertJsonValidationErrors('serial_no');
        $this->patchJson('/devices/'.$device->id, ['serial_no' => null])->assertUnprocessable()->assertJsonValidationErrors('serial_no');
        $this->assertSame('original', $device->fresh()->serial_no);
        $this->get('/edit-device/'.$device->id)->assertRedirect('/devices/'.$device->id.'?edit=1');
        config(['frontend.vue3.device_edit' => true]);
        $this->getJson('/edit-device/'.$device->id, ['X-Obsidian-Modal' => '1'])
            ->assertOk()->assertJsonPath('page', 'edit-device')->assertJsonPath('props.creating', false);
    }
}
