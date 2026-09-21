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

    public function test_unexpected_manifest_path_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        FrontendAssets::fromManifest(['resources/js/entries/app.js' => ['file' => 'assets/../../secret.js']]);
    }
}
