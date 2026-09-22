<?php

use App\Models\Api\SolarTrackerLog;
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
DB::table('hardware')->insert(['id' => 1, 'name' => 'Solar Tracker', 'prefix' => 'BROWSER']);
foreach (['owner', 'admin', 'empty'] as $role) {
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
    'user_id' => $owner->id, 'hardware_id' => 1, 'serial_no' => 'BROWSER-SIMULATOR-1',
    'name' => 'Browser simulator', 'sku' => 'FIXTURE', 'order_no' => 'TEST-ORDER',
    'address_1' => '1 Fixture Street', 'city' => 'Test City', 'address_state' => 'CA',
    'zip_code' => '90001', 'country' => 'US', 'latitude' => 33.7263, 'longitude' => -117.9190,
]);
GeoCode::create(['serial_no' => $device->serial_no, 'latitude' => $device->latitude, 'longitude' => $device->longitude, 'status' => 'online']);
SolarTrackerRemoteControl::create(['serial_no' => $device->serial_no, 'mode' => 0, 'motor_speed' => 0]);
foreach ([0, 1, 4, 12, 24, 30] as $index => $hours) {
    $log = new SolarTrackerLog([
        'serial_no' => $device->serial_no, 'ps1' => 12 + $index, 'ps2' => 22 + $index,
        'ps_avg' => 99, 'temp' => 20 + $index, 'motor_speed' => 0, 'state' => 'solar-track', 'cts' => 1,
    ]);
    $log->created_at = now()->subHours($hours);
    $log->updated_at = $log->created_at;
    $log->save();
}
fwrite(STDOUT, "Isolated browser fixtures ready.\n");
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error).': '.$error->getMessage().PHP_EOL);
    exit(1);
}
