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
    'app.developer_email' => 'developer@browser.example.test',
    'mail.default' => 'array',
    'logging.default' => 'null',
    'redis-workloads.enabled' => false,
]);
$app->make(Illuminate\Contracts\Debug\ExceptionHandler::class)->reportable(function (Throwable $error) {
    file_put_contents('php://stderr', get_class($error).': '.$error->getMessage().PHP_EOL);
});

// These routes exist only behind the disposable-database checks above. The live
// browser test removes only the tagged readings that it created.
Illuminate\Support\Facades\Route::post('/__browser-fixtures/telemetry', function (Illuminate\Http\Request $request) {
    $values = $request->validate([
        'temp' => 'required|numeric',
        'ps_avg' => 'required|numeric',
        'pds' => 'required|numeric',
    ]);
    $serial = 'BROWSER-SIMULATOR-1';
    abort_unless(App\Models\Device::where('serial_no', $serial)->exists(), 404);
    $latest = App\Models\Api\DeviceLog::where('serial_no', $serial)->max('updated_at');
    $recordedAt = $latest && Carbon\Carbon::parse($latest)->gte(now())
        ? Carbon\Carbon::parse($latest)->addSecond()
        : now();
    $reading = new App\Models\Api\DeviceLog([
        'serial_no' => $serial,
        'data' => array_merge($values, [
            'ps1' => 35, 'ps2' => 45, 'motor_speed' => 0, 'state' => 'solar-track', 'cts' => 1,
            'browser_live_fixture' => true,
        ]),
    ]);
    $reading->created_at = $recordedAt;
    $reading->updated_at = $recordedAt;
    $reading->save();

    return response()->json(['id' => $reading->id], 201);
});
Illuminate\Support\Facades\Route::delete('/__browser-fixtures/telemetry/{id}', function (int $id) {
    $reading = App\Models\Api\DeviceLog::where('serial_no', 'BROWSER-SIMULATOR-1')->findOrFail($id);
    abort_unless(($reading->data['browser_live_fixture'] ?? false) === true, 403);
    $reading->delete();

    return response()->noContent();
});

return $app;
