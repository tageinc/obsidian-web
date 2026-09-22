<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE devices RENAME COLUMN alias TO name');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE devices RENAME COLUMN name TO alias');
    }
};
