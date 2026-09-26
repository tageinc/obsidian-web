<?php

namespace Tests\Feature;

use App\Models\Api\DeviceLog;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceLegacySensorDisplayTest extends TestCase
{
    use RefreshDatabase;

    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();
        config(['frontend.vue3.view_device' => false]);
        $owner = User::factory()->create();
        $this->device = Device::factory()->create(['user_id' => $owner->id]);
        $this->actingAs($owner);
    }

    public function test_legacy_status_displays_fahrenheit_and_open_without_changing_source_values(): void
    {
        $reading = DeviceLog::factory()->create([
            'serial_no' => $this->device->serial_no, 'temp' => 26.1135, 'cts' => 0,
        ]);
        $before = $reading->fresh()->getAttributes();

        $this->get('/devices/'.$this->device->id)->assertOk()
            ->assertSee('79.0043 °F')
            ->assertSee('class="cts-badge cts-badge--open">Open</span>', false)
            ->assertDontSee('°C')
            ->assertViewHas('legacyReadings', ['temperature' => 79.0043, 'ctsState' => 'Open', 'cts' => 0]);

        $this->assertSame($before, $reading->fresh()->getAttributes());
    }

    public function test_legacy_status_displays_zero_celsius_as_32_fahrenheit_and_one_as_closed(): void
    {
        DeviceLog::factory()->create([
            'serial_no' => $this->device->serial_no, 'temp' => 0, 'cts' => '1',
        ]);

        $this->get('/devices/'.$this->device->id)->assertOk()
            ->assertSee('32 °F')
            ->assertSee('class="cts-badge cts-badge--closed">Closed</span>', false)
            ->assertViewHas('legacyReadings', ['temperature' => 32.0, 'ctsState' => 'Closed', 'cts' => '1']);
    }

    public function test_legacy_missing_values_are_not_converted_to_a_false_open_or_32_degrees(): void
    {
        DeviceLog::factory()->create([
            'serial_no' => $this->device->serial_no, 'temp' => null, 'cts' => null,
        ]);

        $this->assertMissingSensorDisplay();
    }

    public function test_legacy_empty_device_does_not_display_a_false_open_or_32_degrees(): void
    {
        $this->assertMissingSensorDisplay();
    }

    public function test_legacy_unknown_cts_stays_raw_and_malformed_temperature_remains_unavailable(): void
    {
        DeviceLog::factory()->create([
            'serial_no' => $this->device->serial_no, 'temp' => 'not-a-number', 'cts' => 2,
        ]);

        $this->get('/devices/'.$this->device->id)->assertOk()
            ->assertSee('— °F')
            ->assertSee('<span data-cts-reading>2</span>', false)
            ->assertDontSee('>Open</span>', false)
            ->assertDontSee('>Closed</span>', false)
            ->assertViewHas('legacyReadings', ['temperature' => null, 'ctsState' => null, 'cts' => 2]);
    }

    private function assertMissingSensorDisplay(): void
    {
        $this->get('/devices/'.$this->device->id)->assertOk()
            ->assertSee('— °F')
            ->assertDontSee('32 °F')
            ->assertDontSee('>Open</span>', false)
            ->assertDontSee('>Closed</span>', false)
            ->assertViewHas('legacyReadings', ['temperature' => null, 'ctsState' => null, 'cts' => null]);
    }
}
