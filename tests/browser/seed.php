<?php

use App\Models\Api\DeviceLog;
use App\Models\Device;
use App\Models\GeoCode;
use App\Models\SolarTrackerRemoteControl;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

try {
$app = require __DIR__.'/application.php';
if (filesize(getenv('DB_DATABASE')) !== 0) {
    throw new RuntimeException('Browser fixture seeding requires a new empty database.');
}
if (Artisan::call('migrate', ['--force' => true]) !== 0) {
    throw new RuntimeException('Isolated browser migrations failed.');
}
foreach (['owner', 'developer', 'empty'] as $role) {
    // Deliberately synthetic, test-only credentials. Never passed to production seeders.
    $user = User::create([
        'name' => ucfirst($role).' browser fixture', 'email' => $role.'@browser.example.test',
        'password' => Hash::make('browser-test-password'), 'email_verified_at' => now(),
        'address_1' => '1 Fixture Street', 'city' => 'Test City', 'state' => 'CA',
        'zip_code' => '90001', 'country' => 'US',
    ]);
    if ($role === 'owner') $owner = $user;
}
$device = Device::create([
    'user_id' => $owner->id, 'serial_no' => 'BROWSER-SIMULATOR-1',
    'name' => 'Browser simulator', 'sku' => 'FIXTURE', 'order_no' => 'TEST-ORDER',
    'address_1' => '1 Fixture Street', 'city' => 'Test City', 'address_state' => 'CA',
    'zip_code' => '90001', 'country' => 'US', 'latitude' => 33.7263, 'longitude' => -117.9190,
]);
GeoCode::create(['serial_no' => $device->serial_no, 'latitude' => $device->latitude, 'longitude' => $device->longitude, 'status' => 'online']);
SolarTrackerRemoteControl::create(['serial_no' => $device->serial_no, 'mode' => 0, 'motor_speed' => 0]);
foreach ([0, 1, 4, 12, 24, 30] as $index => $hours) {
    $log = new DeviceLog([
        'serial_no' => $device->serial_no,
        'data' => ['firmware_version' => '001.020', 'config_version' => '0'],
        'ps1' => 12 + $index, 'ps2' => 22 + $index,
        'ps_avg' => 99, 'pds' => $index - 2, 'temp' => 20 + $index, 'motor_speed' => 0, 'state' => 'solar-track', 'cts' => 1,
    ]);
    $log->created_at = now()->subHours($hours);
    $log->updated_at = $log->created_at;
    $log->save();
}
foreach (['firmware_versions' => 'firmware', 'config_versions' => 'configuration'] as $table => $kind) {
    for ($version = 1; $version <= 12; $version++) {
        $uploaded = sprintf('2026-09-%02d 12:00:00', $version);
        DB::table($table)->insert([
            'version' => (string) $version,
            'prefix' => $version % 2 ? 'BROWSER-SP1' : 'BROWSER-SP2',
            'description' => $version === 1
                ? ($kind === 'firmware' ? 'Firmware sunrise calibration fixture.' : 'Configuration dusk schedule fixture.')
                : 'Synthetic '.$kind.' release '.$version.'.',
            'file_path' => 'browser-fixtures/'.$kind.'-'.$version,
            'timestamp' => $uploaded,
            'created_at' => $uploaded,
            'updated_at' => $uploaded,
        ]);
    }
}
$developerId = User::where('email', config('app.developer_email'))->value('id');
for ($index = 1; $index <= 24; $index++) {
    $created = sprintf('2024-01-%02d 12:00:00', $index);
    $state = $index % 4;
    DB::table('external_api_keys')->insert([
        'user_id' => $developerId,
        'name' => sprintf('Fixture integration %02d', $index),
        'prefix' => sprintf('fixture-key-%04d', $index),
        // These invalid-format values cannot authenticate as external API keys.
        'token_hash' => hash('sha256', 'browser-fixture-key-'.$index),
        'expires_at' => $state === 2 ? '2099-01-01 00:00:00' : ($state === 3 ? '2000-01-01 00:00:00' : null),
        'revoked_at' => $state === 0 ? $created : null,
        'last_used_at' => $index % 3 === 0 ? sprintf('2024-01-%02d 14:00:00', $index) : null,
        'created_at' => $created,
        'updated_at' => $created,
    ]);
}
fwrite(STDOUT, "Isolated browser fixtures ready.\n");
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error).': '.$error->getMessage().PHP_EOL);
    exit(1);
}
