<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Database\Seeders\DevelopmentDataSeeder;
use Database\Seeders\SolarTrackerLogSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SolarTrackerLogSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_development_seed_produces_varied_history_and_can_be_repeated(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-02 12:03:00', 'UTC'));
        DB::table('users')->insert([
            'name' => 'Andre', 'email' => UserSeeder::EMAIL, 'password' => 'existing-hash',
        ]);
        $this->seed(DevelopmentDataSeeder::class);
        DB::table('solar_tracker_logs')->where('serial_no', '202600000001')->delete();
        $this->seed(SolarTrackerLogSeeder::class);
        $logs = DB::table('solar_tracker_logs')->where('serial_no', '202600000001')->orderBy('updated_at')->get();
        $this->assertCount(2017, $logs);
        $this->assertSame('2026-10-26 12:00:00', $logs->first()->updated_at);
        $this->assertSame('2026-11-02 12:00:00', $logs->last()->updated_at);
        foreach (['temp', 'ps_avg', 'motor_speed'] as $metric) {
            $this->assertGreaterThan(5, $logs->pluck($metric)->unique()->count());
        }
        foreach ($logs as $index => $log) {
            $this->assertEqualsWithDelta(($log->ps1 + $log->ps2) / 2, $log->ps_avg, 0.001);
            $this->assertLessThanOrEqual(100, abs($log->motor_speed));
            if ($index) {
                $this->assertSame(300, strtotime($log->updated_at.' UTC') - strtotime($logs[$index - 1]->updated_at.' UTC'));
            }
        }
        $this->seed(SolarTrackerLogSeeder::class);
        $this->assertSame(2017, DB::table('solar_tracker_logs')->where('serial_no', '202600000001')->count());
        $this->assertSame(15, DB::table('devices')->count());
        $this->assertEquals($logs->first(), DB::table('solar_tracker_logs')->where('serial_no', '202600000001')->orderBy('updated_at')->first());
        $this->assertSame('existing-hash', DB::table('users')->value('password'));
    }

    public function test_telemetry_seeding_is_disabled_in_production(): void
    {
        $this->app->instance('env', 'production');
        $this->artisan('db:seed', ['--class' => SolarTrackerLogSeeder::class, '--force' => true])->assertExitCode(0);
        $this->assertSame(0, DB::table('solar_tracker_logs')->count());
    }
}
