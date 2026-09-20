<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Source of truth for device, delivery, weather, and telemetry tables that
 * predate Laravel migrations in deployed installations. Existing tables are
 * preserved; each create is guarded for non-destructive upgrades.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hardware')) {
            Schema::create('hardware', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('prefix')->nullable()->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('device_registers')) {
            Schema::create('device_registers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('hardware_id')->nullable()->constrained('hardware')->nullOnDelete();
                $table->string('serial_no')->index();
                $table->string('sku')->nullable();
                $table->string('alias')->nullable();
                $table->string('order_no')->nullable();
                $table->string('address_1')->nullable();
                $table->string('address_2')->nullable();
                $table->string('city')->nullable();
                $table->string('state')->nullable();
                $table->string('zip_code')->nullable();
                $table->string('country')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->boolean('status_notification')->default(false);
                $table->boolean('sms_notification')->default(false);
                $table->string('state_message')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'hardware_id']);
            });
        }

        if (!Schema::hasTable('weather')) {
            Schema::create('weather', function (Blueprint $table) {
                $table->id();
                $table->string('address1')->nullable();
                $table->string('address2')->nullable();
                $table->string('city')->nullable();
                $table->string('state')->nullable();
                $table->string('country')->nullable();
                $table->string('zipCode')->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('wind_speed_now', 8, 2)->nullable();
                $table->decimal('wind_speed_1D', 8, 2)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('geocode')) {
            Schema::create('geocode', function (Blueprint $table) {
                $table->id();
                $table->string('serial_no')->index();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->string('status')->nullable();
                $table->timestamps();
            });
        }

        foreach (['firmware_versions' => 'firmware', 'config_versions' => 'configuration'] as $tableName => $kind) {
            if (!Schema::hasTable($tableName)) {
                Schema::create($tableName, function (Blueprint $table) {
                    $table->id();
                    $table->string('version')->index();
                    $table->string('prefix')->nullable()->index();
                    $table->string('file_path');
                    $table->text('description')->nullable();
                    $table->timestamp('timestamp')->nullable();
                    $table->timestamps();
                });
            }
        }

        if (!Schema::hasTable('solar_tracker_remote_controls')) {
            Schema::create('solar_tracker_remote_controls', function (Blueprint $table) {
                $table->id();
                $table->string('serial_no')->index();
                $table->boolean('mode')->default(false);
                $table->integer('motor_speed')->default(0);
            });
        }

        if (!Schema::hasTable('solar_tracker_logs')) {
            Schema::create('solar_tracker_logs', function (Blueprint $table) {
                $table->id();
                $table->string('serial_no')->index();
                $table->decimal('ps1', 10, 3)->nullable();
                $table->decimal('ps2', 10, 3)->nullable();
                $table->decimal('ps_avg', 10, 3)->nullable();
                $table->decimal('pds', 10, 3)->nullable();
                $table->decimal('motor_speed', 10, 3)->nullable();
                $table->decimal('temp', 10, 3)->nullable();
                $table->integer('cts')->nullable();
                $table->string('state')->nullable();
                $table->timestamps();
                $table->index(['serial_no', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        // Existing legacy tables are intentionally preserved on rollback.
    }
};
