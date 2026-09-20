<?php
namespace Database\Factories;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
class ProductFactory extends Factory
{
    protected $model = Product::class;
    public function definition(): array { return ['name' => $this->faker->unique()->words(2, true), 'slug' => $this->faker->unique()->slug, 'duration_days' => 30, 'price' => 0, 'quantity' => 1, 'software_id' => null]; }
}
