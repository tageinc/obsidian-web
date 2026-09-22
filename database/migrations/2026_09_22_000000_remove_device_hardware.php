<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('devices', 'hardware_id')) {
            if (DB::getDriverName() === 'sqlite') {
                $this->removeSqliteHardware();
            } else {
                // Legacy installations may use a different foreign key name, or none.
                if (DB::getDriverName() === 'mysql') {
                    $keys = DB::select(
                        'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
                         AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
                        ['devices', 'hardware_id']
                    );
                    foreach ($keys as $key) {
                        Schema::table('devices', function (Blueprint $table) use ($key) {
                            $table->dropForeign($key->CONSTRAINT_NAME);
                        });
                    }
                }
                Schema::table('devices', function (Blueprint $table) {
                    $table->dropColumn('hardware_id');
                });
            }
        }

        Schema::dropIfExists('hardware');
    }

    private function removeSqliteHardware(): void
    {
        // Rebuild from the stored definition to retain other legacy columns and constraints.
        // SQLite cannot drop a column while its table-level foreign key still exists.
        $definition = DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'devices'")->sql;
        $definition = preg_replace('/([,(])\s*["`\[]?hardware_id["`\]]?\s+[^,)]*/i', '$1', $definition);
        $definition = preg_replace('/,\s*(?:constraint\s+\S+\s+)?foreign key\s*\(\s*["`\[]?hardware_id["`\]]?\s*\)\s*references\s+[^,]+/i', '', $definition);
        $definition = preg_replace('/,\s*,/', ',', $definition);
        $definition = preg_replace('/,\s*\)/', ')', $definition);
        // A removed final foreign key may have consumed the closing table parenthesis.
        $definition = rtrim($definition, "; \t\n\r");
        if (substr_count($definition, '(') > substr_count($definition, ')')) {
            $definition .= ')';
        }
        if (stripos($definition, 'hardware_id') !== false) {
            throw new RuntimeException('Unrecognized SQLite device schema; hardware migration was not applied.');
        }
        $definition = preg_replace('/^(CREATE TABLE\s+)["`\[]?devices["`\]]?/i', '$1"devices_without_hardware"', $definition);
        $objects = DB::select("SELECT sql FROM sqlite_master WHERE tbl_name = 'devices' AND type IN ('index', 'trigger') AND sql IS NOT NULL");
        $columns = array_diff(Schema::getColumnListing('devices'), ['hardware_id']);
        $quoted = implode(', ', array_map(fn ($name) => '"'.str_replace('"', '""', $name).'"', $columns));

        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function () use ($definition, $quoted, $objects) {
                DB::statement($definition);
                DB::statement("INSERT INTO devices_without_hardware ({$quoted}) SELECT {$quoted} FROM devices");
                Schema::drop('devices');
                Schema::rename('devices_without_hardware', 'devices');
                foreach ($objects as $object) {
                    if (stripos($object->sql, 'hardware_id') === false) {
                        DB::statement($object->sql);
                    }
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    public function down(): void
    {
        // Removed hardware records and assignments cannot be reconstructed.
    }
};
