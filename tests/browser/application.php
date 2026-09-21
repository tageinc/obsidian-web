<?php

// This harness is not routable in a deployed app and refuses every non-test database.
$fixtureRoot = getenv('OBSIDIAN_BROWSER_TEST_ROOT');
$databasePath = getenv('DB_DATABASE');
if (getenv('OBSIDIAN_BROWSER_TEST') !== '1' || getenv('APP_ENV') !== 'testing'
    || getenv('DB_CONNECTION') !== 'sqlite' || !$fixtureRoot || !$databasePath
    || !preg_match('/^obsidian-browser-[\w-]+$/', basename($fixtureRoot))
    || realpath(dirname($fixtureRoot)) !== realpath(sys_get_temp_dir())
    || realpath($databasePath) !== realpath($fixtureRoot.DIRECTORY_SEPARATOR.'database.sqlite')) {
    throw new RuntimeException('Browser fixtures require an isolated temporary SQLite database.');
}

require_once dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
if (DIRECTORY_SEPARATOR === '\\') {
    $app->addAbsoluteCachePathPrefix(substr($fixtureRoot, 0, 3));
}
$app->useStoragePath($fixtureRoot.DIRECTORY_SEPARATOR.'storage');
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (config('database.default') !== 'sqlite'
    || realpath(config('database.connections.sqlite.database')) !== realpath($databasePath)) {
    throw new RuntimeException('Browser tests cannot connect to the configured application database.');
}
config([
    'app.admin_email' => 'admin@browser.example.test',
    'mail.default' => 'array',
    'logging.default' => 'null',
    'redis-workloads.enabled' => false,
]);
$app->make(Illuminate\Contracts\Debug\ExceptionHandler::class)->reportable(function (Throwable $error) {
    file_put_contents('php://stderr', get_class($error).': '.$error->getMessage().PHP_EOL);
});

return $app;
