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
            UserSeeder::class,
            DevelopmentDataSeeder::class,
        ]);
    }
}
