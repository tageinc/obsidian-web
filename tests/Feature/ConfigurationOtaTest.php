<?php

namespace Tests\Feature;

use App\Models\ConfigVersions;
use App\Models\ExternalApiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ConfigurationOtaTest extends TestCase
{
    use RefreshDatabase;

    private User $developer;
    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();
        $this->developer = User::factory()->create(['email' => 'ota@example.test']);
        config(['app.developer_email' => $this->developer->email]);
        [, $this->secret] = ExternalApiKey::issue($this->developer, 'OTA test', null);
        Storage::fake('local');
    }

    private function payload(string $version = '1', ?string $bytes = null): array
    {
        return [
            'device_family' => 'smart-panels-esp32', 'version' => $version,
            'prefix' => 'TEST', 'description' => 'Synthetic configuration',
            'config' => UploadedFile::fake()->createWithContent('config.json', $bytes ?? file_get_contents(base_path('tests/Fixtures/tracker-configuration.json'))),
        ];
    }

    public function test_browser_and_external_publication_reject_incompatible_incomplete_and_unsafe_candidates_without_files(): void
    {
        $valid = json_decode(file_get_contents(base_path('tests/Fixtures/tracker-configuration.json')), true);
        $invalid = ['{', 'null', '[]', '{}'];
        foreach ($valid as $key => $value) {
            $missing = $valid;
            unset($missing[$key]);
            $invalid[] = json_encode($missing);
            foreach ([null, true, (string) $value, [], 1e100] as $bad) {
                $invalid[] = json_encode(array_replace($valid, [$key => $bad]));
            }
        }
        foreach (['lid_N' => 1.5, 'hm_N' => 0, 'lid_th' => -1, 'kp' => -10.1, 'nrml_w' => 101,
            'log_T' => 0, 'update_T' => 4, 'config_portal_T' => 1.5, 'optimize_T' => 0.0001,
            'cool_off_T' => -1, 'remote_control_T' => 0] as $key => $value) {
            $invalid[] = json_encode(array_replace($valid, [$key => $value]));
        }
        $invalid[] = json_encode($valid + ['max_temp' => 999]);
        $invalid[] = str_replace('{', '{"lid_N":10,', json_encode($valid));
        $invalid[] = str_replace('{', '{"lid_\\u004e":10,', json_encode($valid));
        $invalid[] = str_repeat(' ', 4097).json_encode($valid);
        foreach ([false, true] as $external) {
            Auth::forgetGuards();
            $this->withHeader('Authorization', $external ? 'Bearer '.$this->secret : '');
            if (!$external) {
                $this->actingAs($this->developer);
            }
            $url = $external ? '/api/external/v1/configuration' : '/upload-config';
            foreach ($invalid as $bytes) {
                $this->postJson($url, $this->payload('1', $bytes))->assertUnprocessable()->assertJsonValidationErrors('config');
            }
            foreach ([null, 'other-family'] as $family) {
                $this->postJson($url, array_replace($this->payload(), ['device_family' => $family]))
                    ->assertUnprocessable()->assertJsonValidationErrors('device_family');
            }
            $this->postJson($url, array_replace($this->payload(), ['prefix' => 'unsafe/prefix']))
                ->assertUnprocessable()->assertJsonValidationErrors('prefix');
            $this->assertDatabaseCount('config_versions', 0);
            $this->assertSame([], Storage::allFiles());
        }
        $this->postJson('/api/external/v1/configuration', $this->payload())->assertCreated()
            ->assertJsonPath('message', 'Configuration uploaded successfully. Available for polling; application and persistence require device telemetry.');
        $this->getJson('/api/external/v1/configuration')->assertOk()
            ->assertJsonPath('data.0.device_family', 'smart-panels-esp32')->assertJsonPath('data.0.schema_version', 1);
    }

    public function test_discovery_and_download_keep_the_same_release_during_publication_and_reject_edits_or_missing_bytes(): void
    {
        $this->actingAs($this->developer);
        $first = $this->postJson('/upload-config', $this->payload())->assertCreated();
        $record = ConfigVersions::findOrFail($first->json('id'));
        $discovery = $this->getJson('/api/config-version/TEST?protocol=2')->assertOk();
        $identity = $discovery->json();
        $this->assertSame('1', $identity['version']);
        $this->assertSame(hash('sha256', Storage::get($record->file_path)), $identity['sha256']);
        $this->getJson('/api/config-version/TEST?protocol=2')->assertExactJson($identity);
        $this->postJson('/upload-config', $this->payload('2'))->assertCreated();
        $this->getJson('/api/config-version/TEST?protocol=2')->assertJsonPath('version', '2');
        $download = $this->getJson('/'.$identity['download_path'])->assertOk()
            ->assertJsonPath('release_id', $identity['release_id'])->assertJsonPath('version', '1')
            ->assertJsonPath('schema_version', 1)->assertJsonPath('prefix', 'TEST')->assertDontSee('file_path');
        $this->assertSame(json_decode(Storage::get($record->file_path), true), $download->json('config'));
        if ($directory = getenv('CONFIG_OTA_CONTRACT_OUTPUT')) {
            $this->assertDirectoryExists($directory);
            file_put_contents($directory.DIRECTORY_SEPARATOR.'discovery.json', json_encode($identity, JSON_THROW_ON_ERROR));
            file_put_contents($directory.DIRECTORY_SEPARATOR.'envelope.json', $download->getContent());
        }
        $this->getJson(str_replace('prefix=TEST', 'prefix=OTHER', '/'.$identity['download_path']))
            ->assertStatus(409)->assertJsonPath('error', 'identity_mismatch');
        $this->patchJson('/developer-workspace/config/'.$record->id, ['version' => '3'])->assertOk();
        $this->getJson('/'.$identity['download_path'])->assertStatus(409)->assertJsonPath('error', 'identity_mismatch');
        $edited = $this->getJson('/api/config-version/TEST?protocol=2')->assertOk()->json();
        $this->assertNotSame($identity['release_id'], $edited['release_id']);
        Storage::put($record->file_path, str_replace('"log_T":30', '"log_T":45', Storage::get($record->file_path)));
        $this->getJson('/'.$edited['download_path'])->assertStatus(409)->assertJsonPath('error', 'identity_mismatch');
        Storage::delete($record->file_path);
        $this->getJson('/api/config-version/TEST?protocol=2')->assertNotFound()->assertJsonPath('error', 'missing_file');
        $this->getJson('/'.$edited['download_path'])->assertNotFound()->assertJsonPath('error', 'missing_file');
        $record->delete();
        $this->getJson('/'.$edited['download_path'])->assertNotFound()->assertJsonPath('error', 'missing_record');
        $this->getJson('/api/config-version/MISSING?protocol=2')->assertNotFound()->assertJsonPath('error', 'no_matching_version');
    }

    public function test_legacy_latest_release_requires_validated_republication_without_silently_selecting_older_release(): void
    {
        $this->actingAs($this->developer)->postJson('/upload-config', $this->payload())->assertCreated();
        ConfigVersions::create(['prefix' => 'TEST', 'version' => '2', 'file_path' => 'legacy.json']);
        Storage::put('legacy.json', '{}');
        $this->getJson('/api/config-version/TEST?protocol=2')->assertStatus(409)
            ->assertJsonPath('error', 'unsupported_configuration_schema');
        $this->getJson('/api/config-version/TEST')->assertOk()->assertJsonPath('version', '2');
        $this->get('/api/config-file/prefix/TEST')->assertDownload('legacy.json');
    }
    public function test_firmware_contract_acknowledgement_roundtrip(): void
    {
        $path = getenv('CONFIG_OTA_CONTRACT_ACK');
        $identity = ['release_id' => str_repeat('c', 64), 'prefix' => 'TEST', 'version' => '1', 'schema_version' => 1];
        $payload = $path ? json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR) : [
            'temp' => 20, 'firmware_version' => 'test-binary', 'config_version' => '1', 'prefix' => 'TEST',
            'config_ota' => ['downloaded' => $identity, 'applied' => $identity, 'persisted' => $identity, 'status' => 'restored', 'error' => null],
        ];
        $payload = $payload['data'] ?? $payload;
        $device = \App\Models\Device::factory()->create(['serial_no' => 'OTA-CONTRACT', 'user_id' => $this->developer->id]);
        $this->postJson('/api/log', ['serial_no' => $device->serial_no, 'data' => json_encode($payload, JSON_THROW_ON_ERROR)])->assertOk();
        $snapshot = $this->actingAs($this->developer)->getJson('/devices/'.$device->id.'/telemetry')->assertOk();
        $this->assertSame($payload['config_ota']['applied'], $snapshot->json('status.Configuration OTA.applied'));
        $this->assertSame($payload['config_ota']['persisted'], $snapshot->json('status.Configuration OTA.persisted'));
        $this->assertSame($payload['config_version'], $snapshot->json('status.Config version'));
        $this->assertSame($payload['firmware_version'], $snapshot->json('status.Firmware version'));
    }

    public function test_file_read_races_and_storage_outages_have_sanitized_outcomes(): void
    {
        $record = ConfigVersions::create([
            'prefix' => 'TEST', 'version' => '1', 'file_path' => 'private/fixture.json',
            'device_family' => 'smart-panels-esp32', 'schema_version' => 1,
        ]);
        Storage::shouldReceive('exists')->with($record->file_path)->andReturn(true);
        Storage::shouldReceive('get')->with($record->file_path)->once()->andReturn(null);
        $this->getJson('/api/config-version/TEST?protocol=2')->assertNotFound()->assertJsonPath('error', 'missing_file');
        Storage::shouldReceive('get')->with($record->file_path)->once()->andThrow(new \RuntimeException('private-storage-secret'));
        $this->getJson('/api/config-version/TEST?protocol=2')->assertStatus(503)
            ->assertJsonPath('error', 'storage_unavailable')->assertDontSee('private-storage-secret')->assertDontSee('fixture.json');
    }

    public function test_signed_settings_and_stop_values_used_by_existing_device_configurations_are_supported(): void
    {
        $valid = json_decode(file_get_contents(base_path('tests/Fixtures/tracker-configuration.json')), true);
        $this->actingAs($this->developer);
        foreach ([[-10, -100], [0, 0], [10, 100], [-0.1, 50]] as $index => [$gain, $command]) {
            $this->postJson('/upload-config', $this->payload((string) ($index + 1), json_encode(array_replace($valid, [
                'kp' => $gain, 'nrml_w' => $command,
            ]))))->assertCreated();
        }
    }

}
