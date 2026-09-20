<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Api\SolarTrackerLog;

class AuditDeviceTelemetry extends Command
{
    protected $signature = 'device:audit-telemetry';
    protected $description = 'Read-only verification of telemetry tables, columns and serial-leading indexes';

    public function handle()
    {
        $failed = false;
        foreach ([new SolarTrackerLog] as $model) {
            $table = $model->getTable();
            try {
                if (!Schema::hasTable($table)) {
                    $this->error($table.': missing table');
                    $failed = true;
                    continue;
                }
                $required = array_merge(['id', 'created_at', 'updated_at'], $model->getFillable());
                $missing = array_diff($required, Schema::getColumnListing($table));
                $indexes = $this->indexes($table);
                $serialIndex = false;
                $recencyIndex = false;
                foreach ($indexes as $columns) {
                    $serialIndex = $serialIndex || ($columns[0] ?? null) === 'serial_no';
                    $recencyIndex = $recencyIndex || array_slice($columns, 0, 2) === ['serial_no', 'created_at'];
                }
                $this->line($table.': missing_columns='.json_encode(array_values($missing))
                    .' serial_leading_index='.($serialIndex ? 'yes' : 'NO')
                    .' serial_created_at_index='.($recencyIndex ? 'yes' : 'no'));
                $failed = $failed || count($missing) > 0 || !$serialIndex;
            } catch (\Throwable $e) {
                // Exception messages can contain connection details, SQL and credentials.
                $this->error($table.': verification unavailable ('.class_basename($e).')');
                $failed = true;
            }
        }
        return $failed ? 1 : 0;
    }

    private function indexes(string $table): array
    {
        $connection = DB::connection();
        $physical = $connection->getTablePrefix().$table;
        $indexes = [];
        if ($connection->getDriverName() === 'mysql') {
            $quoted = '`'.str_replace('`', '``', $physical).'`';
            foreach ($connection->select('SHOW INDEX FROM '.$quoted) as $row) {
                $indexes[$row->Key_name][(int) $row->Seq_in_index] = $row->Column_name;
            }
            foreach ($indexes as &$columns) {
                ksort($columns);
                $columns = array_values($columns);
            }
        } elseif ($connection->getDriverName() === 'sqlite') {
            $quoted = "'".str_replace("'", "''", $physical)."'";
            foreach ($connection->select('PRAGMA index_list('.$quoted.')') as $index) {
                if ($index->partial) {
                    continue;
                }
                $name = "'".str_replace("'", "''", $index->name)."'";
                $indexes[$index->name] = array_map(function ($row) { return $row->name; },
                    $connection->select('PRAGMA index_info('.$name.')'));
            }
        } else {
            throw new \RuntimeException('Unsupported metadata driver');
        }
        return array_values($indexes);
    }
}
