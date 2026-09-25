<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('solar_tracker_logs')) {
            if (Schema::hasTable('device_logs')) {
                throw new RuntimeException('Both telemetry tables exist; reconcile them before migrating.');
            }
            Schema::rename('solar_tracker_logs', 'device_logs');
        }
        if (!Schema::hasColumn('device_logs', 'data')) {
            Schema::table('device_logs', function (Blueprint $table) {
                $table->json('data')->nullable();
            });
        }

        // Preserve additional deployed telemetry columns and original database values.
        $columns = array_values(array_diff(Schema::getColumnListing('device_logs'), [
            'id', 'serial_no', 'created_at', 'updated_at', 'data',
        ]));
        if (!$columns) {
            return;
        }
        DB::table('device_logs')->orderBy('id')->chunkById(500, function ($logs) use ($columns) {
            DB::transaction(function () use ($logs, $columns) {
                foreach ($logs as $log) {
                    $data = $log->data === null ? [] : json_decode($log->data, true, 512, JSON_THROW_ON_ERROR);
                    foreach ($columns as $column) {
                        $data[$column] = $log->{$column};
                    }
                    DB::table('device_logs')->where('id', $log->id)->update([
                        'data' => json_encode($data, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
                    ]);
                }
            });
        });

        // Use one ALTER on MySQL to avoid rebuilding a large telemetry table repeatedly.
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('device_logs', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
            return;
        }
        // Laravel 8 uses optional DBAL for drops; SQLite 3.35+ supports them natively.
        $grammar = DB::connection()->getQueryGrammar();
        foreach ($columns as $column) {
            DB::statement('ALTER TABLE '.$grammar->wrapTable('device_logs').' DROP COLUMN '.$grammar->wrap($column));
        }
    }

    public function down(): void
    {
        throw new RuntimeException('JSON telemetry cannot be safely reduced to legacy columns. Restore a pre-migration backup to roll back.');
    }
};
