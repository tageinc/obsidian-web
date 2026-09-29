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
    'filesystems.default' => 'local',
    'filesystems.disks.local.root' => $fixtureRoot.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app',
    'filesystems.disks.public.root' => $fixtureRoot.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public',
]);
// The cookie exists only in this guarded harness, so real signed links and native
// form redirects can exercise the fallback auth renderer without changing URLs.
if (($_COOKIE['obsidian_browser_auth_renderer'] ?? null) === 'legacy') {
    config(['frontend.vue3.auth' => false]);
}
$app->make(Illuminate\Contracts\Debug\ExceptionHandler::class)->reportable(function (Throwable $error) {
    file_put_contents('php://stderr', get_class($error).': '.$error->getMessage().PHP_EOL);
});

// Capture only dedicated synthetic authentication recipients after the real array
// transport accepts their rendered mail. Links stay in private temporary storage.
$authMailRecipients = [];
foreach (['reset', 'verify', 'register'] as $purpose) {
    foreach (['modern', 'legacy'] as $renderer) {
        foreach (['desktop', 'mobile'] as $project) {
            $authMailRecipients[] = 'auth-'.$purpose.'-'.$renderer.'-'.$project.'@browser.example.test';
        }
    }
}
$authMailPath = static function (string $email, string $kind) use ($fixtureRoot): string {
    return $fixtureRoot.DIRECTORY_SEPARATOR.'auth-mail-'.$kind.'-'.hash('sha256', $email).'.json';
};
$authMailTransport = new class($authMailRecipients, $authMailPath) extends Illuminate\Mail\Transport\ArrayTransport {
    private array $recipients;
    private $path;

    public function __construct(array $recipients, callable $path)
    {
        parent::__construct();
        $this->recipients = $recipients;
        $this->path = $path;
    }

    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        foreach (array_keys($message->getTo() ?? []) as $email) {
            if (in_array($email, $this->recipients, true) && is_file(($this->path)($email, 'failure'))) {
                throw new Swift_TransportException('Synthetic browser mail delivery failure.');
            }
        }

        return parent::send($message, $failedRecipients);
    }
};
$app->make('mailer')->setSwiftMailer(new Swift_Mailer($authMailTransport));
Illuminate\Support\Facades\Event::listen(Illuminate\Mail\Events\MessageSent::class,
    static function ($event) use ($authMailRecipients, $authMailPath) {
        $recipients = array_keys($event->message->getTo() ?? []);
        if (count($recipients) !== 1 || !in_array($recipients[0], $authMailRecipients, true)) {
            return;
        }
        preg_match_all('/href="([^"]+)"/', $event->message->getBody(), $matches);
        foreach ($matches[1] as $href) {
            $url = html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (parse_url($url, PHP_URL_HOST) !== '127.0.0.1'
                || parse_url($url, PHP_URL_PORT) !== 8127
                || !preg_match('#^/(password/reset|email/verify)/#', parse_url($url, PHP_URL_PATH) ?? '')) {
                continue;
            }
            file_put_contents($authMailPath($recipients[0], 'delivery'), json_encode([
                'email' => $recipients[0], 'url' => $url,
            ], JSON_THROW_ON_ERROR), LOCK_EX);
            break;
        }
    });
Illuminate\Support\Facades\Route::put('/__browser-fixtures/auth-mail',
    static function (Illuminate\Http\Request $request) use ($authMailRecipients, $authMailPath) {
        $values = $request->validate([
            'email' => ['required', Illuminate\Validation\Rule::in($authMailRecipients)],
            'fail' => ['required', 'boolean'],
        ]);
        $path = $authMailPath($values['email'], 'failure');
        if ($values['fail']) {
            file_put_contents($path, 'true', LOCK_EX);
        } elseif (is_file($path)) {
            unlink($path);
        }

        return response()->noContent();
    });
Illuminate\Support\Facades\Route::get('/__browser-fixtures/auth-mail',
    static function (Illuminate\Http\Request $request) use ($authMailRecipients, $authMailPath) {
        $values = $request->validate([
            'email' => ['required', Illuminate\Validation\Rule::in($authMailRecipients)],
        ]);
        $user = App\Models\User::where('email', $values['email'])->firstOrFail();
        $path = $authMailPath($values['email'], 'delivery');

        return response()->json([
            'delivery' => is_file($path) ? json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR) : null,
            'verified' => $user->hasVerifiedEmail(),
            'activity_email_enabled' => $user->settings->receive_app_activity_emails,
            'notification_count' => $user->notifications()->count(),
        ]);
    });

