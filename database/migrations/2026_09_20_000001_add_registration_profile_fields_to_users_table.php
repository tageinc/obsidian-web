<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRegistrationProfileFieldsToUsersTable extends Migration
{
    /**
     * Add the fields required by the public registration form without
     * modifying or removing existing accounts.
     */
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'phone_number')) {
                $table->string('phone_number', 20)->nullable();
            }
            if (! Schema::hasColumn('users', 'address_1')) {
                $table->string('address_1')->nullable();
            }
            if (! Schema::hasColumn('users', 'address_2')) {
                $table->string('address_2')->nullable();
            }
            if (! Schema::hasColumn('users', 'city')) {
                $table->string('city')->nullable();
            }
            if (! Schema::hasColumn('users', 'state')) {
                $table->string('state')->nullable();
            }
            if (! Schema::hasColumn('users', 'zip_code')) {
                $table->string('zip_code')->nullable();
            }
            if (! Schema::hasColumn('users', 'country')) {
                $table->string('country')->nullable();
            }
        });
    }

    /**
     * Preserve profile data on rollback.
     */
    public function down(): void
    {
        // Intentionally no-op: profile data must not be removed by rollback.
    }
}
