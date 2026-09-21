<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use RuntimeException;

class FrontendAssets
{
    public static function tags(): HtmlString
    {
        $dev = config('frontend.dev_server');
        if ($dev && app()->environment('local')) {
            if (!preg_match('#^http://(?:localhost|127\.0\.0\.1):[0-9]+$#', $dev)) {
                throw new RuntimeException('The frontend development server must be local.');
            }
            return new HtmlString('<script type="module" src="'.e($dev).'/@vite/client"></script><script type="module" src="'.e($dev).'/resources/js/entries/app.js"></script>');
        }

        $path = public_path('build/manifest.json');
        if (!is_file($path)) {
            throw new RuntimeException('Frontend assets are missing. Run npm ci and npm run build.');
        }
        $manifest = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        return static::fromManifest($manifest);
    }

    public static function fromManifest(array $manifest): HtmlString
    {
        $entry = 'resources/js/entries/app.js';
        $styles = [];
        $visited = [];
        $walk = function ($key) use (&$walk, &$styles, &$visited, $manifest) {
            if (isset($visited[$key])) {
                return;
            }
            if (!isset($manifest[$key]['file'])) {
                throw new RuntimeException('Frontend manifest entry is missing.');
            }
            $visited[$key] = true;
            foreach ($manifest[$key]['imports'] ?? [] as $import) {
                $walk($import);
            }
            foreach ($manifest[$key]['css'] ?? [] as $css) {
                $styles[$css] = true;
            }
        };
        $walk($entry);
        $url = function ($asset) {
            if (!is_string($asset) || !preg_match('#^assets/[a-zA-Z0-9_./-]+$#', $asset) || strpos($asset, '..') !== false) {
                throw new RuntimeException('Unexpected frontend asset path.');
            }
            return e(asset('build/'.$asset));
        };
        $tags = [];
        foreach (array_keys($styles) as $style) {
            $tags[] = '<link rel="stylesheet" href="'.$url($style).'">';
        }
        $tags[] = '<script type="module" src="'.$url($manifest[$entry]['file']).'"></script>';
        return new HtmlString(implode("\n", $tags));
    }
}
