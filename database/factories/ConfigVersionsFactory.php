<?php
namespace Database\Factories;
use App\Models\ConfigVersions;
use Illuminate\Database\Eloquent\Factories\Factory;
class ConfigVersionsFactory extends Factory
{
    protected $model = ConfigVersions::class;
    public function definition(): array { return ['version' => $this->faker->numberBetween(1, 1000), 'prefix' => $this->faker->numerify('2026######'), 'file_path' => 'config/local.json', 'description' => 'Development configuration']; }
}
