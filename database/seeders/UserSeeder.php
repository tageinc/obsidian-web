<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

/** Creates the documented local development administrator account. */
class UserSeeder extends Seeder
{
    public const EMAIL = 'andre.troncoso@tezca.net';

    public function run(): void
    {
        if (!app()->environment(['local', 'testing'])) {
            $this->command?->warn('UserSeeder is restricted to local and testing environments.');
            return;
        }

        $password = env('LOCAL_ADMIN_PASSWORD');
        if (!$password) {
            $this->command?->warn('Set LOCAL_ADMIN_PASSWORD in the ignored local .env before seeding.');
            return;
        }

        DB::table('users')->updateOrInsert(
            ['email' => self::EMAIL],
            [
                'name' => 'Andre Troncoso',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
