<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\SolarTrackerRemoteControl;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SolarTrackerRemoteControlTest extends TestCase
{
    use RefreshDatabase;

    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::create([
            'name' => 'Owner', 'email' => 'owner@example.test',
            'password' => 'test-hash', 'email_verified_at' => now(),
        ]);
        $this->device = Device::create([
            'serial_no' => 'remote-test', 'user_id' => $user->id,
        ]);
        $this->actingAs($user);
    }

    public function test_missing_control_defaults_to_automatic_without_writing_a_command(): void
    {
        $this->get('/device-info/'.$this->device->id)
            ->assertOk()
            ->assertViewHas('remoteControl', ['mode' => 0, 'motor_speed' => 0])
            ->assertSee('Remote control mode');
        $this->assertSame(0, SolarTrackerRemoteControl::count());
    }

    public function test_toggle_modes_are_persisted_as_zero_and_one_and_loaded_on_page_refresh(): void
    {
        $this->postJson('/update-solar-tracker', [
            'serial_no' => $this->device->serial_no, 'mode' => 1, 'motor_speed' => 0,
        ])->assertOk()->assertJsonPath('success', true)->assertJsonPath('mode', 1);
        $this->assertDatabaseHas('solar_tracker_remote_controls', [
            'serial_no' => $this->device->serial_no, 'mode' => 1, 'motor_speed' => 0,
        ]);
        $this->get('/device-info/'.$this->device->id)->assertOk()
            ->assertViewHas('remoteControl', fn ($control) => $control['mode'] === 1);
        $this->get('/api/remote-control/'.$this->device->serial_no)
            ->assertOk()->assertJsonPath('mode', 1)->assertJsonPath('motor_speed', 0);

        $this->postJson('/update-solar-tracker', [
            'serial_no' => $this->device->serial_no, 'mode' => 0, 'motor_speed' => 20,
        ])->assertOk()->assertJsonPath('mode', 0)->assertJsonPath('motor_speed', 0);
        $this->assertSame(1, SolarTrackerRemoteControl::count());
        $this->assertDatabaseHas('solar_tracker_remote_controls', [
            'serial_no' => $this->device->serial_no, 'mode' => 0, 'motor_speed' => 0,
        ]);
        $this->get('/device-info/'.$this->device->id)->assertOk()
            ->assertViewHas('remoteControl', fn ($control) => $control['mode'] === 0);
        $this->get('/api/remote-control/'.$this->device->serial_no)
            ->assertOk()->assertJsonPath('mode', 0)->assertJsonPath('motor_speed', 0);
    }

    public function test_manual_speed_range_and_stop_persist_and_reach_firmware_in_remote_mode(): void
    {
        foreach (range(-100, 100, 10) as $speed) {
            $this->postJson('/update-solar-tracker', [
                'serial_no' => $this->device->serial_no, 'mode' => 1, 'motor_speed' => $speed,
            ])->assertOk()->assertJsonPath('mode', 1)->assertJsonPath('motor_speed', $speed);
            $this->assertDatabaseHas('solar_tracker_remote_controls', [
                'serial_no' => $this->device->serial_no, 'mode' => 1, 'motor_speed' => $speed,
            ]);
            $this->get('/api/remote-control/'.$this->device->serial_no)
                ->assertOk()->assertJsonPath('mode', 1)->assertJsonPath('motor_speed', $speed);
            $this->get('/device-info/'.$this->device->id)->assertOk()
                ->assertViewHas('remoteControl', fn ($control) => $control['mode'] === 1 && $control['motor_speed'] === (float) $speed);
        }
        $this->assertSame(1, SolarTrackerRemoteControl::count());
    }

    public function test_out_of_bounds_or_nonnumeric_speed_never_overwrites_the_saved_command(): void
    {
        SolarTrackerRemoteControl::create([
            'serial_no' => $this->device->serial_no, 'mode' => 1, 'motor_speed' => 30,
        ]);
        Sanctum::actingAs(User::findOrFail($this->device->user_id));
        foreach (['/update-solar-tracker', '/api/update-solar-tracker', '/api/remote-control-set'] as $url) {
            foreach ([-101, 101, 'not-a-speed'] as $speed) {
                $this->postJson($url, [
                    'serial_no' => $this->device->serial_no, 'mode' => 1, 'motor_speed' => $speed,
                ])->assertStatus(422)->assertJsonValidationErrors('motor_speed');
            }
        }
        $this->assertDatabaseCount('solar_tracker_remote_controls', 1);
        $this->assertDatabaseHas('solar_tracker_remote_controls', [
            'serial_no' => $this->device->serial_no, 'mode' => 1, 'motor_speed' => 30,
        ]);
        $this->get('/api/remote-control/'.$this->device->serial_no)
            ->assertOk()->assertJsonPath('mode', 1)->assertJsonPath('motor_speed', 30);
    }

    public function test_existing_api_clients_can_still_use_speeds_outside_the_ui_step_of_ten(): void
    {
        Sanctum::actingAs(User::findOrFail($this->device->user_id));
        foreach (['/api/update-solar-tracker', '/api/remote-control-set'] as $url) {
            foreach ([-37, 23] as $speed) {
                $this->postJson($url, [
                    'serial_no' => $this->device->serial_no, 'mode' => 1, 'motor_speed' => $speed,
                ])->assertOk()->assertJsonPath('success', true);
                $this->assertDatabaseHas('solar_tracker_remote_controls', [
                    'serial_no' => $this->device->serial_no, 'mode' => 1, 'motor_speed' => $speed,
                ]);
                $this->get('/api/remote-control/'.$this->device->serial_no)
                    ->assertOk()->assertJsonPath('mode', 1)->assertJsonPath('motor_speed', $speed);
            }
        }
        $this->assertDatabaseCount('solar_tracker_remote_controls', 1);
    }

    public function test_invalid_mode_does_not_write_a_control_row(): void
    {
        $this->postJson('/update-solar-tracker', [
            'serial_no' => $this->device->serial_no, 'mode' => 2, 'motor_speed' => 0,
        ])->assertStatus(422)->assertJsonValidationErrors('mode');
        $this->assertSame(0, SolarTrackerRemoteControl::count());
    }
}
