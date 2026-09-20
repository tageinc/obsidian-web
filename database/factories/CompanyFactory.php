<?php
namespace Database\Factories;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
class CompanyFactory extends Factory
{
    protected $model = Company::class;
    public function definition(): array
    {
        return ['name' => $this->faker->company, 'address' => $this->faker->streetAddress, 'city' => $this->faker->city, 'state' => $this->faker->stateAbbr, 'country' => 'US', 'zip_code' => $this->faker->postcode, 'phone_office' => $this->faker->phoneNumber, 'url' => $this->faker->unique()->url, 'admin_id' => 1];
    }
}
