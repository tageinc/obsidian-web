<?php

namespace Tests\Feature;

use App\Models\ConfigVersions;
use App\Models\ExternalApiKey;
use App\Models\FirmwareVersions;
use App\Models\User;
use App\Services\RedisWorkloadStore;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ConfigurationManagementTest extends TestCase
{
    private User $developer;
    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();
        // Real commits exercise the shared storage and cache callbacks in isolation.
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        Carbon::setTestNow(Carbon::parse('2026-09-28T12:00:00Z'));
        $this->developer = User::factory()->create(['email' => 'configuration-developer@example.test']);
        config(['app.developer_email' => $this->developer->email]);
        [, $this->secret] = ExternalApiKey::issue($this->developer, 'Configuration fixture', null);
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function payload(string $version = '1'): array
    {
        return [
            'device_family' => 'smart-panels-esp32', 'version' => $version, 'prefix' => 'CFG', 'description' => 'Synthetic configuration',
            'config' => UploadedFile::fake()->createWithContent('configuration.json', file_get_contents(base_path('tests/Fixtures/tracker-configuration.json'))),
        ];
    }

    private function record(string $version = '1', string $prefix = 'CFG', ?string $path = null): ConfigVersions
    {
        $path = $path ?? 'public/config/fixture-'.$version.'.json';
        Storage::put($path, file_get_contents(base_path('tests/Fixtures/tracker-configuration.json')));

        return ConfigVersions::create([
            'version' => $version, 'prefix' => $prefix, 'description' => 'Configuration fixture', 'file_path' => $path,
        ]);
    }

    private function client(bool $external): string
    {
        Auth::forgetGuards();
        $this->withHeader('Authorization', $external ? 'Bearer '.$this->secret : '');
        if (!$external) {
            $this->actingAs($this->developer);
        }

        return $external ? '/api/external/v1/configuration' : '/developer-workspace/config';
    }

    public function test_browser_and_external_configuration_crud_preserve_selected_version_identity_and_bytes(): void
    {
        foreach ([false, true] as $external) {
            $base = $this->client($external);
            $version = $external ? str_repeat('9', 64) : '9007199254740993';
            $create = $this->postJson($external ? $base : '/upload-config', $this->payload($version))
                ->assertCreated()->assertJsonPath('version', $version)->assertDontSee('file_path');
            $record = ConfigVersions::findOrFail($create->json('id'));
            $path = $record->file_path;
            $createdAt = $record->created_at->toISOString();
            $this->assertMatchesRegularExpression('#^public/config/[a-f0-9-]{36}\.json$#', $path);
            $this->assertSame(file_get_contents(base_path('tests/Fixtures/tracker-configuration.json')), Storage::get($path));
            Carbon::setTestNow(now()->addDay());
            $newVersion = $external ? '2' : '1';
            $this->patchJson($base.'/'.$record->id, ['version' => $newVersion, 'description' => 'Edited configuration'])
                ->assertOk()->assertJsonPath('id', $record->id)->assertJsonPath('version', $newVersion);
            $record->refresh();
            $this->assertSame('CFG', $record->prefix);
            $this->assertSame('Edited configuration', $record->description);
            $this->assertSame($createdAt, $record->created_at->toISOString());
            $this->assertSame($path, $record->file_path);
            $this->get('/api/config-file/version/'.$version)->assertNotFound();
            $this->get('/api/config-file/version/'.$newVersion)->assertDownload(basename($path));
            if ($external) {
                $this->get('/api/external/v1/configuration/'.$newVersion.'/download')->assertDownload(basename($path));
                $this->getJson('/api/external/v1/configuration')->assertOk()->assertJsonPath('data.0.version', $newVersion)->assertDontSee('file_path');
            }
            $this->deleteJson($base.'/'.$record->id)->assertOk()->assertJsonPath('message', 'Configuration deleted successfully.');
            $this->assertDatabaseMissing('config_versions', ['id' => $record->id]);
            Storage::assertMissing($path);
            $this->get('/api/config-file/version/'.$newVersion)->assertNotFound();
            $this->deleteJson($base.'/'.$record->id)->assertNotFound();
        }
        $endpoints = $this->getJson('/api/external/v1')->assertOk()->json('endpoints');
        $this->assertContains('PATCH /api/external/v1/configuration/{configuration}', $endpoints);
        $this->assertContains('DELETE /api/external/v1/configuration/{configuration}', $endpoints);
    }

    public function test_configuration_validates_integer_versions_global_collisions_json_and_readonly_fields(): void
    {
        $record = $this->record('12', 'OTHER');
        foreach ([false, true] as $external) {
            $base = $this->client($external);
            $upload = $external ? $base : '/upload-config';
            foreach ([null, '0', '-1', '1.5', '1e3', '01', ['1'], str_repeat('9', 256), '12'] as $version) {
                $payload = $this->payload();
                $payload['version'] = $version;
                $this->postJson($upload, $payload)->assertUnprocessable()->assertJsonValidationErrors('version');
            }
            foreach ([UploadedFile::fake()->createWithContent('not-json.bin', 'not JSON'), UploadedFile::fake()->create('large.json', 1025, 'application/json')] as $file) {
                $payload = $this->payload();
                $payload['config'] = $file;
                $this->postJson($upload, $payload)->assertUnprocessable()->assertJsonValidationErrors('config');
            }
            foreach (['prefix' => 'CHANGED', 'file_path' => null, 'created_at' => '2000-01-01', 'id' => 999] as $field => $value) {
                $this->patchJson($base.'/'.$record->id, [$field => $value])->assertUnprocessable()->assertJsonValidationErrors($field);
            }
            $this->patchJson($base.'/'.$record->id, ['config' => $this->payload()['config']])->assertUnprocessable()->assertJsonValidationErrors('config');
            $this->patchJson($base.'/'.$record->id, ['version' => '0', 'description' => ''])->assertUnprocessable()->assertJsonValidationErrors(['version', 'description']);
        }
        $base = $this->client(false);
        $second = $this->record('13');
        $this->patchJson($base.'/'.$second->id, ['version' => '12'])->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->patchJson($base.'/'.$second->id, ['version' => '13'])->assertOk();
        $legacy = $this->record('001.20');
        $this->patchJson($base.'/'.$legacy->id, ['description' => 'Legacy description'])->assertOk()->assertJsonPath('version', '001.20');
        $this->assertSame('OTHER', $record->fresh()->prefix);
        $this->assertDatabaseCount('config_versions', 3);
    }

    public function test_configuration_versions_are_independent_of_firmware_and_reused_versions_do_not_overwrite_files(): void
    {
        $base = $this->client(false);
        FirmwareVersions::create(['version' => '1', 'prefix' => 'CFG', 'description' => 'Firmware', 'file_path' => 'public/firmware/fixture.bin']);
        $first = $this->postJson('/upload-config', $this->payload())->assertCreated();
        $record = ConfigVersions::findOrFail($first->json('id'));
        $this->patchJson($base.'/'.$record->id, ['version' => '2'])->assertOk();
        $payload = $this->payload();
        $payload['config'] = UploadedFile::fake()->createWithContent('different.json', str_replace('"log_T":30', '"log_T":45', file_get_contents(base_path('tests/Fixtures/tracker-configuration.json'))));
        $second = $this->postJson('/upload-config', $payload)->assertCreated();
        $this->assertSame(file_get_contents(base_path('tests/Fixtures/tracker-configuration.json')), Storage::get($record->file_path));
        $this->assertSame(str_replace('"log_T":30', '"log_T":45', file_get_contents(base_path('tests/Fixtures/tracker-configuration.json'))), Storage::get(ConfigVersions::findOrFail($second->json('id'))->file_path));
        $this->assertCount(2, Storage::allFiles('public/config'));
    }

    public function test_configuration_mutations_enforce_current_verified_developer_and_missing_ids(): void
    {
        $record = $this->record();
        $url = '/developer-workspace/config/'.$record->id;
        $this->patchJson($url, ['version' => '2'])->assertUnauthorized();
        $this->deleteJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create());
        $this->patchJson($url, ['version' => '2'])->assertForbidden();
        $this->deleteJson($url)->assertForbidden();
        $this->actingAs($this->developer);
        config(['app.developer_email' => '']);
        $this->patchJson($url, ['version' => '2'])->assertForbidden();
        $this->deleteJson($url)->assertForbidden();
        config(['app.developer_email' => $this->developer->email]);
        $this->developer->forceFill(['email_verified_at' => null])->save();
        $this->patchJson($url, ['version' => '2'])->assertForbidden();
        $this->deleteJson($url)->assertForbidden();
        $this->developer->forceFill(['email_verified_at' => now()])->save();
        foreach ([false, true] as $external) {
            $base = $this->client($external);
            $this->patchJson($base.'/999999', ['version' => '2'])->assertNotFound();
            $this->deleteJson($base.'/999999')->assertNotFound();
        }
        config(['app.developer_email' => null]);
        $this->patchJson('/api/external/v1/configuration/'.$record->id, ['version' => '2'])->assertUnauthorized();
        $this->deleteJson('/api/external/v1/configuration/'.$record->id)->assertUnauthorized();
        $this->assertSame('1', $record->fresh()->version);
        Storage::assertExists($record->file_path);
    }

    public function test_configuration_deletion_preserves_shared_legacy_files_and_restores_staged_bytes_on_rollback(): void
    {
        $base = $this->client(false);
        $first = $this->record('1', 'CFG', 'public/config/shared.json');
        $second = $this->record('2', 'OTHER', $first->file_path);
        $this->deleteJson($base.'/'.$first->id)->assertOk();
        Storage::assertExists($second->file_path);
        $firmware = FirmwareVersions::create(['version' => '1', 'prefix' => 'CFG', 'file_path' => $second->file_path]);
        $this->deleteJson($base.'/'.$second->id)->assertOk();
        Storage::assertExists($firmware->file_path);
        $record = $this->record('3');
        ConfigVersions::deleting(function () {
            throw new RuntimeException('Synthetic configuration deletion failure');
        });
        $this->deleteJson($base.'/'.$record->id)->assertStatus(500);
        $this->assertDatabaseHas('config_versions', ['id' => $record->id]);
        $this->assertSame(file_get_contents(base_path('tests/Fixtures/tracker-configuration.json')), Storage::get($record->file_path));
        $this->assertSame([], Storage::allFiles('config-deletions'));
    }

    public function test_configuration_failed_database_persistence_cleans_uploaded_json(): void
    {
        $this->client(false);
        ConfigVersions::creating(function () {
            throw new RuntimeException('Synthetic configuration persistence failure');
        });
        $this->postJson('/upload-config', $this->payload())->assertStatus(500);
        $this->assertDatabaseCount('config_versions', 0);
        $this->assertSame([], Storage::allFiles('public/config'));
    }

    public function test_configuration_numeric_latest_downloads_and_cache_follow_edits_and_deletions(): void
    {
        $cache = Cache::store('array');
        $store = Mockery::mock(RedisWorkloadStore::class);
        $store->shouldReceive('get')->andReturnUsing(fn ($key) => $cache->get($key));
        $store->shouldReceive('put')->andReturnUsing(fn ($key, $value, $ttl) => $cache->put($key, $value, $ttl));
        $store->shouldReceive('forget')->andReturnUsing(fn ($key) => $cache->forget($key));
        $this->app->instance(RedisWorkloadStore::class, $store);
        config(['redis-workloads.enabled' => true]);
        $base = $this->client(false);
        $this->record('9');
        $latest = $this->record('10');
        $this->getJson('/api/config-version/CFG')->assertOk()->assertJsonPath('version', '10');
        $this->get('/api/config-file/prefix/CFG')->assertDownload(basename($latest->file_path));
        $huge = str_repeat('9', 64);
        $this->patchJson($base.'/'.$latest->id, ['version' => $huge])->assertOk();
        $this->getJson('/api/config-version/CFG')->assertOk()->assertJsonPath('version', $huge);
        $this->get('/config-version/CFG')->assertOk()->assertSee($huge);
        $this->deleteJson($base.'/'.$latest->id)->assertOk();
        $this->getJson('/api/config-version/CFG')->assertOk()->assertJsonPath('version', '9');
        $this->record('001.20', 'LEGACY');
        $this->record('002.00', 'LEGACY');
        $this->getJson('/api/config-version/LEGACY')->assertOk()->assertJsonPath('version', '002.00');
    }
}
