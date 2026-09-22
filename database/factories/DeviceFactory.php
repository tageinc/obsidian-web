<?php
namespace Database\Factories;
use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;
class DeviceFactory extends Factory
{
    protected $model = Device::class;
    public function definition(): array { return ['user_id' => null, 'serial_no' => $this->faker->unique()->numerify('2026########'), 'sku' => 'SP1', 'alias' => $this->faker->words(2, true), 'order_no' => 'DEV-'.$this->faker->unique()->numerify('#####'), 'address_1' => $this->faker->streetAddress, 'city' => $this->faker->city, 'state' => $this->faker->stateAbbr, 'country' => 'US', 'zip_code' => $this->faker->postcode, 'latitude' => $this->faker->latitude, 'longitude' => $this->faker->longitude, 'status_notification' => false, 'sms_notification' => false]; }
}
