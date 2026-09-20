<?php
namespace Database\Factories;
use App\Models\Weather;
use Illuminate\Database\Eloquent\Factories\Factory;
class WeatherFactory extends Factory
{
    protected $model = Weather::class;
    public function definition(): array { return ['city' => $this->faker->city, 'state' => $this->faker->stateAbbr, 'country' => 'US', 'zipCode' => $this->faker->postcode, 'latitude' => $this->faker->latitude, 'longitude' => $this->faker->longitude, 'wind_speed_now' => 0, 'wind_speed_1D' => 0]; }
}
