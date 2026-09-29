<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddConfigurationSchemaMetadata extends Migration
{
    public function up(): void
    {
        Schema::table('config_versions', function (Blueprint $table) {
            // Null means an existing release has never passed family-specific validation.
            $table->string('device_family', 64)->nullable();
            $table->unsignedInteger('schema_version')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('config_versions', function (Blueprint $table) {
            $table->dropColumn(['device_family', 'schema_version']);
        });
    }
}
