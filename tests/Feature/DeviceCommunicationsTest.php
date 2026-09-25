<?php

namespace Tests\Feature;

use App\Mail\LowVoltageMail;
use App\Jobs\SendAppUpdateMail;
use App\Models\Api\DeviceLog;
use App\Models\Device;
use Carbon\Carbon;
use Database\Seeders\DeveloperSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeviceCommunicationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-20 12:00:00'));
        config(['devices.telemetry_freshness_seconds' => 600]);

        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('state')->default('active');
            $table->string('serial_no');
            $table->integer('user_id')->nullable();
            $table->boolean('status_notification')->default(false);
            $table->boolean('sms_notification')->default(false);
            $table->string('address_1')->nullable();
            $table->timestamps();
        });
        Schema::create('solar_tracker_logs', function (Blueprint $table) {
            $table->id();
            $table->string('serial_no')->index();
            $table->float('ps1')->nullable();
            $table->float('ps2')->nullable();
            $table->float('ps_avg')->nullable();
            $table->float('pds')->nullable();
            $table->float('motor_speed')->nullable();
            $table->float('temp')->nullable();
            $table->integer('cts')->nullable();
            $table->string('state')->nullable();
            $table->timestamps();
        });
        (require database_path('migrations/2026_09_24_000000_convert_solar_tracker_logs_to_device_logs.php'))->up();
        Schema::create('geocode', function (Blueprint $table) {
            $table->id();
            $table->string('serial_no');
            $table->string('status');
            $table->float('latitude')->nullable();
            $table->float('longitude')->nullable();
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });
        Schema::create('hardware', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('prefix')->unique();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_solar_telemetry_is_accepted_and_invalid_payload_is_rejected_without_a_write(): void
    {
        DB::table('devices')->insert(['serial_no' => 'solar']);

        $this->post('/api/log', ['serial_no' => 'solar', 'data' => '{"ps1":0,"state":"online"}'])
            ->assertOk()->assertExactJson(['msg' => 'success']);
        $this->postJson('/api/log', ['serial_no' => 'solar', 'data' => '{}'])->assertStatus(422);

        $this->assertSame(1, DeviceLog::count());
    }

    public function test_json_telemetry_preserves_extensions_and_legacy_status_fields(): void
    {
        DB::table('devices')->insert(['serial_no' => 'solar']);
        $payload = ['ps1' => '0', 'temp' => null, 'state' => 'online', 'extension' => ['voltage' => 12.34567], 'firmware_version' => '001.020', 'config_version' => '003.004'];
        $this->postJson('/api/log', ['serial_no' => 'solar', 'data' => json_encode($payload)])
            ->assertOk()->assertExactJson(['msg' => 'success']);
        $log = DeviceLog::firstOrFail();
        $this->assertEquals(0, $log->data['ps1']);
        $this->assertNull($log->data['temp']);
        $this->assertSame($payload['extension'], $log->data['extension']);
        $this->assertSame('001.020', $log->data['firmware_version']);
        $this->assertSame('003.004', $log->data['config_version']);
        $this->assertSame('online', $log->state);
        $response = app(\App\Http\Controllers\ViewDeviceController::class)->getLatestStatusJson('solar');
        $this->assertSame('online', $response->getData(true)['latestStatus']['state']);
        $this->assertEquals(0, $response->getData(true)['latestStatus']['ps1']);
        $this->artisan('device:audit-telemetry')->assertExitCode(0);
    }

    public function test_device_payload_preserves_firmware_and_zero_config_version(): void
    {
        DB::table('devices')->insert(['serial_no' => 'solar']);
        $payload = [
            'ps1' => '65.200000', 'ps2' => '68.500000', 'ps_avg' => '66.850000',
            'pds' => '3.300000', 'motor_speed' => '5.511000', 'temp' => '28.400000',
            'cts' => '0', 'state' => 'solar track',
            'firmware_version' => '43', 'config_version' => '0',
        ];

        $this->postJson('/api/log', ['serial_no' => 'solar', 'data' => json_encode($payload)])
            ->assertOk()->assertExactJson(['msg' => 'success']);

        $stored = json_decode(DB::table('device_logs')->value('data'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame([
            'ps1' => 65.2, 'ps2' => 68.5, 'ps_avg' => 66.85,
            'pds' => 3.3, 'motor_speed' => 5.511, 'temp' => 28.4,
            'cts' => 0, 'state' => 'solar track',
            'firmware_version' => '43', 'config_version' => '0',
        ], $stored);
    }

    public function test_retired_energy_monitor_telemetry_is_not_written(): void
    {
        DB::table('devices')->insert(['serial_no' => 'retired']);

        $this->postJson('/api/log', ['serial_no' => 'retired', 'data' => '{"v_batt":12.5}'])
            ->assertStatus(422)->assertExactJson(['msg' => 'invalid telemetry']);

        $this->assertSame(0, DeviceLog::count());
    }

    public function test_billing_and_license_routes_are_removed_and_device_access_has_no_license_gate(): void
    {
        $this->assertFalse(Route::has('purchase'));
        $this->assertFalse(Route::has('subscription-manager'));
        $this->assertFalse(Route::has('purchase-checkout'));
        $this->assertFileDoesNotExist(base_path('app/Models/Order.php'));
        $this->assertFileDoesNotExist(base_path('app/Models/License.php'));
        $this->assertFileDoesNotExist(base_path('app/Models/Product.php'));
        $this->assertFileDoesNotExist(base_path('app/Http/Middleware/BillingMiddleware.php'));
        $this->assertFileDoesNotExist(base_path('database/migrations/2020_10_26_221110_create_software_table.php'));
        $this->assertFileDoesNotExist(base_path('database/migrations/2020_10_27_051838_create_products_table.php'));
        $this->assertFileDoesNotExist(base_path('database/migrations/2021_05_18_032407_create_orders_table.php'));
        $this->assertFileDoesNotExist(base_path('database/migrations/2021_05_18_032408_create_licenses_table.php'));
        $this->assertFileDoesNotExist(base_path('database/migrations/2021_05_18_033517_create_order_product_table.php'));
        $this->assertFileDoesNotExist(base_path('database/migrations/2021_06_16_154641_create_connections_table.php'));
        $this->assertStringNotContainsString('authorizenet', file_get_contents(base_path('composer.json')));
    }

    public function test_developer_user_seeder_creates_the_configured_account_once(): void
    {
        config(['app.developer_email' => 'developer@example.test']);
        putenv('DEVELOPER_BOOTSTRAP_PASSWORD=seed-password');

        try {
            $this->seed(DeveloperSeeder::class);
            $this->seed(DeveloperSeeder::class);

            $admin = DB::table('users')->where('email', 'developer@example.test')->first();
            $this->assertNotNull($admin);
            $this->assertTrue(Hash::check('seed-password', $admin->password));
            $this->assertSame(1, DB::table('users')->where('email', 'developer@example.test')->count());
        } finally {
            putenv('DEVELOPER_BOOTSTRAP_PASSWORD');
        }
    }

    public function test_developer_seeder_includes_and_preserves_the_requested_profile(): void
    {
        require_once database_path('migrations/2026_09_20_000001_add_registration_profile_fields_to_users_table.php');
        (new \AddRegistrationProfileFieldsToUsersTable())->up();
        config(['app.developer_email' => 'andre.troncoso@tezca.net']);
        putenv('DEVELOPER_BOOTSTRAP_PASSWORD=seed-password');
        try {
            $this->seed(DeveloperSeeder::class);
            $this->assertDatabaseHas('users', [
                'email' => 'andre.troncoso@tezca.net', 'name' => 'Andre Troncoso',
                'phone_number' => '19495296737', 'address_1' => '1605 E 4TH ST',
                'address_2' => '200', 'city' => 'SANTA ANA', 'state' => 'CA',
                'zip_code' => '92701', 'country' => 'United States',
            ]);
            $user = \App\Models\User::where('email', 'andre.troncoso@tezca.net')->firstOrFail();
            $password = $user->password;
            $user->update(['address_2' => 'Updated suite']);
            putenv('DEVELOPER_BOOTSTRAP_PASSWORD=different-password');
            $this->seed(DeveloperSeeder::class);
            $this->assertSame($password, $user->fresh()->password);
            $this->assertSame('Updated suite', $user->fresh()->address_2);
            $this->assertDatabaseCount('users', 1);
        } finally {
            putenv('DEVELOPER_BOOTSTRAP_PASSWORD');
        }
    }

    public function test_developer_seeder_does_not_use_old_email_policy_when_new_setting_is_missing(): void
    {
        config(['app.developer_email' => null, 'app.admin_email' => 'old-admin@example.test']);
        putenv('DEVELOPER_BOOTSTRAP_PASSWORD=seed-password');
        try {
            $this->seed(DeveloperSeeder::class);
            $this->assertDatabaseCount('users', 0);
        } finally {
            putenv('DEVELOPER_BOOTSTRAP_PASSWORD');
        }
    }

    public function test_status_command_preserves_email_notifications_without_sms_or_twilio(): void
    {
        Mail::fake();
        Queue::fake();
        DB::table('users')->insert(['id' => 1, 'name' => 'Owner', 'email' => 'owner@example.test']);
        DB::table('devices')->insert([
            'serial_no' => 'solar', 'user_id' => 1,
            'status_notification' => true, 'sms_notification' => true, 'address_1' => '1 Main St',
        ]);
        DB::table('geocode')->insert(['serial_no' => 'solar', 'status' => 'offline', 'latitude' => 1, 'longitude' => 2]);
        DeviceLog::create(['serial_no' => 'solar', 'state' => 'low voltage']);

        $this->artisan('device:check-status')->assertExitCode(0);

        $this->assertDatabaseHas('geocode', ['serial_no' => 'solar', 'status' => 'low voltage']);
        Queue::assertPushedOn('mail', SendAppUpdateMail::class, fn ($job) =>
            $job->connection === 'app-updates' && $job->recipientId === 1 && $job->mail instanceof LowVoltageMail
        );
        Mail::assertNothingSent();
        $this->artisan('device:check-status')->assertExitCode(0);
        Queue::assertPushed(SendAppUpdateMail::class, 1);
        $this->assertStringNotContainsString('Twilio', file_get_contents(app_path('Console/Commands/UpdateDeviceStatus.php')));
    }

    public function test_failed_enqueue_does_not_consume_the_status_transition(): void
    {
        DB::table('users')->insert(['id' => 1, 'name' => 'Owner', 'email' => 'owner@example.test']);
        DB::table('devices')->insert([
            'serial_no' => 'solar', 'user_id' => 1,
            'status_notification' => true, 'address_1' => '1 Main St',
        ]);
        DB::table('geocode')->insert(['serial_no' => 'solar', 'status' => 'offline']);
        DeviceLog::create(['serial_no' => 'solar', 'state' => 'low voltage']);
        $this->mock(\App\Services\AppUpdateDelivery::class, function ($mock) {
            $mock->shouldReceive('queue')->once()->andThrow(new \RuntimeException('Queue unavailable'));
        });

        try {
            $this->artisan('device:check-status')->run();
            $this->fail('Expected the enqueue failure to propagate.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Queue unavailable', $exception->getMessage());
        }

        $this->assertDatabaseHas('geocode', ['serial_no' => 'solar', 'status' => 'offline']);
    }

    public function test_sms_deactivation_is_explicit_and_non_destructive(): void
    {
        DB::table('devices')->insert([
            ['serial_no' => 'one', 'sms_notification' => true],
            ['serial_no' => 'two', 'sms_notification' => false],
        ]);

        $this->artisan('device:deactivate-sms-notifications')->assertExitCode(0);
        $this->assertDatabaseHas('devices', ['serial_no' => 'one', 'sms_notification' => true]);
        $this->artisan('device:deactivate-sms-notifications', ['--apply' => true])->assertExitCode(0);
        $this->assertDatabaseHas('devices', ['serial_no' => 'one', 'sms_notification' => false]);
        $this->assertDatabaseHas('devices', ['serial_no' => 'two', 'sms_notification' => false]);
    }

    public function test_sms_routes_configuration_and_dependency_are_removed(): void
    {
        $this->assertFalse(Route::has('update.sms_notification'));
        $this->assertFalse(Route::has('device.api-update-sms-notification'));
        $this->assertFileDoesNotExist(app_path('Services/TwilioService.php'));
        $this->assertStringNotContainsString('twilio/sdk', file_get_contents(base_path('composer.json')));
        $this->assertStringNotContainsString('TWILIO_', file_get_contents(base_path('.env.example')));
    }

    public function test_energy_monitor_is_archived_and_not_an_active_status_or_view_device_path(): void
    {
        $this->assertFileDoesNotExist(app_path('Models/Api/EnergyMonitorLog.php'));
        $this->assertFileExists(base_path('docs/archive/energy-monitor.md'));
        DB::table('devices')->insert(['id' => 2, 'serial_no' => 'retired']);
        DB::table('geocode')->insert(['serial_no' => 'retired', 'status' => 'archived']);

        $response = app(\App\Http\Controllers\ViewDeviceController::class)->getLatestStatusJson('retired');
        $this->assertSame(200, $response->getStatusCode());
        $this->artisan('device:check-status')->assertExitCode(0);
        $this->assertDatabaseHas('geocode', ['serial_no' => 'retired', 'status' => 'offline']);
    }
}
