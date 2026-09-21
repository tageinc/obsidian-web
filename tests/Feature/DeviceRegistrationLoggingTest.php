<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Monolog\Handler\TestHandler;
use Tests\TestCase;

class DeviceRegistrationLoggingTest extends TestCase
{
    use RefreshDatabase;

    private $handler;
    private $fields;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new TestHandler();
        Log::swap(new Logger(new \Monolog\Logger('test', [$this->handler])));
        $user = User::create(['name' => 'Test owner', 'email' => 'device-test@example.test', 'password' => 'test-hash']);
        DB::table('hardware')->insert(['id' => 1, 'name' => 'Solar Tracker', 'prefix' => 'SP1']);
        $this->fields = [
            'hardware_id' => 1, 'user_id' => $user->id, 'alias' => 'sentinel-private-alias',
            'serial_no' => 'sentinel-private-serial', 'sku' => 'SP1', 'order_no' => '1234',
            'address_1' => 'sentinel-private-address', 'address_2' => 'sentinel-private-unit',
            'city' => 'sentinel-private-city', 'state' => 'CA', 'zip_code' => '99999',
            'latitude' => '12.345678', 'longitude' => '-98.765432', 'status_notification' => false,
        ];
    }

    public function test_successful_registration_logs_only_a_fixed_event_without_private_fields(): void
    {
        $this->postJson('/api/device-register', $this->fields)->assertCreated();
        $this->assertDatabaseHas('device_registers', ['serial_no' => $this->fields['serial_no']]);
        $this->assertPrivateFieldsNotLogged();
        $this->assertTrue($this->handler->hasInfo('device.registration_validated'));
    }

    public function test_database_failure_logs_no_query_bindings_or_private_registration_fields(): void
    {
        // The isolated in-memory test schema forces a real SQL exception containing bindings.
        Schema::drop('device_registers');
        $this->postJson('/api/device-register', $this->fields)->assertStatus(500)
            ->assertExactJson(['message' => 'An error occurred']);
        $this->assertPrivateFieldsNotLogged();
        $this->assertTrue($this->handler->hasError('device.registration_failed'));
        $this->assertStringNotContainsString('insert into', json_encode($this->handler->getRecords()));
    }

    public function test_validation_failure_retains_its_contract_without_logging_entered_fields(): void
    {
        unset($this->fields['hardware_id']);
        $this->postJson('/api/device-register', $this->fields)->assertUnprocessable()
            ->assertJsonValidationErrors('hardware_id');
        $this->assertPrivateFieldsNotLogged();
    }

    private function assertPrivateFieldsNotLogged(): void
    {
        $records = json_encode($this->handler->getRecords());
        foreach (['alias', 'serial_no', 'address_1', 'address_2', 'city', 'latitude', 'longitude'] as $field) {
            $this->assertStringNotContainsString($this->fields[$field], $records);
        }
    }
}
