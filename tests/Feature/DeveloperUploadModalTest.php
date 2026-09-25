<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\UsesFrontendManifest;
use Tests\TestCase;

class DeveloperUploadModalTest extends TestCase
{
    use RefreshDatabase;
    use UsesFrontendManifest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useFrontendManifest();
        config(['frontend.vue3.developer' => true, 'app.developer_email' => 'upload-modal@example.test']);
        $this->actingAs(User::create([
            'name' => 'Upload developer', 'email' => 'upload-modal@example.test',
            'password' => 'synthetic-hash', 'email_verified_at' => now(),
        ]));
        Storage::fake('local');
    }

    private function props($response): array
    {
        $response->assertOk();
        $this->assertSame(1, preg_match('/<script id="frontend-developer" type="application\/json">(.*?)<\/script>/s', $response->getContent(), $matches));

        return json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
    }

    public static function uploadKinds(): array
    {
        return [['firmware', 'bin', 10240], ['config', 'json', 1024]];
    }

    /** @dataProvider uploadKinds */
    public function test_rejected_native_upload_reopens_only_its_modal_and_retains_text(string $kind, string $extension, int $maxKb): void
    {
        $description = '</script><img src=x onerror=alert(1)>';
        $url = '/developer-workspace?section='.($kind === 'firmware' ? 'config' : 'firmware').'&firmware_show=2&config_show=10';
        $this->from($url)->post('/upload-'.$kind, [
            '_upload_kind' => $kind, $kind => UploadedFile::fake()->create('rejected.'.$extension, $maxKb + 1),
            'description' => $description, 'prefix' => 'TEST', 'file_path' => 'private-path-must-not-appear',
        ])->assertRedirect($url)->assertSessionHasErrors($kind)
            ->assertSessionHasInput('_upload_kind', $kind)->assertSessionHasInput('description', $description);
        $response = $this->get($url);
        $props = $this->props($response);
        $this->assertSame($kind, $props['initiallyOpenUpload']);
        $this->assertSame($kind, $props['activeUpload']);
        $this->assertSame($kind, $props['activeSection']);
        $this->assertSame(['description' => $description, 'prefix' => 'TEST'], $props['values']);
        $this->assertArrayHasKey($kind, $props['errors']);
        $this->assertNull($props['success']);
        $response->assertDontSee($description, false)->assertDontSee('private-path-must-not-appear');
        $this->assertDatabaseCount('firmware_versions', 0);
        $this->assertDatabaseCount('config_versions', 0);
    }

    /** @dataProvider uploadKinds */
    public function test_storage_failure_reopens_server_selected_kind_and_flashes_only_safe_text(string $kind, string $extension, int $maxKb): void
    {
        $content = $kind === 'config' ? '{"fixture":"file-content-must-not-appear"}' : 'file-content-must-not-appear';
        $fixture = UploadedFile::fake()->createWithContent('failed.'.$extension, $content);
        $failedFile = new class($fixture->getPathname(), 'failed.'.$extension, $fixture->getMimeType(), null, true) extends UploadedFile {
            public function storeAs($path, $name, $options = [])
            {
                return false;
            }
        };
        $opposite = $kind === 'firmware' ? 'config' : 'firmware';
        $url = '/developer-workspace?section='.$opposite;
        $this->from($url)->post('/upload-'.$kind, [
            '_upload_kind' => $opposite, $kind => $failedFile,
            'description' => 'Retained description', 'prefix' => 'TEST',
            'file_path' => 'private-path-must-not-appear', 'extra' => 'unknown-input-must-not-appear',
        ])->assertRedirect($url)->assertSessionHasNoErrors()->assertSessionHas('active_upload', $kind)
            ->assertSessionHas('_old_input', [
                '_upload_kind' => $opposite, 'description' => 'Retained description', 'prefix' => 'TEST',
            ]);
        $response = $this->get($url);
        $props = $this->props($response);
        $this->assertSame($kind, $props['initiallyOpenUpload']);
        $this->assertSame($kind, $props['activeUpload']);
        $this->assertSame($kind, $props['activeSection']);
        $this->assertSame(['description' => 'Retained description', 'prefix' => 'TEST'], $props['values']);
        $this->assertSame([], $props['errors']);
        $this->assertNotEmpty($props['sessionError']);
        $response->assertDontSee('private-path-must-not-appear')->assertDontSee('file-content-must-not-appear')->assertDontSee('unknown-input-must-not-appear');
        $this->assertDatabaseCount('firmware_versions', 0);
        $this->assertDatabaseCount('config_versions', 0);
    }

    /** @dataProvider uploadKinds */
    public function test_successful_upload_returns_to_history_without_reopening_or_exposing_stored_files(string $kind, string $extension, int $maxKb): void
    {
        $content = $kind === 'config' ? '{"fixture":"file-content-must-not-appear"}' : 'file-content-must-not-appear';
        $url = '/developer-workspace?section='.$kind;
        $this->from($url)->post('/upload-'.$kind, [
            '_upload_kind' => $kind, $kind => UploadedFile::fake()->createWithContent('success.'.$extension, $content),
            'description' => 'Release fixture', 'prefix' => 'TEST',
        ])->assertRedirect($url)->assertSessionHasNoErrors()->assertSessionHas('success');
        $response = $this->get($url);
        $props = $this->props($response);
        $this->assertNull($props['initiallyOpenUpload']);
        $this->assertSame($kind, $props['activeSection']);
        $this->assertSame([], $props['errors']);
        $this->assertNull($props['sessionError']);
        $this->assertSame(['description' => null, 'prefix' => null], $props['values']);
        $this->assertSame(['version', 'prefix', 'description', 'createdAt'], array_keys($props[$kind]['rows'][0]));
        $response->assertDontSee('file-content-must-not-appear')->assertDontSee('public/'.$kind.'/', false);
    }

    public function test_history_queries_and_retained_text_do_not_open_uploads_and_legacy_flag_keeps_forms(): void
    {
        $props = $this->props($this->withSession([
            '_old_input' => ['_upload_kind' => 'config', 'description' => 'An earlier value', 'prefix' => 'TEST'],
        ])->get('/developer-workspace?section=config'));
        $this->assertSame('config', $props['activeSection']);
        $this->assertNull($props['initiallyOpenUpload']);
        config(['frontend.vue3.developer' => false]);
        $this->get('/developer-workspace')->assertOk()->assertDontSee('data-vue-page="developer"', false)
            ->assertSee('action="'.route('uploadFirmware').'"', false)
            ->assertSee('action="'.route('uploadConfig').'"', false);
    }
}
