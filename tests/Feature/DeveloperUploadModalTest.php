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

    private function assertLegacyDraft($response, string $kind, string $version, string $description, string $prefix): void
    {
        $response->assertOk();
        $document = new \DOMDocument;
        $previousErrors = libxml_use_internal_errors(true);
        try {
            $document->loadHTML($response->getContent());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }
        $xpath = new \DOMXPath($document);
        foreach (['firmware' => 'uploadFirmware', 'config' => 'uploadConfig'] as $formKind => $route) {
            $form = $xpath->query('//form[@action="'.route($route).'"]')->item(0);
            $this->assertNotNull($form);
            $this->assertSame($formKind === $kind ? $version : '', $xpath->query('.//input[@name="version"]', $form)->item(0)->getAttribute('value'));
            $this->assertSame($formKind === $kind ? $description : '', $xpath->query('.//textarea[@name="description"]', $form)->item(0)->textContent);
            $this->assertSame($formKind === $kind ? $prefix : '', $xpath->query('.//input[@name="prefix"]', $form)->item(0)->getAttribute('value'));
        }
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
            'version' => '1', 'description' => $description, 'prefix' => 'TEST', 'file_path' => 'private-path-must-not-appear',
        ])->assertRedirect($url)->assertSessionHasErrors($kind)
            ->assertSessionHasInput('_upload_kind', $kind)->assertSessionHasInput('description', $description);
        $response = $this->get($url);
        $props = $this->props($response);
        $this->assertSame($kind, $props['initiallyOpenUpload']);
        $this->assertSame($kind, $props['activeUpload']);
        $this->assertSame($kind, $props['activeSection']);
        $this->assertSame(['version' => '1', 'description' => $description, 'prefix' => 'TEST'], $props['values']);
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
            'version' => '1', 'description' => 'Retained description', 'prefix' => 'TEST',
            'file_path' => 'private-path-must-not-appear', 'extra' => 'unknown-input-must-not-appear',
        ])->assertRedirect($url)->assertSessionHasNoErrors()->assertSessionHas('active_upload', $kind)
            ->assertSessionHas('_old_input', [
                '_upload_kind' => $opposite, 'version' => '1',
                'description' => 'Retained description', 'prefix' => 'TEST',
            ]);
        $failureState = [
            '_old_input' => session()->getOldInput(),
            'active_upload' => session('active_upload'),
            'error' => session('error'),
        ];
        $response = $this->get($url);
        $props = $this->props($response);
        $this->assertSame($kind, $props['initiallyOpenUpload']);
        $this->assertSame($kind, $props['activeUpload']);
        $this->assertSame($kind, $props['activeSection']);
        $this->assertSame(['version' => '1', 'description' => 'Retained description', 'prefix' => 'TEST'], $props['values']);
        $this->assertSame([], $props['errors']);
        $this->assertNotEmpty($props['sessionError']);
        $response->assertDontSee('private-path-must-not-appear')->assertDontSee('file-content-must-not-appear')->assertDontSee('unknown-input-must-not-appear');
        $this->assertDatabaseCount('firmware_versions', 0);
        $this->assertDatabaseCount('config_versions', 0);
        config(['frontend.vue3.developer' => false]);
        $this->assertLegacyDraft($this->withSession($failureState)->get($url), $kind, '1', 'Retained description', 'TEST');
    }

    /** @dataProvider uploadKinds */
    public function test_successful_upload_returns_to_history_without_reopening_or_exposing_stored_files(string $kind, string $extension, int $maxKb): void
    {
        $content = $kind === 'config' ? '{"fixture":"file-content-must-not-appear"}' : 'file-content-must-not-appear';
        $url = '/developer-workspace?section='.$kind;
        $this->from($url)->post('/upload-'.$kind, [
            '_upload_kind' => $kind, $kind => UploadedFile::fake()->createWithContent('success.'.$extension, $content),
            'version' => '1', 'description' => 'Release fixture', 'prefix' => 'TEST',
        ])->assertRedirect($url)->assertSessionHasNoErrors()->assertSessionHas('success');
        $response = $this->get($url);
        $props = $this->props($response);
        $this->assertNull($props['initiallyOpenUpload']);
        $this->assertSame($kind, $props['activeSection']);
        $this->assertSame([], $props['errors']);
        $this->assertNull($props['sessionError']);
        $this->assertSame(['version' => null, 'description' => null, 'prefix' => null], $props['values']);
        $this->assertSame(['version', 'prefix', 'description', 'createdAt', 'id', 'updateUrl', 'deleteUrl'], array_keys($props[$kind]['rows'][0]));
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

    /** @dataProvider uploadKinds */
    public function test_legacy_validation_draft_is_retained_only_in_the_matching_upload_form(string $kind, string $extension, int $maxKb): void
    {
        config(['frontend.vue3.developer' => false]);
        $description = 'Selected '.$kind.' draft <with escaped text>';
        $errors = (new \Illuminate\Support\ViewErrorBag)->put('default', new \Illuminate\Support\MessageBag([
            'version' => ['That release version already exists.'],
        ]));
        $response = $this->withSession([
            '_old_input' => ['_upload_kind' => $kind, 'version' => '37', 'description' => $description, 'prefix' => 'DRAFT'],
            'errors' => $errors,
        ])->get('/developer-workspace?section='.($kind === 'firmware' ? 'config' : 'firmware'));

        $this->assertLegacyDraft($response, $kind, '37', $description, 'DRAFT');
    }
}
