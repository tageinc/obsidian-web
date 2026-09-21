<?php

namespace Tests\Feature;

use App\Mail\LowVoltageMail;
use App\Models\Api\SolarTrackerLog;
use App\Models\DeviceRegister;
use Carbon\Carbon;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\HardwareSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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

        Schema::create('device_registers', function (Blueprint $table) {
            $table->id();
            $table->string('serial_no');
            $table->integer('hardware_id');
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
        DB::table('device_registers')->insert(['serial_no' => 'solar', 'hardware_id' => 1]);

        $this->post('/api/log', ['serial_no' => 'solar', 'data' => '{"ps1":0,"state":"online"}'])
            ->assertOk()->assertExactJson(['msg' => 'success']);
        $this->postJson('/api/log', ['serial_no' => 'solar', 'data' => '{}'])->assertStatus(422);

        $this->assertSame(1, SolarTrackerLog::count());
    }

    public function test_retired_energy_monitor_telemetry_is_not_written(): void
    {
        DB::table('device_registers')->insert(['serial_no' => 'retired', 'hardware_id' => 2]);

        $this->postJson('/api/log', ['serial_no' => 'retired', 'data' => '{"v_batt":12.5}'])
            ->assertOk()->assertExactJson(['msg' => 'invalid hardware']);

        $this->assertSame(0, SolarTrackerLog::count());
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

    public function test_admin_user_seeder_creates_the_configured_account_once(): void
    {
        putenv('ADMIN_EMAIL=admin@example.test');
        putenv('ADMIN_BOOTSTRAP_PASSWORD=seed-password');

        try {
            $this->seed(AdminUserSeeder::class);
            $this->seed(AdminUserSeeder::class);

            $admin = DB::table('users')->where('email', 'admin@example.test')->first();
            $this->assertNotNull($admin);
            $this->assertTrue(Hash::check('seed-password', $admin->password));
            $this->assertSame(1, DB::table('users')->where('email', 'admin@example.test')->count());
        } finally {
            putenv('ADMIN_EMAIL');
            putenv('ADMIN_BOOTSTRAP_PASSWORD');
        }
    }

    public function test_hardware_seeder_creates_the_solar_tracker_at_legacy_id_one(): void
    {
        $this->seed(HardwareSeeder::class);
        $this->seed(HardwareSeeder::class);

        $this->assertDatabaseHas('hardware', [
            'id' => HardwareSeeder::SOLAR_TRACKER_ID,
            'name' => 'Solar Tracker',
            'prefix' => HardwareSeeder::SOLAR_TRACKER_PREFIX,
        ]);
        $this->assertSame(1, DB::table('hardware')->count());
    }

    public function test_status_command_preserves_email_notifications_without_sms_or_twilio(): void
    {
        Mail::fake();
        DB::table('users')->insert(['id' => 1, 'name' => 'Owner', 'email' => 'owner@example.test']);
        DB::table('device_registers')->insert([
            'serial_no' => 'solar', 'hardware_id' => 1, 'user_id' => 1,
            'status_notification' => true, 'sms_notification' => true, 'address_1' => '1 Main St',
        ]);
        DB::table('geocode')->insert(['serial_no' => 'solar', 'status' => 'offline', 'latitude' => 1, 'longitude' => 2]);
        SolarTrackerLog::create(['serial_no' => 'solar', 'state' => 'low voltage']);

        $this->artisan('device:check-status')->assertExitCode(0);

        $this->assertDatabaseHas('geocode', ['serial_no' => 'solar', 'status' => 'low voltage']);
        Mail::assertSent(LowVoltageMail::class, 1);
        $this->assertStringNotContainsString('Twilio', file_get_contents(app_path('Console/Commands/UpdateDeviceStatus.php')));
    }

    public function test_sms_deactivation_is_explicit_and_non_destructive(): void
    {
        DB::table('device_registers')->insert([
            ['serial_no' => 'one', 'hardware_id' => 1, 'sms_notification' => true],
            ['serial_no' => 'two', 'hardware_id' => 1, 'sms_notification' => false],
        ]);

        $this->artisan('device:deactivate-sms-notifications')->assertExitCode(0);
        $this->assertDatabaseHas('device_registers', ['serial_no' => 'one', 'sms_notification' => true]);
        $this->artisan('device:deactivate-sms-notifications', ['--apply' => true])->assertExitCode(0);
        $this->assertDatabaseHas('device_registers', ['serial_no' => 'one', 'sms_notification' => false]);
        $this->assertDatabaseHas('device_registers', ['serial_no' => 'two', 'sms_notification' => false]);
    }

    public function test_sms_routes_configuration_and_dependency_are_removed(): void
    {
        $this->assertFalse(Route::has('update.sms_notification'));
        $this->assertFalse(Route::has('device.api-update-sms-notification'));
        $this->assertFileDoesNotExist(app_path('Services/TwilioService.php'));
        $this->assertStringNotContainsString('twilio/sdk', file_get_contents(base_path('composer.json')));
        $this->assertStringNotContainsString('TWILIO_', file_get_contents(base_path('.env.example')));
    }

    public function test_energy_monitor_is_archived_and_not_an_active_status_or_device_info_path(): void
    {
        $this->assertFileDoesNotExist(app_path('Models/Api/EnergyMonitorLog.php'));
        $this->assertFileExists(base_path('docs/archive/energy-monitor.md'));
        DB::table('device_registers')->insert(['id' => 2, 'serial_no' => 'retired', 'hardware_id' => 2]);
        DB::table('geocode')->insert(['serial_no' => 'retired', 'status' => 'archived']);

        $response = app(\App\Http\Controllers\DeviceInfoController::class)->getLatestStatusJson('retired');
        $this->assertSame(410, $response->getStatusCode());
        $this->artisan('device:check-status')->assertExitCode(0);
        $this->assertDatabaseHas('geocode', ['serial_no' => 'retired', 'status' => 'archived']);
    }
}
