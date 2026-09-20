<?php
namespace Database\Factories;
use App\Models\License;
use Illuminate\Database\Eloquent\Factories\Factory;
class LicenseFactory extends Factory
{
    protected $model = License::class;
    public function definition(): array { return ['key' => (string) $this->faker->uuid, 'product_id' => null, 'company_id' => null, 'user_id' => null, 'device_id' => 0, 'order_id' => null, 'subscription_id' => null, 'active' => true]; }
}
