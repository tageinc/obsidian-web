<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Synthetic local device and telemetry. Never run this against production. */
class DevelopmentDataSeeder extends Seeder
{
    public const SERIAL = '202600000001';
    public const LATITUDE = 34.0522;
    public const LONGITUDE = -118.2437;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('DevelopmentDataSeeder is restricted to local and testing environments.');

            return;
        }

        $userId = DB::table('users')->where('email', UserSeeder::EMAIL)->value('id');
        if (! $userId) {
            $this->call(UserSeeder::class);
            $userId = DB::table('users')->where('email', UserSeeder::EMAIL)->value('id');
        }
        if (! $userId) {
            throw new \RuntimeException('Set LOCAL_ADMIN_PASSWORD to create the development account before seeding development data.');
        }
        $this->call(HardwareSeeder::class);
        $hardwareId = HardwareSeeder::SOLAR_TRACKER_ID;
        $serial = self::SERIAL;
        // Downtown Los Angeles; also repair the previously unlocated local fixture.
        $coordinates = ['latitude' => self::LATITUDE, 'longitude' => self::LONGITUDE];
        if (! DB::table('device_registers')->where('serial_no', $serial)->exists()) {
            DB::table('device_registers')->insert($coordinates + [
                'user_id' => $userId, 'hardware_id' => $hardwareId, 'serial_no' => $serial,
                'sku' => 'SP1', 'alias' => 'Development Solar Tracker', 'order_no' => 'DEV-0001',
                'address_1' => '1 Development Way', 'city' => 'Testville', 'state' => 'CA',
                'country' => 'US', 'zip_code' => '90000', 'status_notification' => 0,
                'sms_notification' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
        } else {
            DB::table('device_registers')->where('serial_no', $serial)->update($coordinates);
        }
        $this->call(SolarTrackerLogSeeder::class);
    }
}
