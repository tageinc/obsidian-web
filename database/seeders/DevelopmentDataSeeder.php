<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Deterministic, synthetic local data. Never run this against production. */
class DevelopmentDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('DevelopmentDataSeeder is restricted to local and testing environments.');

            return;
        }

        $userId = DB::table('users')->insertGetId([
            'name' => 'Obsidian Development User',
            'email' => 'developer@example.test',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->call(HardwareSeeder::class);
        $hardwareId = HardwareSeeder::SOLAR_TRACKER_ID;
        $serial = '202600000001';
        DB::table('device_registers')->insert([
            'user_id' => $userId, 'hardware_id' => $hardwareId, 'serial_no' => $serial,
            'sku' => 'SP1', 'alias' => 'Development Solar Tracker', 'order_no' => 'DEV-0001',
            'address_1' => '1 Development Way', 'city' => 'Testville', 'state' => 'CA',
            'country' => 'US', 'zip_code' => '90000', 'status_notification' => 0,
            'sms_notification' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('solar_tracker_logs')->insert([
            'serial_no' => $serial, 'ps1' => 0, 'ps2' => 0, 'ps_avg' => 0, 'pds' => 0,
            'motor_speed' => 0, 'temp' => 20, 'cts' => 0, 'state' => 'online',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
