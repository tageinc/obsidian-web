<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Seeds the active Solar Tracker hardware type required by device workflows. */
class HardwareSeeder extends Seeder
{
    public const SOLAR_TRACKER_ID = 1;
    public const SOLAR_TRACKER_PREFIX = 'SP1';

    public function run(): void
    {
        $atExpectedId = DB::table('hardware')->where('id', self::SOLAR_TRACKER_ID)->first();
        $atExpectedPrefix = DB::table('hardware')->where('prefix', self::SOLAR_TRACKER_PREFIX)->first();

        if (($atExpectedId && $atExpectedId->prefix !== self::SOLAR_TRACKER_PREFIX)
            || ($atExpectedPrefix && (int) $atExpectedPrefix->id !== self::SOLAR_TRACKER_ID)) {
            $this->command?->error('HardwareSeeder skipped: the Solar Tracker hardware identity conflicts with existing data.');

            return;
        }

        DB::table('hardware')->updateOrInsert(
            ['id' => self::SOLAR_TRACKER_ID],
            [
                'name' => 'Solar Tracker',
                'prefix' => self::SOLAR_TRACKER_PREFIX,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
