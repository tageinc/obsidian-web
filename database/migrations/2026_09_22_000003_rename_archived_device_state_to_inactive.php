<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE devices MODIFY state ENUM('active', 'archived', 'inactive') NOT NULL DEFAULT 'active'");
        }

        DB::table('devices')->where('state', 'archived')->update(['state' => 'inactive']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE devices MODIFY state ENUM('active', 'inactive') NOT NULL DEFAULT 'active'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE devices MODIFY state ENUM('active', 'archived', 'inactive') NOT NULL DEFAULT 'active'");
        }

        DB::table('devices')->where('state', 'inactive')->update(['state' => 'archived']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE devices MODIFY state ENUM('active', 'archived') NOT NULL DEFAULT 'active'");
        }
    }
};
