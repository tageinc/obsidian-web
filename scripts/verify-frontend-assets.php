<?php

// Standalone: usable during image construction without Laravel, a database, or secrets.
function verifyFrontendAssets(string $directory): int
{
    $manifestPath = $directory.'/manifest.json';
    if (!is_file($manifestPath)) {
        throw new RuntimeException('Frontend manifest is missing. Run npm ci and npm run build.');
    }
    $manifest = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
    $entry = 'resources/js/entries/app.js';
    if (!is_array($manifest) || empty($manifest[$entry]['isEntry']) || empty($manifest[$entry]['file'])) {
        throw new RuntimeException('Frontend app entry is missing from the manifest.');
    }

    $files = [];
    foreach ($manifest as $key => $chunk) {
        if (!is_array($chunk) || empty($chunk['file'])) {
            throw new RuntimeException('Invalid frontend manifest chunk: '.$key);
        }
        foreach (['imports', 'dynamicImports'] as $field) {
            foreach ($chunk[$field] ?? [] as $import) {
                if (!isset($manifest[$import])) {
                    throw new RuntimeException('Frontend manifest references a missing chunk: '.$import);
                }
            }
        }
        foreach (array_merge([$chunk['file']], $chunk['css'] ?? [], $chunk['assets'] ?? []) as $file) {
            if (!is_string($file) || !preg_match('#^assets/[a-zA-Z0-9_./-]+$#', $file) || strpos($file, '..') !== false) {
                throw new RuntimeException('Invalid frontend asset path.');
            }
            $path = $directory.'/'.$file;
            if (!is_file($path) || !is_readable($path) || filesize($path) === 0) {
                throw new RuntimeException('Frontend asset is missing, empty, or unreadable: '.$file);
            }
            $files[$file] = true;
        }
    }

    return count($files);
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        $count = verifyFrontendAssets(__DIR__.'/../public/build');
        echo 'Frontend assets verified: '.$count.' files (including lazy chunks and styles).'.PHP_EOL;
    } catch (Throwable $error) {
        fwrite(STDERR, 'Frontend asset verification failed: '.$error->getMessage().PHP_EOL);
        exit(1);
    }
}
