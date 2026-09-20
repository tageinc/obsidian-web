<?php

declare(strict_types=1);

/**
 * Lints first-party PHP files without requiring a network-installed formatter.
 *
 * Run with `composer lint` or `php scripts/lint.php`.
 */

$root = dirname(__DIR__);
$directories = ['app', 'bootstrap', 'config', 'database', 'routes', 'tests'];
$files = [];

foreach ($directories as $directory) {
    $path = $root.DIRECTORY_SEPARATOR.$directory;
    if (!is_dir($path)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
}

$files[] = $root.DIRECTORY_SEPARATOR.'artisan';
$failures = [];

foreach ($files as $file) {
    $process = proc_open(
        [PHP_BINARY, '-l', $file],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $root
    );

    if (!is_resource($process)) {
        $failures[] = $file.': unable to start PHP lint';
        continue;
    }

    $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    if (proc_close($process) !== 0) {
        $failures[] = trim($output);
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures).PHP_EOL);
    exit(1);
}

fwrite(STDOUT, sprintf("PHP syntax lint passed for %d files.%s", count($files), PHP_EOL));
