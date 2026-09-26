<?php

namespace Tests\Unit;

use App\Support\FrontendAssets;
use Tests\TestCase;

class FrontendAssetsTest extends TestCase
{
    public function test_manifest_deduplicates_imported_css_and_emits_only_module_entry(): void
    {
        $tags = (string) FrontendAssets::fromManifest([
            'resources/js/entries/app.js' => ['file' => 'assets/app-abc.js', 'css' => ['assets/app-abc.css'], 'imports' => ['shared']],
            'shared' => ['file' => 'assets/shared.js', 'css' => ['assets/app-abc.css']],
        ]);
        $this->assertSame(1, substr_count($tags, 'app-abc.css'));
        $this->assertStringContainsString('type="module"', $tags);
        $this->assertStringNotContainsString('shared.js', $tags);
    }

    public function test_missing_entry_fails_clearly(): void
    {
        $this->expectException(\RuntimeException::class);
        FrontendAssets::fromManifest([]);
    }

    public function test_legacy_activity_entry_loads_only_its_own_styles_and_script(): void
    {
        $tags = (string) FrontendAssets::fromManifest([
            'resources/js/entries/app.js' => ['file' => 'assets/app.js', 'css' => ['assets/bootstrap.css']],
            'resources/js/entries/activity.js' => ['file' => 'assets/activity.js', 'imports' => ['controls']],
            'controls' => ['file' => 'assets/controls.js', 'css' => ['assets/activity.css']],
        ], 'resources/js/entries/activity.js');

        $this->assertStringContainsString('assets/activity.js', $tags);
        $this->assertStringContainsString('assets/activity.css', $tags);
        $this->assertStringNotContainsString('assets/app.js', $tags);
        $this->assertStringNotContainsString('bootstrap.css', $tags);
    }

    public function test_unexpected_manifest_path_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        FrontendAssets::fromManifest(['resources/js/entries/app.js' => ['file' => 'assets/../../secret.js']]);
    }
}