// These routes exist only behind the disposable-database checks above. The live
// browser test removes only the tagged readings that it created.
Illuminate\Support\Facades\Route::get('/__browser-fixtures/legacy-login', function () {
    config(['frontend.vue3.auth' => false]);

    return view('auth.login');
})->middleware('web');

Illuminate\Support\Facades\Route::get('/__browser-fixtures/devices/{id}/legacy', function ($id) {
    config(['frontend.vue3.view_device' => false, 'frontend.vue3.workspace' => false]);

    return app(App\Http\Controllers\ViewDeviceController::class)->show($id);
})->middleware(['web', 'auth', 'verified', 'browser.device']);

Illuminate\Support\Facades\Route::get('/__browser-fixtures/developer/legacy', function (Illuminate\Http\Request $request) {
    config(['frontend.vue3.developer' => false, 'frontend.vue3.workspace' => false]);

    return app(App\Http\Controllers\DeveloperWorkspaceController::class)->index($request);
})->middleware(['web', 'auth', 'verified', 'browser.developer']);

Illuminate\Support\Facades\Route::delete('/__browser-fixtures/firmware', function (Illuminate\Http\Request $request) {
    $values = $request->validate([
        'prefix' => ['required', 'string', 'regex:/\ABROWSER-MUTATION-(modern|legacy)-(desktop|mobile)\z/'],
    ]);
    foreach (App\Models\FirmwareVersions::where('prefix', $values['prefix'])->get() as $firmware) {
        Illuminate\Support\Facades\Storage::disk('local')->delete($firmware->file_path);
        $firmware->delete();
    }

    return response()->noContent();
})->middleware(['web', 'auth', 'verified', 'browser.developer']);

Illuminate\Support\Facades\Route::delete('/__browser-fixtures/config', function (Illuminate\Http\Request $request) {
    $values = $request->validate([
        'prefix' => ['required', 'string', 'regex:/\ABROWSER-MUTATION-(modern|legacy)-(desktop|mobile)\z/'],
    ]);
    foreach (App\Models\ConfigVersions::where('prefix', $values['prefix'])->get() as $configuration) {
        Illuminate\Support\Facades\Storage::disk('local')->delete($configuration->file_path);
        $configuration->delete();
    }

    return response()->noContent();
})->middleware(['web', 'auth', 'verified', 'browser.developer']);

Illuminate\Support\Facades\Route::post('/__browser-fixtures/telemetry', function (Illuminate\Http\Request $request) {
    $values = $request->validate([
        'temp' => 'required|numeric',
        'ps_avg' => 'required|numeric',
        'pds' => 'required|numeric',
        'config_ota' => 'sometimes|array',
        'firmware_version' => 'nullable|string|max:255',
        'config_version' => 'nullable|string|max:255',
        'prefix' => 'nullable|string|max:255',
        'cts' => 'sometimes|nullable|integer',
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
            'ps1' => 35, 'ps2' => 45, 'motor_speed' => 0, 'state' => 'solar-track',
            'cts' => array_key_exists('cts', $values) ? $values['cts'] : 1,
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

Illuminate\Support\Facades\Route::post('/__browser-fixtures/app-activity', function (Illuminate\Http\Request $request) {
    $values = $request->validate(['count' => 'required|integer|min:1|max:12']);
    $owner = $request->user();
    abort_unless($owner && $owner->email === 'owner@browser.example.test', 403);
    $device = App\Models\Device::where('serial_no', 'BROWSER-SIMULATOR-1')
        ->where('user_id', $owner->id)->firstOrFail();
    $ids = [];
    for ($index = 1; $index <= $values['count']; $index++) {
        $notice = new App\Notifications\DeviceStatusChangedActivity($device, 'online', 'fixture status '.$index);
        $notice->id = (string) Illuminate\Support\Str::uuid();
        $owner->notify($notice);
        $ids[] = $notice->id;
    }

    return response()->json(['ids' => $ids], 201);
})->middleware(['web', 'auth', 'verified']);

return $app;
