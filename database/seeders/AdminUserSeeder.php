<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates the deployment administrator once, using deployment-only settings.
 * Existing accounts are never modified by this seeder.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_BOOTSTRAP_PASSWORD');

        if (! $email || ! $password) {
            $this->command?->warn('AdminUserSeeder skipped: set ADMIN_EMAIL and ADMIN_BOOTSTRAP_PASSWORD in the deployment environment.');

            return;
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_BOOTSTRAP_NAME', 'Obsidian Administrator'),
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        if (! $user->wasRecentlyCreated) {
            $this->command?->line('AdminUserSeeder skipped: the configured administrator already exists.');
        }
    }
}
