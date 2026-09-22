<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\GeoCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesFrontendManifest;
use Tests\TestCase;

class DeviceModalTest extends TestCase
{
    use RefreshDatabase;
    use UsesFrontendManifest;

    private User $owner;
    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useFrontendManifest();
        $this->travelTo(now()->startOfMinute());
        config([
            'frontend.vue3.workspace' => false,
            'frontend.vue3.create_device' => true,
            'frontend.vue3.device_edit' => true,
            'frontend.vue3.view_device' => true,
        ]);
        $this->owner = User::create([
            'name' => 'Modal owner', 'email' => 'device-modal-owner@example.test',
            'password' => 'never-serialize-password-hash', 'email_verified_at' => now(),
            'address_1' => '10 Profile Street',
        ]);
        $this->device = Device::create(array_merge($this->fields(), [
            'serial_no' => 'modal-owned-tracker', 'user_id' => $this->owner->id,
            'sku' => 'SP1', 'order_no' => 'MODAL-ORDER',
            'status_notification' => true, 'sms_notification' => true,
        ]));
    }

    private function fields(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Garden tracker', 'address_1' => '20 Garden Street', 'address_2' => 'Rear garden',
            'city' => 'Los Angeles', 'address_state' => 'CA', 'zip_code' => '90012', 'country' => 'US',
            'latitude' => '34.0522', 'longitude' => '-118.2437',
        ], $overrides);
    }

    private function modal(string $url)
    {
        return $this->getJson($url, ['X-Obsidian-Modal' => '1']);
    }

    private function modalPaths(): array
    {
        return [
            '/edit-device/'.$this->device->id => 'edit-device',
            '/devices/'.$this->device->id => 'view-device',
        ];
    }

    public function test_modal_dtos_match_authorized_blade_props_without_the_workspace_flag(): void
    {
        $this->actingAs($this->owner);
        foreach ($this->modalPaths() as $path => $page) {
            $url = $path.'?source=dashboard';
            if ($page === 'edit-device') {
                $this->get($url)->assertRedirect('/devices/'.$this->device->id.'?edit=1');
                $this->modal($url)->assertOk()->assertJsonPath('page', 'edit-device');
                continue;
            }
            $html = $this->get($url)->assertOk()->assertDontSee('data-workspace="1"', false);
            $this->assertSame(1, preg_match('/<script id="frontend-'.preg_quote($page, '/').'" type="application\/json">(.*?)<\/script>/s', $html->getContent(), $matches));
            $expected = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
            $json = $this->modal($url)->assertOk()->assertJsonPath('page', $page)->assertJsonPath('url', $url);
            $this->assertSame(['page', 'props', 'url'], array_keys($json->json()));
            $this->assertSame($expected, $json->json('props'));
            $this->assertStringNotContainsString('never-serialize-password-hash', $json->getContent());
            foreach ([$html, $json] as $response) {
                $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
                $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
                foreach (['Accept', 'X-Obsidian-Page', 'X-Obsidian-Modal'] as $header) {
                    $this->assertContains($header, $response->headers->all('vary'));
                }
            }
        }

        $edit = $this->modal('/edit-device/'.$this->device->id)->json('props');
        $this->assertSame(['serial_no', 'sku', 'order_no', 'name', 'address_1', 'address_2', 'city', 'address_state', 'zip_code', 'country', 'latitude', 'longitude'], array_keys($edit['values']));
        $this->assertSame(['serialNo', 'sku', 'orderNo'], array_keys($edit['fixedIdentity']));
        $info = $this->modal('/devices/'.$this->device->id)->json('props');
        $this->assertSame(['editing', 'values', 'device', 'status', 'remote', 'points', 'csrfToken', 'links'], array_keys($info));
        $this->assertSame(['name', 'serial', 'details'], array_keys($info['device']));
        $this->assertArrayNotHasKey('user_id', $info['device']);
    }

    public function test_modal_negotiation_requires_both_headers_and_only_the_requested_page_flag(): void
    {
        $this->actingAs($this->owner);
        $path = '/edit-device/'.$this->device->id;
        $this->getJson($path)->assertRedirect('/devices/'.$this->device->id.'?edit=1');
        $this->get($path, ['X-Obsidian-Modal' => '1', 'Accept' => 'text/html'])
            ->assertRedirect('/devices/'.$this->device->id.'?edit=1');
        // Workspace navigation retains its separate, stronger rollout requirements.
        $this->getJson($path, ['X-Obsidian-Page' => '1'])->assertRedirect('/devices/'.$this->device->id.'?edit=1');
        config(['frontend.vue3.view_device' => false]);
        $this->modal($path)->assertOk();
        $this->modal('/devices/'.$this->device->id)->assertStatus(409);
        config(['frontend.vue3.device_edit' => false]);
        $this->modal($path)->assertStatus(409);
        config(['frontend.vue3.create_device' => false]);
        $this->modal('/create-device')->assertRedirect('/dashboard?create=1');
        $this->get($path)->assertRedirect('/devices/'.$this->device->id.'?edit=1');
    }

    public function test_noncanonical_and_nondevice_page_requests_require_document_navigation(): void
    {
        $this->actingAs($this->owner);
        foreach ([
            '/edit-device/0'.$this->device->id,
            '/devices/0'.$this->device->id,
            'http://localhost/devices/'.$this->device->id.'/?source=compatibility',
            '/dashboard', '/profile',
        ] as $url) {
            if (str_contains($url, '/edit-device/')) {
                $this->get($url)->assertRedirect();
            } else {
                $this->get($url)->assertOk();
            }
            $this->modal($url)->assertStatus(409)
                ->assertExactJson(['message' => 'This page requires document navigation.']);
        }
    }

    public function test_modal_requests_preserve_guest_verification_and_owner_developer_boundaries(): void
    {
        foreach ($this->modalPaths() as $path => $page) {
            $this->modal($path)->assertUnauthorized();
        }
        $other = User::create([
            'name' => 'Other', 'email' => 'device-modal-other@example.test', 'password' => 'hash',
        ]);
        $this->actingAs($other);
        foreach ($this->modalPaths() as $path => $page) {
            $this->modal($path)->assertForbidden();
        }
        $other->update(['email_verified_at' => now()]);
        $this->modal('/create-device')->assertRedirect('/dashboard?create=1');
        foreach (['/edit-device/', '/devices/'] as $prefix) {
            $this->modal($prefix.$this->device->id)->assertForbidden();
        }
        config(['app.developer_email' => $other->email]);
        foreach ($this->modalPaths() as $path => $page) {
            $this->modal($path)->assertOk()->assertJsonPath('page', $page);
        }
    }

    public function test_missing_and_archived_devices_keep_existing_response_contracts(): void
    {
        $this->actingAs($this->owner);
        $this->modal('/edit-device/999999')->assertNotFound();
        $this->modal('/devices/999999')->assertNotFound();
        $this->modal('/devices/'.$this->device->id)->assertOk();
    }

    public function test_json_updates_save_all_editable_fields_and_geocode_including_identity(): void
    {
        GeoCode::create([
            'serial_no' => $this->device->serial_no, 'latitude' => $this->device->latitude,
            'longitude' => $this->device->longitude, 'status' => 'connected',
        ]);
        $changes = $this->fields([
            'name' => 'Roof tracker', 'address_1' => '30 Roof Street', 'address_2' => null,
            'latitude' => '34.0540', 'longitude' => '-118.2450',
        ]);
        $this->actingAs($this->owner)->putJson('/edit-device/'.$this->device->id, array_merge($changes, [
            'user_id' => 999, 'serial_no' => 'changed', 'sku' => 'changed',
            'order_no' => 'changed', 'status_notification' => 0, 'sms_notification' => 0,
        ]))->assertOk()->assertExactJson(['message' => 'Device updated successfully.'])
            ->assertSessionMissing('success');
        $this->assertDatabaseHas('devices', array_merge($changes, [
            'id' => $this->device->id, 'user_id' => $this->owner->id,
            'serial_no' => 'changed',
            'sku' => 'changed', 'order_no' => 'changed',
            'status_notification' => true, 'sms_notification' => true,
        ]));
        $this->assertDatabaseHas('geocode', [
            'serial_no' => 'changed', 'latitude' => 34.0540,
            'longitude' => -118.2450, 'status' => 'connected',
        ]);

        $this->putJson('/edit-device/'.$this->device->id, $changes, ['X-Obsidian-Modal' => '1'])
            ->assertOk()->assertExactJson(['message' => 'Device updated successfully.'])
            ->assertSessionHas('success', 'Device updated successfully.');
    }

    public function test_json_validation_errors_and_unauthorized_updates_never_write(): void
    {
        $original = $this->device->fresh()->getAttributes();
        $path = '/edit-device/'.$this->device->id;
        $this->putJson($path, $this->fields())->assertUnauthorized();
        $this->owner->update(['email_verified_at' => null]);
        $this->actingAs($this->owner)->putJson($path, $this->fields())->assertForbidden();
        $this->owner->update(['email_verified_at' => now()]);
        $this->putJson($path, $this->fields(['name' => 'Changed', 'city' => '', 'latitude' => 91]))
            ->assertUnprocessable()->assertJsonValidationErrors(['city', 'latitude']);
        $other = User::create([
            'name' => 'Other', 'email' => 'device-modal-writer@example.test',
            'password' => 'hash', 'email_verified_at' => now(),
        ]);
        $this->actingAs($other)->putJson($path, $this->fields(['name' => 'Stolen']))->assertForbidden();
        $this->assertSame($original, $this->device->fresh()->getAttributes());
        $this->assertDatabaseCount('geocode', 0);

        config(['app.developer_email' => $other->email]);
        $this->putJson($path, $this->fields(['name' => 'Developer updated']))->assertOk();
        $this->assertDatabaseHas('devices', ['id' => $this->device->id, 'name' => 'Developer updated']);
        $this->putJson('/edit-device/999999', $this->fields())->assertNotFound();
    }
}
