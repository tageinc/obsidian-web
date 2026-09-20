<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Billing and license fixtures were retired. Historical billing tables remain
 * available for audit, but new seed runs never create orders or licenses.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Application seed data is provisioned by environment-specific tooling.
    }
}
