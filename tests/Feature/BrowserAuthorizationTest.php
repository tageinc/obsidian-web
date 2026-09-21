<?php

namespace Tests\Feature;

use App\Models\Api\SolarTrackerLog;
use App\Models\ConfigVersions;
use App\Models\DeviceRegister;
use App\Models\FirmwareVersions;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BrowserAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $admin;
    private User $other;
    private DeviceRegister $device;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.admin_email' => 'admin@example.test']);
        foreach (['owner', 'admin', 'other'] as $role) {
            $this->{$role} = User::create([
                'name' => $role, 'email' => $role.'@example.test',
                'password' => 'test-hash', 'email_verified_at' => now(),
            ]);
        }
        DB::table('hardware')->insert(['id' => 1, 'name' => 'Solar Tracker', 'prefix' => 'SP1']);
        $this->device = DeviceRegister::create([
            'serial_no' => 'browser-owned', 'hardware_id' => 1, 'user_id' => $this->owner->id,
        ]);
        SolarTrackerLog::create(['serial_no' => $this->device->serial_no, 'ps1' => 10]);
    }

    public function test_device_page_graph_and_both_refresh_urls_require_owner_or_admin(): void
    {
        foreach ([$this->owner, $this->admin, $this->other] as $user) {
            $this->actingAs($user);
            $status = $user->is($this->other) ? 403 : 200;
            foreach ([
                '/device-info/'.$this->device->id,
                '/device/'.$this->device->id.'/fetch-graph-data',
                '/device/'.$this->device->id.'/refresh',
            ] as $url) {
                $this->get($url)->assertStatus($status);
            }
            $this->postJson('/device/refresh/'.$this->device->id)->assertStatus($status);
        }
    }

    public function test_remote_mutation_denies_foreign_devices_before_writing(): void
    {
        $payload = ['serial_no' => $this->device->serial_no, 'mode' => 1, 'motor_speed' => 20];
        $this->actingAs($this->other)->postJson('/update-solar-tracker', $payload)->assertForbidden();
        $this->assertDatabaseCount('solar_tracker_remote_controls', 0);
        foreach ([$this->owner, $this->admin] as $user) {
            $this->actingAs($user)->postJson('/update-solar-tracker', $payload)->assertOk();
        }
        $this->assertDatabaseCount('solar_tracker_remote_controls', 1);
    }

    public function test_guest_and_unverified_browser_requests_cannot_read_or_command_a_device(): void
    {
        $this->get('/device-info/'.$this->device->id)->assertRedirect('/login');
        $this->postJson('/update-solar-tracker', ['serial_no' => $this->device->serial_no])->assertUnauthorized();
        $this->owner->email_verified_at = null;
        $this->owner->save();
        $this->actingAs($this->owner)->get('/device-info/'.$this->device->id)->assertRedirect('/email/verify');
        $this->postJson('/update-solar-tracker', ['serial_no' => $this->device->serial_no])->assertForbidden();
        $this->assertDatabaseCount('solar_tracker_remote_controls', 0);
    }

    public function test_missing_devices_and_invalid_remote_payload_keep_existing_responses(): void
    {
        $this->actingAs($this->owner)->get('/device-info/999999')->assertRedirect('/device-manager');
        $this->getJson('/device/999999/fetch-graph-data')->assertNotFound();
        $this->postJson('/update-solar-tracker', ['serial_no' => ['invalid']])
            ->assertUnprocessable()->assertJsonValidationErrors('serial_no');
        $this->postJson('/update-solar-tracker', ['serial_no' => 'missing', 'mode' => 0, 'motor_speed' => 0])
            ->assertStatus(410);
    }

    public function test_admin_page_and_uploads_are_restricted_server_side(): void
    {
        $this->get('/admin-control-center')->assertRedirect('/login');
        $this->actingAs($this->other)->get('/admin-control-center')->assertForbidden();
        foreach (['/upload-firmware', '/upload-config'] as $url) {
            $this->postJson($url)->assertForbidden();
        }
        $this->actingAs($this->admin)->get('/admin-control-center')->assertOk();
        // Validation runs only after the admin boundary permits the request.
        $this->postJson('/upload-firmware')->assertUnprocessable()->assertJsonValidationErrors('firmware');
        $this->postJson('/upload-config')->assertUnprocessable()->assertJsonValidationErrors('config');
        $this->admin->email_verified_at = null;
        $this->admin->save();
        $this->get('/admin-control-center')->assertRedirect('/email/verify');
    }

    public function test_configured_admin_requires_a_nonempty_exact_email(): void
    {
        $this->assertTrue($this->admin->isAdministrator());
        $this->assertFalse($this->other->isAdministrator());
        config(['app.admin_email' => '']);
        $this->assertFalse($this->admin->isAdministrator());
        $this->actingAs($this->admin)->get('/admin-control-center')->assertForbidden();
    }

    public function test_software_read_downloads_remain_available_to_verified_users_and_public_device_api(): void
    {
        Storage::fake('local');
        foreach ([FirmwareVersions::class => 'firmware', ConfigVersions::class => 'config'] as $model => $kind) {
            $path = 'public/'.$kind.'/fixture.bin';
            Storage::put($path, 'synthetic software fixture');
            $model::create(['version' => '1', 'prefix' => 'SP1', 'file_path' => $path, 'description' => 'Fixture']);
            $this->actingAs($this->other);
            foreach (['version/1', 'prefix/SP1'] as $selector) {
                $this->get('/'.$kind.'-file/'.$selector)->assertOk()->assertDownload('fixture.bin');
            }
            $this->assertSame('{"version":"1"}', $this->get('/'.$kind.'-version/SP1')->assertOk()->getContent());
        }
        auth()->logout();
        $this->get('/firmware-file/version/1')->assertRedirect('/login');
        $this->get('/api/firmware-file/version/1')->assertOk()->assertDownload('fixture.bin');
        $this->get('/api/config-version/SP1')->assertOk()->assertJsonPath('version', '1');
    }

    public function test_browser_middleware_does_not_change_external_mobile_or_firmware_access(): void
    {
        Sanctum::actingAs($this->other);
        $this->getJson('/api/device/'.$this->device->id.'/data')->assertOk()->assertJsonStructure(['graph']);
        $this->postJson('/api/update-solar-tracker', [
            'serial_no' => $this->device->serial_no, 'mode' => 0, 'motor_speed' => 0,
        ])->assertOk();
        $this->get('/api/remote-control/'.$this->device->serial_no)->assertOk()->assertJsonPath('mode', 0);
    }
}
