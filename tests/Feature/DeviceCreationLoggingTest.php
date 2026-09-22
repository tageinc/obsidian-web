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

class DeviceCreationLoggingTest extends TestCase
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
        $this->fields = [
            'user_id' => $user->id, 'name' => 'sentinel-private-name',
            'serial_no' => 'sentinel-private-serial', 'sku' => 'SP1', 'order_no' => '1234',
            'address_1' => 'sentinel-private-address', 'address_2' => 'sentinel-private-unit',
            'city' => 'sentinel-private-city', 'address_state' => 'CA', 'zip_code' => '99999',
            'latitude' => '12.345678', 'longitude' => '-98.765432', 'status_notification' => false,
        ];
    }

    public function test_successful_creation_logs_only_a_fixed_event_without_private_fields(): void
    {
        $this->postJson('/api/create-device', $this->fields)->assertCreated();
        $this->assertDatabaseHas('devices', ['serial_no' => $this->fields['serial_no']]);
        $this->assertPrivateFieldsNotLogged();
        $this->assertTrue($this->handler->hasInfo('device.creation_validated'));
    }

    public function test_database_failure_logs_no_query_bindings_or_private_creation_fields(): void
    {
        // The isolated in-memory test schema forces a real SQL exception containing bindings.
        Schema::drop('devices');
        $this->postJson('/api/create-device', $this->fields)->assertStatus(500)
            ->assertExactJson(['message' => 'An error occurred']);
        $this->assertPrivateFieldsNotLogged();
        $this->assertTrue($this->handler->hasError('device.creation_failed'));
        $this->assertStringNotContainsString('insert into', json_encode($this->handler->getRecords()));
    }

    public function test_validation_failure_retains_its_contract_without_logging_entered_fields(): void
    {
        unset($this->fields['sku']);
        $this->postJson('/api/create-device', $this->fields)->assertUnprocessable()
            ->assertJsonValidationErrors('sku');
        $this->assertPrivateFieldsNotLogged();
    }

    private function assertPrivateFieldsNotLogged(): void
    {
        $records = json_encode($this->handler->getRecords());
        foreach (['name', 'serial_no', 'address_1', 'address_2', 'city', 'latitude', 'longitude'] as $field) {
            $this->assertStringNotContainsString($this->fields[$field], $records);
        }
    }
}
