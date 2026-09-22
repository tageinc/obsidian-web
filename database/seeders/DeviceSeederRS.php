<?php

namespace Database\Seeders;

use App\Models\User;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/** Import recorded sensor history for an isolated local real-scenario device. */
class DeviceSeederRS extends Seeder
{
    public const SERIAL = 'RS0000000001';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('DeviceSeederRS is restricted to local and testing environments.');
            return;
        }

        // Parse everything first: a bad/missing file must not erase existing history.
        $rows = $this->readCsv((string) config('device-seeder-rs.csv_path'));
        DB::transaction(function () use ($rows) {
            $user = User::firstOrCreate(['email' => 'andre.troncoso@tezca.net'], [
                'name' => 'Andre Troncoso',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]);
            $device = DB::table('devices')->where('serial_no', self::SERIAL)->first();
            if ($device && ((int) $device->user_id !== (int) $user->id || $device->order_no !== 'DEV-RS')) {
                throw new RuntimeException('The DeviceSeederRS serial is already used by another device.');
            }
            if (! $device) {
                DB::table('devices')->insert([
                    'serial_no' => self::SERIAL, 'user_id' => $user->id,
                    'name' => 'Real Scenario', 'sku' => 'SP1', 'order_no' => 'DEV-RS',
                    'latitude' => 34.052235, 'longitude' => -118.243683,
                    'city' => 'Los Angeles', 'address_state' => 'CA', 'country' => 'US',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            DB::table('geocode')->updateOrInsert(['serial_no' => self::SERIAL], [
                'latitude' => $device->latitude ?? 34.052235,
                'longitude' => $device->longitude ?? -118.243683,
                'status' => null, 'created_at' => now(), 'updated_at' => now(),
            ]);
            // Replace only this seeder's history, keeping duplicate timestamps in the CSV.
            DB::table('solar_tracker_logs')->where('serial_no', self::SERIAL)->delete();
            foreach (array_chunk($rows, 250) as $chunk) {
                DB::table('solar_tracker_logs')->insert($chunk);
            }
        });
        $this->command?->info(count($rows).' recorded readings imported for Real Scenario.');
    }

    private function readCsv(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('Sensor CSV not found/readable: '.$path.'. Set DEVICE_SEEDER_RS_CSV to its path.');
        }
        $file = fopen($path, 'r');
        if ($file === false) {
            throw new RuntimeException('Cannot open sensor CSV: '.$path);
        }
        try {
            $header = fgetcsv($file, 0, ',', '"', '');
            if ($header === false) {
                throw new RuntimeException('Sensor CSV is empty.');
            }
            $header = array_map(fn ($value) => strtolower(trim($value ?? '')), $header);
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            $header = array_map(fn ($value) => ['temperature' => 'temp', 'timestamp' => 'updated_at'][$value] ?? $value, $header);
            if (count(array_unique($header)) !== count($header)) {
                throw new RuntimeException('Sensor CSV has duplicate columns.');
            }
            foreach (['updated_at', 'motor_speed', 'temp', 'ps1', 'ps2'] as $column) {
                if (! in_array($column, $header, true)) {
                    throw new RuntimeException('Sensor CSV missing column: '.$column);
                }
            }
            $rows = [];
            $line = 1;
            while (($values = fgetcsv($file, 0, ',', '"', '')) !== false) {
                $line++;
                if ($values === [null]) continue;
                if (count($values) !== count($header)) {
                    throw new RuntimeException("Sensor CSV row {$line} has the wrong number of columns.");
                }
                $data = array_combine($header, $values);
                $rawTime = trim($data['updated_at']);
                if (! preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})?$/', $rawTime)) {
                    throw new RuntimeException("Sensor CSV row {$line} requires a timestamp with seconds (ISO 8601 or UTC YYYY-MM-DD HH:MM:SS).");
                }
                $time = new DateTimeImmutable($rawTime, new DateTimeZone('UTC'));
                $errors = DateTimeImmutable::getLastErrors();
                if ($errors && ($errors['warning_count'] || $errors['error_count'])) {
                    throw new RuntimeException("Sensor CSV row {$line} has an invalid timestamp.");
                }
                $row = ['serial_no' => self::SERIAL];
                foreach (['motor_speed', 'temp', 'ps1', 'ps2', 'ps_avg', 'pds'] as $column) {
                    if (! array_key_exists($column, $data)) continue;
                    $value = trim($data[$column]);
                    if ($value !== '' && (! is_numeric($value) || ! is_finite((float) $value) || abs((float) $value) > 9999999.999)) {
                        throw new RuntimeException("Sensor CSV row {$line} has an invalid {$column} reading.");
                    }
                    $row[$column] = $value === '' ? null : $value;
                }
                if (isset($data['cts']) && trim($data['cts']) !== '') {
                    if (! preg_match('/^-?\d+$/', trim($data['cts'])) || abs((float) $data['cts']) > 2147483647) {
                        throw new RuntimeException("Sensor CSV row {$line} has an invalid cts reading.");
                    }
                    $row['cts'] = (int) $data['cts'];
                } else {
                    $row['cts'] = null;
                }
                $row['state'] = isset($data['state']) && trim($data['state']) !== '' ? trim($data['state']) : null;
                if (strlen($row['state'] ?? '') > 255) {
                    throw new RuntimeException("Sensor CSV row {$line} has an invalid state.");
                }
                $timestamp = $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
                $row['created_at'] = $row['updated_at'] = $timestamp;
                // Do not invent values for measurements absent from the source CSV.
                $rows[] = $row;
            }
            if (! $rows) throw new RuntimeException('Sensor CSV contains no readings.');
            return $rows;
        } finally {
            fclose($file);
        }
    }
}
