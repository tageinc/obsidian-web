<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Both supported databases (MySQL 8 and SQLite) support native renaming.
        DB::statement('ALTER TABLE devices RENAME COLUMN state TO address_state');
        Schema::table('devices', function (Blueprint $table) {
            $table->enum('state', ['active', 'archived'])->default('active')->index();
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropIndex(['state']);
        });
        DB::statement('ALTER TABLE devices DROP COLUMN state');
        DB::statement('ALTER TABLE devices RENAME COLUMN address_state TO state');
    }
};
