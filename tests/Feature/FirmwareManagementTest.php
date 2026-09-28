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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class FirmwareManagementTest extends TestCase
{
    private User $developer;
    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();
        // Real commits exercise storage cleanup and cache callbacks on a fresh in-memory DB.
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        Carbon::setTestNow(Carbon::parse('2026-09-28T12:00:00Z'));
        $this->developer = User::factory()->create(['email' => 'firmware-developer@example.test']);
        config(['app.developer_email' => $this->developer->email]);
        [, $this->secret] = ExternalApiKey::issue($this->developer, 'Firmware fixture', null);
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
            'version' => $version, 'prefix' => 'SP1', 'description' => 'Synthetic firmware',
            'firmware' => UploadedFile::fake()->createWithContent('firmware.bin', 'synthetic firmware bytes'),
        ];
    }

    private function record(string $version = '1', string $prefix = 'SP1', ?string $path = null): FirmwareVersions
    {
        $path = $path ?? 'public/firmware/fixture-'.$version.'.bin';
        Storage::put($path, 'fixture bytes '.$version);

        return FirmwareVersions::create([
            'version' => $version, 'prefix' => $prefix, 'description' => 'Fixture description', 'file_path' => $path,
        ]);
    }

    private function client(bool $external): string
    {
        Auth::forgetGuards();
        $this->withHeader('Authorization', $external ? 'Bearer '.$this->secret : '');
        if (!$external) {
            $this->actingAs($this->developer);
        }

        return $external ? '/api/external/v1/firmware' : '/developer-workspace/firmware';
    }

    public function test_browser_and_external_uploads_preserve_selected_integer_precision_and_unique_file_identity(): void
    {
        foreach ([false, true] as $external) {
            $base = $this->client($external);
            $version = $external ? str_repeat('9', 255) : '9007199254740993';
            $response = $this->postJson($external ? $base : '/upload-firmware', $this->payload($version))
                ->assertCreated()->assertJsonPath('version', $version)->assertDontSee('file_path');
            $record = FirmwareVersions::findOrFail($response->json('id'));
            $this->assertSame($version, $record->version);
            $this->assertSame('SP1', $record->prefix);
            $this->assertSame('synthetic firmware bytes', Storage::get($record->file_path));
            $this->assertMatchesRegularExpression('#^public/firmware/[a-f0-9-]{36}\.bin$#', $record->file_path);
        }
        $this->assertCount(2, Storage::allFiles('public/firmware'));
        $this->getJson('/api/external/v1/firmware')->assertOk()->assertDontSee('file_path');
        $discovery = $this->getJson('/api/external/v1')->assertOk()->json('endpoints');
        $this->assertContains('PATCH /api/external/v1/firmware/{firmware}', $discovery);
        $this->assertContains('DELETE /api/external/v1/firmware/{firmware}', $discovery);
    }

    public function test_upload_requires_a_positive_integer_and_global_unique_version_on_both_entry_points(): void
    {
        $this->record('12', 'OTHER');
        foreach ([false, true] as $external) {
            $base = $this->client($external);
            $url = $external ? $base : '/upload-firmware';
            foreach ([null, '', '0', '-1', '1.5', '1e3', '01', false, ['1'], str_repeat('9', 256), '12'] as $version) {
                $payload = $this->payload();
                $payload['version'] = $version;
                $this->postJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('version');
            }
        }
        $this->assertDatabaseCount('firmware_versions', 1);
        $this->assertCount(1, Storage::allFiles('public/firmware'));
        $payload = $this->payload();
        $payload['version'] = 1;
        $this->postJson('/api/external/v1/firmware', $payload)->assertCreated()->assertJsonPath('version', '1');
    }

    public function test_edit_keeps_record_identity_upload_time_prefix_and_stored_bytes_on_both_entry_points(): void
    {
        foreach ([false, true] as $external) {
            $base = $this->client($external);
            $record = $this->record($external ? '3' : '1');
            $path = $record->file_path;
            $uploadedAt = $record->created_at->toISOString();
            Carbon::setTestNow(now()->addDay());
            $version = $external ? '9007199254740994' : '9007199254740993';
            $this->patchJson($base.'/'.$record->id, ['version' => $version, 'description' => 'Edited description'])
                ->assertOk()->assertJsonPath('id', $record->id)->assertJsonPath('version', $version)->assertDontSee('file_path');
            $record->refresh();
            $this->assertSame($uploadedAt, $record->created_at->toISOString());
            $this->assertSame('SP1', $record->prefix);
            $this->assertSame('Edited description', $record->description);
            $this->assertSame($path, $record->file_path);
            $this->get('/api/firmware-file/version/'.$version)->assertOk()->assertDownload(basename($path));
            $this->get('/api/firmware-file/version/'.($external ? '3' : '1'))->assertNotFound();
        }
    }

    public function test_edit_rejects_collisions_invalid_versions_and_changes_outside_the_two_editable_fields(): void
    {
        $record = $this->record('1');
        $this->record('2', 'OTHER');
        foreach ([false, true] as $external) {
            $base = $this->client($external).'/'.$record->id;
            foreach (['0', '2', '1.5', '01', str_repeat('1', 256)] as $version) {
                $this->patchJson($base, ['version' => $version])->assertUnprocessable()->assertJsonValidationErrors('version');
            }
            foreach (['prefix' => 'CHANGED', 'file_path' => 'elsewhere', 'created_at' => '2000-01-01', 'id' => 99] as $field => $value) {
                $this->patchJson($base, [$field => $value])->assertUnprocessable()->assertJsonValidationErrors($field);
            }
            $this->patchJson($base, ['firmware' => UploadedFile::fake()->create('replacement.bin')])
                ->assertUnprocessable()->assertJsonValidationErrors('firmware');
            $this->patchJson($base, ['description' => ''])->assertUnprocessable()->assertJsonValidationErrors('description');
            $this->patchJson($base, ['version' => '1', 'description' => 'Allowed same version'])->assertOk();
            $this->patchJson($base, ['prefix' => null, 'file_path' => null, 'description' => 'Rejected update'])
                ->assertUnprocessable()->assertJsonValidationErrors(['prefix', 'file_path']);
        }
        $this->assertSame('1', $record->fresh()->version);
        $this->assertSame('SP1', $record->fresh()->prefix);
        $this->assertCount(2, Storage::allFiles('public/firmware'));
    }

    public function test_description_only_edit_preserves_legacy_version_and_new_upload_cannot_overwrite_edited_file(): void
    {
        $base = $this->client(false);
        $legacy = $this->record('001.20');
        $this->patchJson($base.'/'.$legacy->id, ['description' => 'Legacy description'])->assertOk()->assertJsonPath('version', '001.20');
        $created = $this->postJson('/upload-firmware', $this->payload('1'))->assertCreated();
        $first = FirmwareVersions::findOrFail($created->json('id'));
        $this->patchJson($base.'/'.$first->id, ['version' => '2'])->assertOk();
        $payload = $this->payload('1');
        $payload['firmware'] = UploadedFile::fake()->createWithContent('second.bin', 'second release bytes');
        $second = $this->postJson('/upload-firmware', $payload)->assertCreated();
        $this->assertSame('synthetic firmware bytes', Storage::get($first->file_path));
        $this->assertSame('second release bytes', Storage::get(FirmwareVersions::findOrFail($second->json('id'))->file_path));
        $this->assertSame('001.20', $legacy->fresh()->version);
    }

    public function test_delete_removes_only_unreferenced_stored_files_and_supports_both_entry_points(): void
    {
        foreach ([false, true] as $external) {
            $base = $this->client($external);
            $record = $this->record($external ? '2' : '1');
            $this->deleteJson($base.'/'.$record->id)->assertOk()->assertJsonPath('message', 'Firmware deleted successfully.');
            $this->assertDatabaseMissing('firmware_versions', ['id' => $record->id]);
            Storage::assertMissing($record->file_path);
            $this->get('/api/firmware-file/version/'.$record->version)->assertNotFound();
            $this->deleteJson($base.'/'.$record->id)->assertNotFound();
        }
        $first = $this->record('3', 'SP1', 'public/firmware/shared-legacy.bin');
        $second = $this->record('4', 'OTHER', $first->file_path);
        $base = $this->client(false);
        $this->deleteJson($base.'/'.$first->id)->assertOk();
        Storage::assertExists($second->file_path);
        $configuration = ConfigVersions::create(['version' => '1', 'prefix' => 'SP1', 'file_path' => $second->file_path]);
        $this->deleteJson($base.'/'.$second->id)->assertOk();
        Storage::assertExists($configuration->file_path);
    }

    public function test_failed_database_persistence_cleans_up_the_new_upload(): void
    {
        $this->client(false);
        FirmwareVersions::creating(function () {
            throw new RuntimeException('Synthetic persistence failure');
        });
        $this->withoutExceptionHandling();
        try {
            $this->postJson('/upload-firmware', $this->payload());
            $this->fail('The synthetic persistence failure must propagate.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic persistence failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('firmware_versions', 0);
        $this->assertSame([], Storage::allFiles('public/firmware'));
    }

    public function test_failed_deletion_staging_keeps_record_and_file_available_for_retry(): void
    {
        $base = $this->client(false);
        $record = $this->record();
        $disk = Storage::disk();
        Storage::shouldReceive('exists')->with($record->file_path)->andReturn(true);
        Storage::shouldReceive('move')->once()->andReturn(false);
        $this->deleteJson($base.'/'.$record->id)->assertStatus(500);
        $this->assertDatabaseHas('firmware_versions', ['id' => $record->id, 'file_path' => $record->file_path]);
        $this->assertTrue($disk->exists($record->file_path));
        $this->assertSame([], $disk->allFiles('firmware-deletions'));
    }

    public function test_failed_database_deletion_restores_staged_file_and_rolls_back_record(): void
    {
        $base = $this->client(false);
        $record = $this->record();
        FirmwareVersions::deleting(function () {
            throw new RuntimeException('Synthetic deletion failure');
        });
        $this->deleteJson($base.'/'.$record->id)->assertStatus(500);
        $this->assertDatabaseHas('firmware_versions', ['id' => $record->id]);
        $this->assertSame('fixture bytes 1', Storage::get($record->file_path));
        $this->assertSame([], Storage::allFiles('firmware-deletions'));
    }

    public function test_private_cleanup_failure_keeps_success_consistent_and_logs_without_storage_paths(): void
    {
        $base = $this->client(false);
        $record = $this->record();
        $disk = Storage::disk();
        Storage::shouldReceive('exists')->with($record->file_path)->andReturn(true);
        Storage::shouldReceive('move')->once()->andReturnUsing(fn ($from, $to) => $disk->move($from, $to));
        Storage::shouldReceive('delete')->once()->andReturn(false);
        Log::spy();
        $this->deleteJson($base.'/'.$record->id)->assertOk()->assertJsonPath('message', 'Firmware deleted successfully.');
        $this->assertDatabaseMissing('firmware_versions', ['id' => $record->id]);
        $this->assertFalse($disk->exists($record->file_path));
        $this->assertCount(1, $disk->allFiles('firmware-deletions'));
        Log::shouldHaveReceived('warning')->once()->with('firmware.private_deletion_cleanup_failed', ['release_id' => (string) $record->id]);
    }

    public function test_mutations_require_the_current_verified_developer_and_immutable_record_id(): void
    {
        $record = $this->record('9007199254740993');
        $url = '/developer-workspace/firmware/'.$record->id;
        $this->patchJson($url, ['description' => 'Denied'])->assertUnauthorized();
        $this->deleteJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create());
        $this->patchJson($url, ['description' => 'Denied'])->assertForbidden();
        $this->deleteJson($url)->assertForbidden();
        $this->actingAs($this->developer);
        foreach (['', null, 'another@example.test'] as $email) {
            config(['app.developer_email' => $email]);
            $this->patchJson($url, ['description' => 'Denied'])->assertForbidden();
            $this->deleteJson($url)->assertForbidden();
        }
        config(['app.developer_email' => $this->developer->email]);
        $this->developer->forceFill(['email_verified_at' => null])->save();
        $this->patchJson($url, ['description' => 'Denied'])->assertForbidden();
        $this->deleteJson($url)->assertForbidden();
        $this->developer->forceFill(['email_verified_at' => now()])->save();
        $this->patchJson('/developer-workspace/firmware/999999', ['version' => '2'])->assertNotFound();
        $this->deleteJson('/developer-workspace/firmware/'.$record->version)->assertNotFound();
        $this->getJson('/api/external/v1/firmware')->assertUnauthorized();
        $base = $this->client(true);
        $this->patchJson($base.'/999999', ['version' => '2'])->assertNotFound();
        $this->deleteJson($base.'/999999')->assertNotFound();
        config(['app.developer_email' => '']);
        $this->patchJson($base.'/'.$record->id, ['version' => '2'])->assertUnauthorized();
        $this->deleteJson($base.'/'.$record->id)->assertUnauthorized();
        $this->assertSame('Fixture description', $record->fresh()->description);
        Storage::assertExists($record->file_path);
    }

    public function test_numeric_latest_download_and_cache_follow_edits_and_deletions_without_losing_precision(): void
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
        $this->getJson('/api/firmware-version/SP1')->assertOk()->assertJsonPath('version', '10');
        $this->get('/api/firmware-file/prefix/SP1')->assertDownload(basename($latest->file_path));
        $huge = str_repeat('9', 255);
        $this->patchJson($base.'/'.$latest->id, ['version' => $huge])->assertOk();
        $this->getJson('/api/firmware-version/SP1')->assertOk()->assertJsonPath('version', $huge);
        $this->get('/firmware-version/SP1')->assertOk()->assertSee($huge);
        $this->deleteJson($base.'/'.$latest->id)->assertOk();
        $this->getJson('/api/firmware-version/SP1')->assertOk()->assertJsonPath('version', '9');
        $this->record('001.20', 'LEGACY');
        $this->record('002.00', 'LEGACY');
        $this->getJson('/api/firmware-version/LEGACY')->assertOk()->assertJsonPath('version', '002.00');
    }
}
