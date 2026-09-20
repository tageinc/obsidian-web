<?php
namespace Database\Factories;
use App\Models\SolarTrackerRemoteControl;
use Illuminate\Database\Eloquent\Factories\Factory;
class SolarTrackerRemoteControlFactory extends Factory
{
    protected $model = SolarTrackerRemoteControl::class;
    public function definition(): array { return ['serial_no' => $this->faker->numerify('2026########'), 'mode' => false, 'motor_speed' => 0]; }
}
