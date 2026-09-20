<?php
namespace Database\Factories;
use App\Models\GeoCode;
use Illuminate\Database\Eloquent\Factories\Factory;
class GeoCodeFactory extends Factory
{
    protected $model = GeoCode::class;
    public function definition(): array { return ['serial_no' => $this->faker->numerify('2026########'), 'latitude' => $this->faker->latitude, 'longitude' => $this->faker->longitude, 'status' => 'online']; }
}
