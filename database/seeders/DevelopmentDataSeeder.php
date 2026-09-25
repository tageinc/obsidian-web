<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Random synthetic local data. Never run this against production. */
class DevelopmentDataSeeder extends Seeder
{
    public const DEVICE_COUNT = 15;
    public const STATUSES = ['online', 'offline', 'idle', 'set-up', 'calibration', 'solar-track', 'sleep', 'safe', 'remote-control'];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('DevelopmentDataSeeder is restricted to local and testing environments.');

            return;
        }

        DB::transaction(function () {
            $user = User::firstOrCreate(['email' => 'andre.troncoso@tezca.net'], [
                'name' => 'Andre Troncoso',
                'email' => 'andre.troncoso@tezca.net',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $adjectives = ['Sunny', 'Golden', 'Quiet', 'Bright', 'Silver', 'Emerald', 'Crimson', 'Amber'];
            $names = ['Meadow', 'Ridge', 'Valley', 'Summit', 'Grove', 'Horizon', 'Orchard', 'Canyon'];

            for ($index = 1; $index <= self::DEVICE_COUNT; $index++) {
                $serial = sprintf('2026%08d', $index);
                $latitude = random_int(-90000000, 90000000) / 1000000;
                $longitude = random_int(-180000000, 180000000) / 1000000;
                $status = self::STATUSES[array_rand(self::STATUSES)];
                $name = $adjectives[array_rand($adjectives)].' '.$names[array_rand($names)].' '.$index;
                DB::table('devices')->updateOrInsert(['serial_no' => $serial], [
                    'user_id' => $user->id,
                    'latitude' => $latitude, 'longitude' => $longitude,
                    'sku' => 'SP1', 'name' => $name, 'order_no' => sprintf('DEV-%04d', $index),
                    'address_1' => '1 Development Way', 'city' => 'Testville', 'address_state' => 'CA',
                    'country' => 'US', 'zip_code' => '90000', 'status_notification' => 0,
                    'sms_notification' => 0, 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('geocode')->updateOrInsert(['serial_no' => $serial], [
                    'latitude' => $latitude, 'longitude' => $longitude, 'status' => $status,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                // Keep map status and telemetry-based status checks consistent.
                $reportedAt = $status === 'offline'
                    ? now()->subSeconds((int) config('devices.telemetry_freshness_seconds', 600) + 60)
                    : now();
                \App\Models\Api\DeviceLog::unguarded(fn () => \App\Models\Api\DeviceLog::updateOrCreate(['serial_no' => $serial], [
                    'ps1' => 0, 'ps2' => 0, 'ps_avg' => 0, 'pds' => 0,
                    'motor_speed' => 0, 'temp' => 20, 'cts' => 0, 'state' => $status,
                    'created_at' => $reportedAt, 'updated_at' => $reportedAt,
                ]));
            }
        });
    }
}
