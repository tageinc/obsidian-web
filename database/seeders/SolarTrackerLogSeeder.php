<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Randomized telemetry for the dedicated local demo device only. */
class SolarTrackerLogSeeder extends Seeder
{
    public const DAYS = 7;
    public const INTERVAL_MINUTES = 5;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('SolarTrackerLogSeeder is restricted to local and testing environments.');
            return;
        }

        $serial = '202600000001';
        if (! DB::table('devices')->where('serial_no', $serial)->exists()) {
            throw new \RuntimeException('Run DevelopmentDataSeeder first to create the local Solar Tracker device.');
        }

        // UTC intervals keep elapsed time correct through daylight-saving changes.
        $end = CarbonImmutable::now('UTC')->startOfMinute();
        $end = $end->subMinutes($end->minute % self::INTERVAL_MINUTES);
        $start = $end->subDays(self::DAYS);
        $existing = DB::table('solar_tracker_logs')->where('serial_no', $serial)
            ->whereBetween('updated_at', [$start, $end])->pluck('updated_at')
            ->mapWithKeys(fn ($timestamp) => [(string) $timestamp => true])->all();

        $rows = [];
        for ($time = $start; $time <= $end; $time = $time->addMinutes(self::INTERVAL_MINUTES)) {
            $timestamp = $time->format('Y-m-d H:i:s');
            if (isset($existing[$timestamp])) {
                continue;
            }

            $local = $time->setTimezone('America/Los_Angeles');
            $hour = $local->hour + $local->minute / 60;
            $daylight = max(0, sin(($hour - 6) * M_PI / 12));
            $cloudFactor = random_int(65, 100) / 100;
            $light = 900 * $daylight * $cloudFactor;
            $ps1 = round(max(0, $light + random_int(-15, 15)), 3);
            $ps2 = round(max(0, $light + random_int(-15, 15)), 3);
            $rows[] = [
                'serial_no' => $serial,
                'ps1' => $ps1,
                'ps2' => $ps2,
                'ps_avg' => round(($ps1 + $ps2) / 2, 3),
                'pds' => round($ps1 - $ps2, 3),
                'temp' => round(19 + 8 * sin(($hour - 9) * M_PI / 12) + random_int(-20, 20) / 10, 3),
                'motor_speed' => random_int(0, 3) === 0 ? 0 : random_int(-35, 35),
                'cts' => random_int(0, 1),
                'state' => 'online',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        DB::transaction(function () use ($rows) {
            foreach (array_chunk($rows, 250) as $chunk) {
                DB::table('solar_tracker_logs')->insert($chunk);
            }
        });
        $this->command?->info(count($rows).' randomized readings added to the development Solar Tracker (7 days, every 5 minutes).');
    }
}
