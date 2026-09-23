<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__.'/../../scripts/verify-frontend-assets.php';

class FrontendBuildTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/obsidian-assets-'.bin2hex(random_bytes(8));
        mkdir($this->directory.'/assets', 0700, true);
        foreach (['app.js', 'page.js', 'page.css'] as $file) {
            file_put_contents($this->directory.'/assets/'.$file, 'fixture');
        }
        $this->manifest();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/assets/*') as $file) {
            unlink($file);
        }
        if (is_file($this->directory.'/manifest.json')) {
            unlink($this->directory.'/manifest.json');
        }
        rmdir($this->directory.'/assets');
        rmdir($this->directory);
    }

    private function manifest(string $page = 'page', string $css = 'assets/page.css'): void
    {
        file_put_contents($this->directory.'/manifest.json', json_encode([
            'resources/js/entries/app.js' => [
                'file' => 'assets/app.js', 'isEntry' => true, 'dynamicImports' => [$page],
            ],
            'page' => ['file' => 'assets/page.js', 'css' => [$css]],
        ]));
    }

    public function test_complete_build_including_lazy_styles_is_accepted(): void
    {
        $this->assertSame(3, verifyFrontendAssets($this->directory));
    }

    public function test_missing_manifest_is_rejected(): void
    {
        unlink($this->directory.'/manifest.json');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('manifest is missing');
        verifyFrontendAssets($this->directory);
    }

    public function test_missing_lazy_stylesheet_is_rejected(): void
    {
        unlink($this->directory.'/assets/page.css');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('assets/page.css');
        verifyFrontendAssets($this->directory);
    }

    public function test_empty_javascript_is_rejected(): void
    {
        file_put_contents($this->directory.'/assets/page.js', '');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('assets/page.js');
        verifyFrontendAssets($this->directory);
    }

    public function test_missing_dynamic_import_is_rejected(): void
    {
        $this->manifest('missing-page');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing chunk: missing-page');
        verifyFrontendAssets($this->directory);
    }

    public function test_asset_outside_build_directory_is_rejected(): void
    {
        $this->manifest('page', 'assets/../../secret');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid frontend asset path');
        verifyFrontendAssets($this->directory);
    }
}
