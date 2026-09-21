<?php

namespace Tests\Concerns;

trait UsesFrontendManifest
{
    protected function useFrontendManifest(): void
    {
        // Page contract tests use fixture assets; production compilation has its own checks.
        $path = base_path('tests/Fixtures/frontend');
        $this->app->instance('path.public', $path);
        $this->assertSame($path, public_path());
        $this->assertFileExists(public_path('build/manifest.json'));
    }
}
