<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Billing, license, product, and software fixtures are retired. Development
 * seed data contains only active application entities.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            UserSeeder::class,
            DevelopmentDataSeeder::class,
        ]);
    }
}
