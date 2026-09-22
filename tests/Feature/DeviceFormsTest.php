<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\GeoCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeviceFormsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::create([
            'name' => 'Device owner', 'email' => 'device-owner@example.test',
            'password' => 'test-hash', 'email_verified_at' => now(),
            'address_1' => '100 Main Street', 'city' => 'Vancouver',
            'state' => 'BC', 'zip_code' => 'V6B 1A1', 'country' => 'CA',
        ]);
        $this->actingAs($this->owner);
    }

    private function fields(array $overrides = []): array
    {
        return array_merge([
            'alias' => 'Garden tracker', 'serial_no' => 'SP1-forms-test',
            'sku' => 'SP1', 'order_no' => 'ORD-1024',
            'address_1' => '200 Garden Street', 'address_2' => 'Rear garden',
            'city' => 'Vancouver', 'address_state' => 'BC', 'zip_code' => 'V6B 1A1',
            'country' => 'CA', 'latitude' => '49.2827', 'longitude' => '-123.1207',
        ], $overrides);
    }

    private function device(array $overrides = []): Device
    {
        return Device::create($this->fields(array_merge([
            'user_id' => $this->owner->id,
            'status_notification' => true, 'sms_notification' => true,
        ], $overrides)));
    }

    public function test_forms_use_one_save_button_without_notification_controls_and_creation_uses_profile_address(): void
    {
        $this->get('/create-device')->assertRedirect('/dashboard?create=1');

        $device = $this->device();
        $edit = $this->get('/edit-device/'.$device->id)->assertOk()
            ->assertSee('Update device')
            ->assertSee($device->serial_no)
            ->assertDontSee('name="status_notification"', false)
            ->assertDontSee('name="sms_notification"', false)
            ->assertDontSee('name="serial_no"', false);
        preg_match('/<form id="device-form".*?<\/form>/s', $edit->getContent(), $editForm);
        $this->assertSame(1, substr_count($editForm[0], 'type="submit"'));
    }

    public function test_creation_saves_all_fields_and_coordinates_with_notifications_disabled(): void
    {
        $this->post('/create-device', $this->fields([
            'user_id' => 999, 'status_notification' => 1, 'sms_notification' => 1,
        ]))->assertRedirect(route('dashboard'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('devices', array_merge($this->fields(), [
            'user_id' => $this->owner->id, 'status_notification' => false, 'sms_notification' => false,
        ]));
        $this->assertDatabaseHas('geocode', [
            'serial_no' => 'SP1-forms-test', 'latitude' => 49.2827,
            'longitude' => -123.1207, 'status' => 'none',
        ]);
    }

    public function test_invalid_creation_preserves_input_and_does_not_save_either_record(): void
    {
        $this->from('/create-device')->post('/create-device', $this->fields([
            'city' => '', 'latitude' => 91, 'longitude' => -181,
        ]))->assertRedirect('/dashboard')
            ->assertSessionHasErrors(['city', 'latitude', 'longitude'])
            ->assertSessionHasInput('alias', 'Garden tracker');

        $this->assertSame(0, Device::count());
        $this->assertSame(0, GeoCode::count());
    }

    public function test_creation_rejects_an_existing_serial_number(): void
    {
        $this->device();
        $this->post('/create-device', $this->fields())
            ->assertSessionHasErrors('serial_no');
        $this->assertSame(1, Device::count());
        $this->assertSame(0, GeoCode::count());
    }

    public function test_single_update_saves_all_editable_fields_and_preserves_identity_and_preferences(): void
    {
        $device = $this->device();
        GeoCode::create([
            'serial_no' => $device->serial_no, 'latitude' => $device->latitude,
            'longitude' => $device->longitude, 'status' => 'connected',
        ]);
        $changes = [
            'alias' => 'Roof tracker', 'address_1' => '300 Roof Avenue', 'address_2' => null,
            'city' => 'Montréal', 'address_state' => 'Québec', 'zip_code' => 'H2Y 1C6', 'country' => 'CA',
            'latitude' => 45.5019, 'longitude' => -73.5674,
        ];

        $this->put('/edit-device/'.$device->id, array_merge($changes, [
            'serial_no' => 'different', 'sku' => 'different',
            'order_no' => 'different', 'user_id' => 999,
            'status_notification' => 0, 'sms_notification' => 0,
        ]))->assertRedirect('/edit-device/'.$device->id)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('devices', array_merge($changes, [
            'id' => $device->id, 'user_id' => $this->owner->id, 'serial_no' => $device->serial_no,
            'sku' => 'SP1', 'order_no' => 'ORD-1024',
            'status_notification' => true, 'sms_notification' => true,
        ]));
        $this->assertDatabaseHas('geocode', [
            'serial_no' => $device->serial_no, 'latitude' => 45.5019,
            'longitude' => -73.5674, 'status' => 'connected',
        ]);
    }

    public function test_invalid_or_incomplete_update_does_not_partially_save_the_device(): void
    {
        $device = $this->device();
        $original = $device->fresh()->getAttributes();
        $this->from('/edit-device/'.$device->id)->put('/edit-device/'.$device->id, [
            'alias' => 'Changed name', 'latitude' => 49,
        ])->assertRedirect('/edit-device/'.$device->id)
            ->assertSessionHasErrors(['address_1', 'city', 'address_state', 'country', 'zip_code', 'longitude'])
            ->assertSessionHasInput('alias', 'Changed name');
        $this->assertSame($original, $device->fresh()->getAttributes());
        $this->assertSame(0, GeoCode::count());
    }

    public function test_edit_allows_both_coordinates_to_be_empty_for_existing_devices(): void
    {
        $device = $this->device(['latitude' => null, 'longitude' => null]);
        $this->put('/edit-device/'.$device->id, $this->fields([
            'alias' => 'No coordinates yet', 'latitude' => '', 'longitude' => '',
        ]))->assertSessionHasNoErrors()->assertRedirect('/edit-device/'.$device->id);
        $this->assertDatabaseHas('devices', [
            'id' => $device->id, 'alias' => 'No coordinates yet', 'latitude' => null, 'longitude' => null,
        ]);
    }

    public function test_other_users_cannot_view_or_update_a_device_through_new_or_legacy_web_routes(): void
    {
        $device = $this->device();
        $other = User::create([
            'name' => 'Other', 'email' => 'other-device-owner@example.test',
            'password' => 'test-hash', 'email_verified_at' => now(),
        ]);
        $this->actingAs($other)->get('/edit-device/'.$device->id)->assertForbidden();
        $this->put('/edit-device/'.$device->id, $this->fields(['alias' => 'Stolen']))
            ->assertForbidden();
        $this->put('/edit-device/'.$device->id.'/address1', ['address_1' => 'Stolen'])
            ->assertForbidden();
        $this->put('/device/'.$device->id.'/update-product-alias', ['alias' => 'Stolen'])
            ->assertForbidden();
        $this->assertDatabaseHas('devices', [
            'id' => $device->id, 'alias' => 'Garden tracker', 'address_1' => '200 Garden Street',
        ]);
    }

    public function test_configured_developer_can_edit_and_missing_devices_return_not_found(): void
    {
        $device = $this->device();
        $admin = User::create([
            'name' => 'Admin', 'email' => 'device-admin@example.test',
            'password' => 'test-hash', 'email_verified_at' => now(),
        ]);
        config(['app.developer_email' => $admin->email]);
        $this->actingAs($admin)->get('/edit-device/'.$device->id)->assertOk();
        $this->put('/edit-device/'.$device->id, $this->fields(['alias' => 'Admin updated']))
            ->assertSessionHasNoErrors()->assertRedirect('/edit-device/'.$device->id);
        $this->get('/edit-device/99999')->assertNotFound();
        $this->put('/edit-device/99999', $this->fields())->assertNotFound();
    }
}
