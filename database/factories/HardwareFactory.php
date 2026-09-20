<?php
namespace Database\Factories;
use App\Models\Hardware;
use Illuminate\Database\Eloquent\Factories\Factory;
class HardwareFactory extends Factory
{
    protected $model = Hardware::class;
    public function definition(): array { return ['name' => $this->faker->unique()->words(2, true), 'prefix' => $this->faker->unique()->slug(2)]; }
}
