<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates the deployment developer once, using deployment-only settings.
 * Existing accounts are never modified by this seeder.
 */
class DeveloperSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('app.developer_email');
        $password = env('DEVELOPER_BOOTSTRAP_PASSWORD');

        if (! is_string($email) || trim($email) === '' || ! $password) {
            $this->command?->warn('DeveloperSeeder skipped: set DEVELOPER_EMAIL and DEVELOPER_BOOTSTRAP_PASSWORD in the deployment environment.');

            return;
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $email === 'andre.troncoso@tezca.net'
                    ? 'Andre Troncoso'
                    : env('DEVELOPER_BOOTSTRAP_NAME', 'Obsidian Developer'),
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ] + ($email === 'andre.troncoso@tezca.net' ? [
                'phone_number' => '19495296737',
                'address_1' => '1605 E 4TH ST',
                'address_2' => '200',
                'city' => 'SANTA ANA',
                'state' => 'CA',
                'zip_code' => '92701',
                'country' => 'United States',
            ] : [])
        );

        if (! $user->wasRecentlyCreated) {
            $this->command?->line('DeveloperSeeder skipped: the configured developer already exists.');
        }
    }
}
