<?php

namespace Tests\Feature;

use App\Models\ConfigVersions;
use App\Models\Device;
use App\Models\FirmwareVersions;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\Concerns\UsesFrontendManifest;
use Tests\TestCase;

class VueWorkflowPagesTest extends TestCase
{
    use RefreshDatabase;
    use UsesFrontendManifest;

    private User $owner;
    private User $other;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useFrontendManifest();
        config([
            'frontend.vue3.profile' => true,
            'frontend.vue3.create_device' => true,
            'frontend.vue3.device_edit' => true,
            'frontend.vue3.dashboard' => true,
            'frontend.vue3.developer' => true,
            'app.developer_email' => 'admin@example.test',
        ]);
        foreach (['owner', 'other', 'admin'] as $role) {
            $this->{$role} = User::create([
                'name' => ucfirst($role), 'email' => $role.'@example.test',
                'password' => 'hash-must-not-be-serialized', 'email_verified_at' => now(),
                'address_1' => '1 Example Street', 'city' => 'Toronto', 'state' => 'ON',
                'zip_code' => 'M5V 1A1', 'country' => 'CA',
            ]);
        }
    }

    private function props($response, string $page): array
    {
        $response->assertOk()->assertSee('data-vue-page="'.$page.'"', false);
        $this->assertSame(1, preg_match('/<script id="frontend-'.preg_quote($page, '/').'" type="application\/json">(.*?)<\/script>/s', $response->getContent(), $matches));

        return json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
    }

    private function device(User $owner, string $serial, array $attributes = []): Device
    {
        return Device::create(array_merge([
            'user_id' => $owner->id, 'serial_no' => $serial,
            'name' => $serial.' name', 'sku' => 'SKU', 'order_no' => 'ORDER',
            'address_1' => '1 Device Street', 'city' => 'Vancouver', 'address_state' => 'BC',
            'zip_code' => 'V6B 1A1', 'country' => 'CA', 'latitude' => 0, 'longitude' => 0,
        ], $attributes));
    }

    public function test_profile_payload_retains_only_safe_form_values_and_server_feedback(): void
    {
        $unsafeName = '</script><script>window.__unexpected=1</script>';
        $errors = (new ViewErrorBag)->put('default', new MessageBag(['city' => ['Please enter a city.']]));
        $response = $this->actingAs($this->owner)->withSession([
            '_old_input' => ['name' => $unsafeName, 'city' => 'Retained city', 'password' => 'password-must-not-be-serialized', 'password_confirmation' => 'password-must-not-be-serialized', 'user_id' => $this->other->id],
            'errors' => $errors, 'success' => 'Saved profile',
        ])->get('/profile');
        $props = $this->props($response, 'profile');
        $this->assertSame($unsafeName, $props['values']['name']);
        $this->assertSame('Retained city', $props['values']['city']);
        $this->assertSame('M5V 1A1', $props['values']['zip_code']);
        $this->assertSame(['Please enter a city.'], $props['errors']['city']);
        $this->assertSame('Saved profile', $props['success']);
        $this->assertSame(route('profile.update'), $props['action']);
        $this->assertSame(csrf_token(), $props['csrfToken']);
        $this->assertSame(['name', 'email', 'phone_number', 'address_1', 'address_2', 'city', 'state', 'zip_code', 'country'], array_keys($props['values']));
        $response->assertDontSee($unsafeName, false)
            ->assertDontSee('password-must-not-be-serialized')
            ->assertDontSee('hash-must-not-be-serialized')
            ->assertDontSee('js/app.js', false);
    }

    public function test_device_creation_payload_uses_profile_defaults_and_allowlisted_hardware(): void
    {
        $props = $this->props($this->actingAs($this->owner)->withSession([
            '_old_input' => ['_creation_modal' => '1', 'name' => 'Retained name', 'latitude' => '0', 'status_notification' => 1, 'user_id' => $this->other->id],
        ])->get('/dashboard'), 'dashboard')['creation'];
        $this->assertSame(route('create-device.store'), $props['action']);
        $this->assertSame('Retained name', $props['values']['name']);
        $this->assertSame('0', $props['values']['latitude']);
        $this->assertSame('1 Example Street', $props['values']['address_1']);
        $this->assertSame('CA', $props['values']['country']);
        $this->assertArrayNotHasKey('user_id', $props['values']);
        $this->assertArrayNotHasKey('status_notification', $props['values']);
        $this->assertArrayNotHasKey('sms_notification', $props['values']);
    }

    public function test_device_edit_payload_preserves_fixed_identity_and_server_ownership_policy(): void
    {
        $device = $this->device($this->owner, 'OWNED');
        $this->actingAs($this->other)->get('/edit-device/'.$device->id)->assertForbidden();
        foreach ([$this->owner, $this->admin] as $user) {
            $props = $this->actingAs($user)->withSession([
                '_old_input' => ['name' => 'Retained', 'serial_no' => 'TAMPERED', 'latitude' => '', 'longitude' => ''],
            ])->getJson('/edit-device/'.$device->id, ['X-Obsidian-Modal' => '1'])->assertOk()->json('props');
            $this->assertFalse($props['creating']);
            $this->assertSame(route('device.update', $device->id), $props['action']);
            $this->assertSame('OWNED', $props['fixedIdentity']['serialNo']);
            $this->assertSame('Retained', $props['values']['name']);
            $this->assertSame('', $props['values']['latitude']);
            $this->assertSame('TAMPERED', $props['values']['serial_no']);
            $this->assertArrayNotHasKey('user_id', $props['values']);
        }
    }

    public function test_dashboard_payload_paginates_own_devices_and_preserves_query_and_flash_contracts(): void
    {
        $this->device($this->owner, 'OWNED-1');
        $this->device($this->owner, 'OWNED-2', ['sku' => '  <SKU>  ', 'address_1' => '  1 Device Street  ', 'address_2' => ' Suite 2 ']);
        $this->device($this->other, 'OTHER-PRIVATE');
        $response = $this->actingAs($this->owner)->withSession(['success' => 'Saved device'])->get('/dashboard?show=1&page=2');
        $props = $this->props($response, 'dashboard');
        $this->assertCount(1, $props['devices']);
        $this->assertSame(2, $props['pagination']['currentPage']);
        $this->assertSame(1, $props['pagination']['perPage']);
        $this->assertSame(2, $props['pagination']['total']);
        $this->assertSame(2, $props['pagination']['lastPage']);
        $this->assertSame('Saved device', $props['success']);
        $this->assertSame(route('all-devices'), $props['mapEndpoints']['all']);
        $this->assertSame(route('paginated-devices'), $props['mapEndpoints']['paginated']);
        $this->assertSame(['id', 'name', 'serial', 'sku', 'address', 'state', 'status', 'lastUpdated', 'links'], array_keys($props['devices'][0]));
        $this->assertSame('OWNED-2', $props['devices'][0]['serial']);
        $this->assertSame('<SKU>', $props['devices'][0]['sku']);
        $this->assertSame('1 Device Street, Suite 2', $props['devices'][0]['address']);
        $response->assertDontSee('<SKU>', false);
        foreach ($props['pagination']['links'] as $link) {
            if ($link['url']) $this->assertStringContainsString('show=1', $link['url']);
            $this->assertStringNotContainsString('<', $link['label']);
        }
        $response->assertDontSee('OTHER-PRIVATE')->assertDontSee('hash-must-not-be-serialized');
        $this->get('/device-manager?show=20&page=2')->assertRedirect('/dashboard?show=20&page=2');
        $this->getJson('/all-devices')->assertOk()->assertJsonCount(2)->assertJsonMissing(['serial_no' => 'OTHER-PRIVATE']);
        $this->getJson('/paginated-devices?show=1&page=2')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('total', 2);
    }

    public function test_dashboard_detail_fields_use_null_for_missing_text_and_preserve_zero_values(): void
    {
        $this->device($this->owner, 'MISSING', ['sku' => null, 'address_1' => null, 'address_2' => '   ']);
        $this->device($this->owner, '0', ['sku' => '0', 'address_1' => '', 'address_2' => '0']);
        $props = $this->props($this->actingAs($this->owner)->get('/dashboard'), 'dashboard');
        $this->assertSame('MISSING', $props['devices'][0]['serial']);
        $this->assertNull($props['devices'][0]['sku']);
        $this->assertNull($props['devices'][0]['address']);
        $this->assertSame('0', $props['devices'][1]['serial']);
        $this->assertSame('0', $props['devices'][1]['sku']);
        $this->assertSame('0', $props['devices'][1]['address']);
    }

    public function test_dashboard_name_labels_use_trimmed_name_serial_or_device_id_with_safe_json(): void
    {
        $this->device($this->owner, 'NAMED', ['name' => '  Front yard  ']);
        $this->device($this->owner, '  SERIAL-ONLY  ', ['name' => null]);
        $sensitiveSerial = '</script><img src=x onerror=alert(1)>';
        $this->device($this->owner, ' '.$sensitiveSerial.' ', ['name' => " \t "]);
        $unnamed = $this->device($this->owner, '   ', ['name' => '']);
        $this->device($this->owner, '0', ['name' => null]);

        $response = $this->actingAs($this->owner)->get('/dashboard');
        $props = $this->props($response, 'dashboard');
        $this->assertSame([
            'Front yard', 'SERIAL-ONLY', $sensitiveSerial, 'Device '.$unnamed->id, '0',
        ], array_column($props['devices'], 'name'));
        $this->assertSame($sensitiveSerial, $props['devices'][2]['serial']);
        $this->assertNull($props['devices'][3]['serial']);
        $response->assertDontSee($sensitiveSerial, false);
    }

    public function test_developer_payload_requires_server_developer_and_never_serializes_storage_paths(): void
    {
        for ($version = 1; $version <= 3; $version++) {
            FirmwareVersions::create(['version' => $version, 'prefix' => 'SP1', 'description' => 'Firmware '.$version, 'file_path' => 'private-storage-must-not-be-serialized']);
            ConfigVersions::create(['version' => $version, 'prefix' => 'SP1', 'description' => 'Config '.$version, 'file_path' => 'private-storage-must-not-be-serialized']);
        }
        $this->actingAs($this->owner)->get('/developer-workspace')->assertForbidden();
        $response = $this->actingAs($this->admin)->withSession([
            '_old_input' => ['_upload_kind' => 'config', 'description' => 'Retained description', 'prefix' => 'CFG', 'file_path' => 'old-path-must-not-be-serialized'],
            'errors' => (new ViewErrorBag)->put('default', new MessageBag(['config' => ['JSON required.']])),
        ])->get('/developer-workspace?firmware_show=2&config_show=1');
        $props = $this->props($response, 'developer');
        $this->assertSame('config', $props['activeUpload']);
        $this->assertSame('config', $props['activeSection']);
        $this->assertSame(['description' => 'Retained description', 'prefix' => 'CFG'], $props['values']);
        $this->assertSame(['JSON required.'], $props['errors']['config']);
        $this->assertCount(2, $props['firmware']['rows']);
        $this->assertCount(1, $props['config']['rows']);
        $this->assertSame(route('uploadFirmware'), $props['links']['uploadFirmware']);
        $this->assertSame(route('uploadConfig'), $props['links']['uploadConfig']);
        $this->assertSame(route('developer-workspace'), $props['links']['developer']);
        foreach (['firmware', 'config'] as $kind) {
            $this->assertSame(['version', 'prefix', 'description', 'createdAt'], array_keys($props[$kind]['rows'][0]));
            foreach ($props[$kind]['pagination']['links'] as $link) {
                if ($link['url']) {
                    $this->assertStringContainsString('firmware_show=2', $link['url']);
                    $this->assertStringContainsString('config_show=1', $link['url']);
                    $this->assertStringContainsString('section='.$kind, $link['url']);
                }
            }
        }
        $response->assertDontSee('private-storage-must-not-be-serialized')->assertDontSee('old-path-must-not-be-serialized');
    }

    public function test_developer_sections_follow_safe_query_or_upload_feedback_and_work_with_legacy_renderer(): void
    {
        $this->actingAs($this->admin);
        $props = $this->props($this->get('/developer-workspace?section=config'), 'developer');
        $this->assertSame('config', $props['activeSection']);
        $props = $this->props($this->get('/developer-workspace?section=unexpected'), 'developer');
        $this->assertSame('firmware', $props['activeSection']);
        $props = $this->props($this->withSession(['active_upload' => 'config'])->get('/developer-workspace?section=firmware'), 'developer');
        $this->assertSame('config', $props['activeSection']);
        config(['frontend.vue3.developer' => false]);
        $this->get('/developer-workspace')->assertOk()->assertSee('Developer Workspace')
            ->assertDontSee('data-vue-page="developer"', false)
            ->assertSee('action="'.route('developer-workspace').'"', false);
    }

    public function test_modern_pages_preserve_guest_and_unverified_redirects_and_flags_restore_blade(): void
    {
        foreach (['/profile', '/create-device', '/dashboard', '/developer-workspace'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->owner->email_verified_at = null;
        $this->owner->save();
        foreach (['/profile', '/create-device', '/dashboard'] as $url) {
            $this->actingAs($this->owner)->get($url)->assertRedirect('/email/verify');
        }
        $this->owner->email_verified_at = now();
        $this->owner->save();
        config(['frontend.vue3.profile' => false, 'frontend.vue3.dashboard' => false]);
        $this->get('/profile')->assertOk()->assertDontSee('data-vue-page="profile"', false)->assertSee('name="_method" value="PUT"', false);
        $this->get('/dashboard')->assertOk()->assertDontSee('data-vue-page="dashboard"', false)->assertSee('No devices registered yet.');
    }
}
