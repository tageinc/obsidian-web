<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('device_registers')) {
            if (Schema::hasTable('devices')) {
                throw new RuntimeException('Both device tables exist; reconcile them before migrating.');
            }
            Schema::rename('device_registers', 'devices');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('devices') && ! Schema::hasTable('device_registers')) {
            Schema::rename('devices', 'device_registers');
        }
    }
};
