<?php
namespace Database\Factories;
use App\Models\Api\SolarTrackerLog;
use Illuminate\Database\Eloquent\Factories\Factory;
class SolarTrackerLogFactory extends Factory
{
    protected $model = SolarTrackerLog::class;
    public function definition(): array { return ['serial_no' => $this->faker->numerify('2026########'), 'ps1' => 0, 'ps2' => 0, 'ps_avg' => 0, 'pds' => 0, 'motor_speed' => 0, 'temp' => 20, 'cts' => 0, 'state' => 'online']; }
}
