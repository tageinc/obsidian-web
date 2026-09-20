<?php
namespace Database\Factories;
use App\Models\FirmwareVersions;
use Illuminate\Database\Eloquent\Factories\Factory;
class FirmwareVersionsFactory extends Factory
{
    protected $model = FirmwareVersions::class;
    public function definition(): array { return ['version' => $this->faker->numberBetween(1, 1000), 'prefix' => $this->faker->numerify('2026######'), 'file_path' => 'firmware/local.bin', 'description' => 'Development firmware']; }
}
