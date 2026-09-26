<?php

namespace Tests\Feature;

use App\Models\Api\DeviceLog;
use App\Models\ApiToken;
use App\Models\Device;
use App\Models\ExternalApiKey;
use App\Models\SolarTrackerRemoteControl;
use App\Models\User;
use App\Services\DeviceHistoryReportData;
use App\Services\DeviceHistoryReportPdf;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeviceHistoryReportTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $developer;
    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-25T18:00:00Z');
        CarbonImmutable::setTestNow('2026-09-25T18:00:00Z');
        $this->owner = User::factory()->create();
        $this->developer = User::factory()->create(['email' => 'reports-developer@example.test']);
        config(['app.developer_email' => $this->developer->email]);
        $this->device = Device::factory()->create([
            'user_id' => $this->owner->id, 'serial_no' => 'REPORT-SIMULATOR',
            'name' => 'Report simulator', 'sku' => 'SP1', 'order_no' => 'REPORT-ORDER',
            'address_1' => '100 Test Street', 'address_2' => null,
            'city' => 'Sample City', 'address_state' => 'CA', 'zip_code' => '90000',
            'country' => 'US', 'latitude' => 34.125, 'longitude' => -118.125,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_browser_and_external_downloads_share_the_same_authoritative_report(): void
    {
        $renderer = $this->captureReports();
        $first = $this->reading('2026-09-24 18:00:00', ['pds' => -2, 'ps_avg' => 91]);
        $second = $this->reading('2026-09-24 19:00:00', ['temp' => null, 'ps1' => null, 'ps_avg' => null]);
        $latest = $this->reading('2026-09-25 18:00:00', ['temp' => 32.5, 'state' => 'tracking', 'cts' => 4]);
        $this->reading('2026-09-24 17:59:59');
        DeviceLog::factory()->create(['serial_no' => 'ANOTHER-SIMULATOR', 'temp' => 999]);
        $query = '?'.http_build_query([
            'from' => '2026-09-24T11:00:00-07:00', 'to' => '2026-09-24T19:00:00.000Z',
        ]);
        $beforeDevice = $this->device->fresh()->getAttributes();
        $beforeReadings = DeviceLog::count();
        $response = $this->actingAs($this->owner)->get($this->browserUrl().$query)->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition', 'attachment; filename="device-history-REPORT-SIMULATOR-20260925-180000.pdf"');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        [, $secret] = ExternalApiKey::issue($this->developer, 'Report fixture', null);
        $external = $this->bearer($secret)->get($this->externalUrl().$query)->assertOk();
        $external->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame($response->getContent(), $external->getContent());
        $this->assertEquals($renderer->reports[0], $renderer->reports[1]);
        $report = $renderer->reports[0];
        $this->assertSame([$first->id, $second->id], array_column($report['points'], 'id'));
        $this->assertSame(-2.0, $report['points'][0]['pds']);
        $this->assertSame(91.0, $report['points'][0]['ps_avg']);
        $this->assertNull($report['points'][1]['temp']);
        $this->assertNull($report['points'][1]['temp_f']);
        $this->assertNull($report['points'][1]['ps_avg']);
        $this->assertSame('REPORT-ORDER', $report['device']['order_no']);
        $this->assertSame('100 Test Street', $report['device']['address_1']);
        $this->assertArrayNotHasKey('user_id', $report['device']);
        $this->assertArrayNotHasKey('data', $report['latest']);
        $this->assertSame(32.5, $report['latest']['temp']);
        $this->assertSame(90.5, $report['latest']['temp_f']);
        $this->assertSame('tracking', $report['latest']['state']);
        $this->assertSame(4, $report['latest']['cts']);
        $this->assertNull($report['latest']['cts_state']);
        $this->assertTrue($report['latest']['recorded_at']->equalTo($latest->updated_at));
        $this->assertSame('America/Los_Angeles', $report['timezone']);
        $this->assertSame(0, $report['control_mode']);
        $this->assertTrue($report['generated_at']->equalTo(CarbonImmutable::now()));
        $this->assertSame($beforeDevice, $this->device->fresh()->getAttributes());
        $this->assertSame($beforeReadings, DeviceLog::count());
        $this->assertSame(0, SolarTrackerRemoteControl::count());
        $this->bearer($secret)->getJson('/api/external/v1')->assertJsonFragment([
            'GET /api/external/v1/devices/{id}/report',
        ]);
    }

    public function test_default_period_is_the_last_24_elapsed_hours_ending_now_even_across_dst(): void
    {
        CarbonImmutable::setTestNow('2026-11-01T12:15:30.876Z');
        $renderer = $this->captureReports();
        $this->actingAs($this->owner)->get($this->browserUrl())->assertOk();
        $range = $renderer->reports[0]['range'];
        $this->assertSame('2026-10-31T12:15:30+00:00', $range['from']->toIso8601String());
        $this->assertSame('2026-11-01T12:15:30+00:00', $range['to']->toIso8601String());
        $this->assertEquals(86400000, $range['end_ms'] - $range['start_ms']);
    }

    public function test_equal_range_boundaries_include_distinct_readings_at_the_same_instant(): void
    {
        $renderer = $this->captureReports();
        $first = $this->reading('2026-09-25 18:00:00', ['pds' => 0]);
        $second = $this->reading('2026-09-25 18:00:00', ['pds' => 2]);
        $query = '?'.http_build_query(['from' => '2026-09-25T18:00:00Z', 'to' => '2026-09-25T18:00:00Z']);
        $this->actingAs($this->owner)->get($this->browserUrl().$query)->assertOk();
        $this->assertSame([$first->id, $second->id], array_column($renderer->reports[0]['points'], 'id'));
    }

    public function test_report_reads_current_control_mode_without_changing_the_stored_command(): void
    {
        $control = SolarTrackerRemoteControl::create([
            'serial_no' => $this->device->serial_no, 'mode' => 1, 'motor_speed' => 20,
        ]);
        $before = $control->fresh()->getAttributes();
        $renderer = $this->captureReports();
        $this->actingAs($this->owner)->get($this->browserUrl())->assertOk();
        $this->assertSame(1, $renderer->reports[0]['control_mode']);
        $this->assertSame($before, $control->fresh()->getAttributes());
        $this->assertDatabaseCount('solar_tracker_remote_controls', 1);
    }

    public function test_empty_ranges_still_include_the_latest_snapshot_and_missing_values_stay_missing(): void
    {
        $renderer = $this->captureReports();
        $this->actingAs($this->owner)->get($this->browserUrl())->assertOk();
        $this->assertSame([], $renderer->reports[0]['points']);
        $this->assertNull($renderer->reports[0]['latest']);
        $this->reading('2026-09-20 18:00:00', ['temp' => null, 'cts' => null, 'state' => null, 'ps_avg' => null]);
        $this->get($this->browserUrl())->assertOk();
        $this->assertSame([], $renderer->reports[1]['points']);
        $this->assertNull($renderer->reports[1]['latest']['temp']);
        $this->assertNull($renderer->reports[1]['latest']['temp_f']);
        $this->assertNull($renderer->reports[1]['latest']['cts']);
        $this->assertNull($renderer->reports[1]['latest']['cts_state']);
        $this->assertNull($renderer->reports[1]['latest']['state']);
        $this->assertNull($renderer->reports[1]['latest']['ps_avg']);
    }

    public function test_report_filtering_uses_the_same_latest_9000_readings_as_history(): void
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
        $data = app(DeviceHistoryReportData::class)->forDevice($this->device, $start, $start->addDay());
        $this->assertCount(9000, $data['points']);
        $this->assertSame(1.0, $data['points'][0]['temp']);
        $this->assertSame(9000.0, $data['points'][8999]['temp']);
        $this->assertSame(9000, $data['available_count']);
        $this->assertSame(9000, $data['reading_limit']);
        $older = app(DeviceHistoryReportData::class)->forDevice($this->device, $start, $start);
        $this->assertSame([], $older['points']);
        $this->assertSame(9000, $older['latest']['temp']);
    }

    public function test_browser_download_requires_a_verified_owner_or_developer(): void
    {
        $renderer = $this->captureReports();
        $this->getJson($this->browserUrl())->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson($this->browserUrl())->assertForbidden();
        $this->owner->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($this->owner)->getJson($this->browserUrl())->assertForbidden();
        $this->actingAs($this->developer)->get($this->browserUrl())->assertOk();
        config(['app.developer_email' => '']);
        $this->getJson($this->browserUrl())->assertForbidden();
        $this->assertCount(1, $renderer->reports);
    }

    public function test_missing_devices_return_not_found_for_both_routes(): void
    {
        $this->actingAs($this->owner)->getJson('/devices/999999/report')->assertNotFound();
        [, $secret] = ExternalApiKey::issue($this->developer, 'Report fixture', null);
        $this->bearer($secret)->getJson('/api/external/v1/devices/999999/report')->assertNotFound();
    }

    public function test_external_report_rejects_other_credential_types_and_inactive_keys(): void
    {
        [$key, $secret] = ExternalApiKey::issue($this->developer, 'Report fixture', null);
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

    public function test_both_routes_reject_incomplete_ambiguous_invalid_and_reversed_ranges(): void
    {
        $renderer = $this->captureReports();
        [, $secret] = ExternalApiKey::issue($this->developer, 'Report fixture', null);
        $valid = ['from' => '2026-09-24T18:00:00Z', 'to' => '2026-09-25T18:00:00Z'];
        $invalid = [
            [['from' => $valid['from']], 'to'],
            [['to' => $valid['to']], 'from'],
            [['from' => '', 'to' => ''], 'from'],
            [array_merge($valid, ['from' => '2026-09-24T18:00:00']), 'from'],
            [array_merge($valid, ['from' => '2026-02-30T18:00:00Z']), 'from'],
            [array_merge($valid, ['from' => '2026-09-24T18:00:00.000001Z']), 'from'],
            [array_merge($valid, ['from' => 'yesterday']), 'from'],
            [array_merge($valid, ['from' => ['invalid']]), 'from'],
            [array_merge($valid, ['from' => '2026-09-26T18:00:00Z']), 'to'],
        ];
        foreach ($invalid as [$params, $field]) {
            $query = '?'.http_build_query($params);
            $this->withHeader('Authorization', '')->actingAs($this->owner, 'web')
                ->getJson($this->browserUrl().$query)->assertUnprocessable()->assertJsonValidationErrors($field);
            $this->bearer($secret)->getJson($this->externalUrl().$query)
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertSame([], $renderer->reports);
    }

    public function test_endpoint_returns_a_real_pdf_attachment(): void
    {
        $this->reading('2026-09-25 17:00:00', ['temp' => 31, 'ps1' => 35, 'ps2' => 37, 'pds' => -2, 'ps_avg' => 36]);
        $this->reading('2026-09-25 18:00:00', ['temp' => 32, 'ps1' => 37, 'ps2' => 39, 'pds' => -2, 'ps_avg' => 38]);
        $response = $this->actingAs($this->owner)->get($this->browserUrl())->assertOk();
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('%%EOF', $response->getContent());
        $this->assertGreaterThan(5000, strlen($response->getContent()));
    }

    private function reading(string $timestamp, array $values = []): DeviceLog
    {
        return DeviceLog::factory()->create(array_merge([
            'serial_no' => $this->device->serial_no, 'updated_at' => $timestamp, 'created_at' => $timestamp,
        ], $values));
    }

    private function captureReports(): object
    {
        $renderer = new class {
            public array $reports = [];

            public function render(array $data): string
            {
                $this->reports[] = $data;
                return '%PDF-1.4 report fixture';
            }
        };
        $this->app->instance(DeviceHistoryReportPdf::class, $renderer);
        return $renderer;
    }

    private function browserUrl(): string
    {
        return '/devices/'.$this->device->id.'/report';
    }

    private function externalUrl(): string
    {
        return '/api/external/v1/devices/'.$this->device->id.'/report';
    }

    private function bearer(string $token): self
    {
        Auth::forgetGuards();
        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
