<?php

namespace Tests\Feature;

use App\Models\Api\DeviceLog;
use App\Models\ApiToken;
use App\Models\Device;
use App\Models\ExternalApiKey;
use App\Models\SolarTrackerRemoteControl;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeviceTelemetryTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $developer;
    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->developer = User::factory()->create(['email' => 'telemetry-developer@example.test']);
        config(['app.developer_email' => $this->developer->email, 'frontend.vue3.view_device' => true]);
        $this->device = Device::factory()->create([
            'user_id' => $this->owner->id, 'serial_no' => 'LIVE-SIMULATOR',
        ]);
    }

    public function test_browser_external_and_initial_page_share_the_latest_snapshot_without_side_effects(): void
    {
        $first = $this->reading('2026-09-25 17:00:00', ['temp' => 20]);
        $latest = $this->reading('2026-09-25 18:00:00', [
            'temp' => 31.5, 'ps1' => 18, 'ps2' => 19, 'ps_avg' => null, 'pds' => -1,
            'cts' => 4, 'state' => 'tracking', 'motor_speed' => -20,
        ]);
        DeviceLog::factory()->create(['serial_no' => 'OTHER-SIMULATOR', 'temp' => 999]);
        $control = SolarTrackerRemoteControl::create([
            'serial_no' => $this->device->serial_no, 'mode' => 1, 'motor_speed' => 30,
        ]);
        $beforeDevice = $this->device->fresh()->getAttributes();
        $beforeControl = $control->fresh()->getAttributes();
        $beforeCount = DeviceLog::count();

        $browser = $this->actingAs($this->owner)->getJson($this->browserUrl())->assertOk();
        $browser->assertJsonPath('status.State', 'tracking')
            ->assertJsonPath('status.PS Average', null)
            ->assertJsonPath('status.PDS', -1)
            ->assertJsonPath('status.Temperature (°C)', 31.5)
            ->assertJsonPath('status.CTS', 4)
            ->assertJsonPath('status.Motor Speed', -20)
            ->assertJsonPath('status.Updated', 'September 25, 2026, 11:00 AM PDT')
            ->assertJsonPath('latest_reading.id', $latest->id)
            ->assertJsonPath('latest_reading.timestamp', '2026-09-25T11:00:00-07:00')
            ->assertJsonPath('latest_reading.epoch_ms', 1790359200000);
        $this->assertSame([$first->id, $latest->id], array_column($browser->json('graph.points'), 'id'));
        $this->assertStringContainsString('no-store', $browser->headers->get('Cache-Control'));
        $this->get('/devices/'.$this->device->id)->assertOk()->assertViewHas('telemetry', $browser->json());

        [, $secret] = ExternalApiKey::issue($this->developer, 'Snapshot test', null);
        $external = $this->bearer($secret)->getJson($this->externalUrl())->assertOk();
        $this->assertSame($browser->json(), $external->json());
        $this->assertSame(['status', 'graph', 'latest_reading'], array_keys($external->json()));
        $external->assertDontSee('user_id');
        $this->assertSame($beforeDevice, $this->device->fresh()->getAttributes());
        $this->assertSame($beforeControl, $control->fresh()->getAttributes());
        $this->assertSame($beforeCount, DeviceLog::count());
        $this->bearer($secret)->getJson('/api/external/v1')->assertJsonFragment([
            'GET /api/external/v1/devices/{id}/telemetry',
        ]);
    }

    public function test_later_requests_include_new_readings_duplicates_and_corrections(): void
    {
        $first = $this->reading('2026-09-25 18:00:00', ['temp' => 20]);
        $this->actingAs($this->owner)->getJson($this->browserUrl())->assertOk()
            ->assertJsonPath('latest_reading.id', $first->id)->assertJsonCount(1, 'graph.points');
        $second = $this->reading('2026-09-25 18:00:00', ['temp' => 21]);
        $response = $this->getJson($this->browserUrl())->assertOk()
            ->assertJsonPath('latest_reading.id', $second->id)->assertJsonPath('status.Temperature (°C)', 21);
        $this->assertSame([$first->id, $second->id], array_column($response->json('graph.points'), 'id'));
        $this->assertSame($response->json('graph.points.0.epoch_ms'), $response->json('graph.points.1.epoch_ms'));
        $second->timestamps = false;
        $second->temp = null;
        $second->pds = 8;
        $second->save();
        $this->getJson($this->browserUrl())->assertOk()
            ->assertJsonPath('status.Temperature (°C)', null)->assertJsonPath('status.PDS', 8)
            ->assertJsonPath('graph.points.1.temp', null)->assertJsonPath('graph.points.1.pds', 8);
        $newest = $this->reading('2026-09-25 18:01:00', ['temp' => 22]);
        $this->getJson($this->browserUrl())->assertOk()
            ->assertJsonPath('latest_reading.id', $newest->id)->assertJsonPath('status.Temperature (°C)', 22)
            ->assertJsonCount(3, 'graph.points');
    }

    public function test_empty_and_missing_measurements_remain_distinct_from_zero(): void
    {
        $empty = $this->actingAs($this->owner)->getJson($this->browserUrl())->assertOk()
            ->assertJsonPath('latest_reading', null)->assertJsonPath('graph.points', [])
            ->assertJsonPath('status.Updated', 'N/A');
        foreach ($empty->json('status') as $field => $value) {
            if ($field !== 'Updated') {
                $this->assertNull($value);
            }
        }
        $this->reading('2026-09-25 18:00:00', [
            'temp' => null, 'state' => null, 'cts' => null, 'ps_avg' => null, 'pds' => 0,
        ]);
        $this->getJson($this->browserUrl())->assertOk()
            ->assertJsonPath('status.Temperature (°C)', null)->assertJsonPath('status.State', null)
            ->assertJsonPath('status.CTS', null)->assertJsonPath('status.PS Average', null)
            ->assertJsonPath('status.PDS', 0);
        $this->assertDatabaseCount('solar_tracker_remote_controls', 0);
    }

    public function test_snapshot_returns_the_latest_9000_in_chronological_order(): void
    {
        $start = CarbonImmutable::parse('2026-09-01T00:00:00Z');
        $rows = [];
        for ($index = 0; $index < 9001; $index++) {
            $timestamp = $start->addSeconds($index)->format('Y-m-d H:i:s');
            $rows[] = ['serial_no' => $this->device->serial_no, 'data' => json_encode(['temp' => $index]),
                'created_at' => $timestamp, 'updated_at' => $timestamp];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('device_logs')->insert($chunk);
        }
        $response = $this->actingAs($this->owner)->getJson($this->browserUrl())->assertOk()
            ->assertJsonCount(9000, 'graph.points')->assertJsonPath('graph.points.0.temp', 1)
            ->assertJsonPath('graph.points.8999.temp', 9000)->assertJsonPath('status.Temperature (°C)', 9000);
        $this->assertSame($response->json('graph.points.8999.id'), $response->json('latest_reading.id'));
    }

    public function test_browser_refresh_requires_verified_ownership_or_developer_access(): void
    {
        $this->getJson($this->browserUrl())->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson($this->browserUrl())->assertForbidden();
        $this->owner->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($this->owner)->getJson($this->browserUrl())->assertForbidden();
        $this->actingAs($this->developer)->getJson($this->browserUrl())->assertOk();
        config(['app.developer_email' => '']);
        $this->getJson($this->browserUrl())->assertForbidden();
    }

    public function test_external_refresh_preserves_credential_separation_and_key_validation(): void
    {
        [$key, $secret] = ExternalApiKey::issue($this->developer, 'Snapshot test', null);
        $this->actingAs($this->developer)->getJson($this->externalUrl())->assertUnauthorized();
        $this->bearer(ApiToken::issue($this->developer))->getJson($this->externalUrl())->assertUnauthorized();
        $this->bearer($secret)->getJson($this->browserUrl())->assertUnauthorized();
        $this->bearer($secret.'x')->getJson($this->externalUrl())->assertUnauthorized();
        $key->forceFill(['expires_at' => now()->subSecond()])->save();
        $this->bearer($secret)->getJson($this->externalUrl())->assertUnauthorized();
        $key->forceFill(['expires_at' => null, 'revoked_at' => now()])->save();
        $this->bearer($secret)->getJson($this->externalUrl())->assertUnauthorized();
        $key->forceFill(['revoked_at' => null])->save();
        config(['app.developer_email' => '']);
        $this->bearer($secret)->getJson($this->externalUrl())->assertUnauthorized();
        config(['app.developer_email' => $this->developer->email]);
        $this->developer->forceFill(['email_verified_at' => null])->save();
        $this->bearer($secret)->getJson($this->externalUrl())->assertUnauthorized();
        [, $foreign] = ExternalApiKey::issue($this->owner, 'Wrong owner', null);
        $this->bearer($foreign)->getJson($this->externalUrl())->assertUnauthorized();
    }

    public function test_both_routes_return_not_found_for_missing_devices(): void
    {
        $this->actingAs($this->owner)->getJson('/devices/999999/telemetry')->assertNotFound();
        [, $secret] = ExternalApiKey::issue($this->developer, 'Snapshot test', null);
        $this->bearer($secret)->getJson('/api/external/v1/devices/999999/telemetry')->assertNotFound();
    }

    private function reading(string $timestamp, array $values = []): DeviceLog
    {
        return DeviceLog::factory()->create(array_merge([
            'serial_no' => $this->device->serial_no, 'updated_at' => $timestamp, 'created_at' => $timestamp,
        ], $values));
    }

    private function browserUrl(): string
    {
        return '/devices/'.$this->device->id.'/telemetry';
    }

    private function externalUrl(): string
    {
        return '/api/external/v1/devices/'.$this->device->id.'/telemetry';
    }

    private function bearer(string $token): self
    {
        Auth::forgetGuards();
        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
