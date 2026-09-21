<?php

namespace Tests\Feature;

use App\Models\DeviceRegister;
use App\Models\GeoCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\Concerns\UsesFrontendManifest;
use Tests\TestCase;

class DashboardRegistrationTest extends TestCase
{
    use RefreshDatabase;
    use UsesFrontendManifest;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useFrontendManifest();
        config(['frontend.vue3.dashboard' => true, 'frontend.vue3.device_register' => true]);
        $this->owner = User::create([
            'name' => 'Modal owner', 'email' => 'modal@example.test',
            'password' => 'hash-must-not-be-serialized', 'email_verified_at' => now(),
            'address_1' => '10 Profile Street', 'address_2' => 'Suite 3',
            'city' => 'Toronto', 'state' => 'ON', 'zip_code' => 'M5V 1A1', 'country' => 'CA',
        ]);
        DB::table('hardware')->insert([
            ['id' => 1, 'name' => 'Solar Tracker', 'prefix' => 'SP1'],
            ['id' => 2, 'name' => 'Retired device', 'prefix' => 'OLD'],
        ]);
        $this->actingAs($this->owner);
    }

    private function props($response, string $page = 'dashboard'): array
    {
        $response->assertOk();
        $this->assertSame(1, preg_match('/<script id="frontend-'.preg_quote($page, '/').'" type="application\/json">(.*?)<\/script>/s', $response->getContent(), $matches));

        return json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
    }

    private function fields(array $overrides = []): array
    {
        return array_merge([
            '_registration_modal' => '1', 'hardware_id' => '1', 'alias' => 'Garden tracker',
            'serial_no' => 'MODAL-TRACKER-1', 'sku' => 'SP1', 'order_no' => 'ORDER-1',
            'address_1' => '20 Garden Street', 'address_2' => 'Rear garden', 'city' => 'Vancouver',
            'state' => 'BC', 'zip_code' => 'V6B 1A1', 'country' => 'CA',
            'latitude' => '49.2827', 'longitude' => '-123.1207',
        ], $overrides);
    }

    public function test_registration_prefill_matches_standalone_defaults_and_allows_only_solar_hardware(): void
    {
        $response = $this->get('/dashboard');
        $registration = $this->props($response)['registration'];
        $standalone = $this->props($this->get('/device-register'), 'device-register');
        $this->assertSame($standalone['values'], $registration['values']);
        $this->assertSame('10 Profile Street', $registration['values']['address_1']);
        $this->assertSame('CA', $registration['values']['country']);
        $this->assertSame(1, $registration['values']['hardware_id']);
        $this->assertNull($registration['values']['alias']);
        $this->assertSame([['id' => 1, 'name' => 'Solar Tracker']], $registration['hardwareOptions']);
        $this->assertSame(route('dataInsert'), $registration['action']);
        $this->assertSame(csrf_token(), $registration['csrfToken']);
        $this->assertFalse($registration['initiallyOpen']);
        $this->assertSame([], $registration['errors']);
        $this->assertSame([
            'alias', 'serial_no', 'sku', 'order_no', 'latitude', 'longitude',
            'address_1', 'address_2', 'city', 'state', 'zip_code', 'country', 'hardware_id',
        ], array_keys($registration['values']));
        $response->assertDontSee('hash-must-not-be-serialized')->assertDontSee('Retired device');
    }

    public function test_country_defaults_to_us_and_flag_off_keeps_the_standalone_registration_url(): void
    {
        $this->owner->update(['country' => null]);
        $this->assertSame('US', $this->props($this->get('/dashboard'))['registration']['values']['country']);
        $this->assertSame('US', $this->props($this->get('/device-register'), 'device-register')['values']['country']);
        config(['frontend.vue3.device_register' => false]);
        $props = $this->props($this->get('/dashboard'));
        $this->assertNull($props['registration']);
        $this->assertSame(route('device-register'), $props['links']['register']);
        $this->get('/device-register')->assertOk()->assertSee('action="'.route('dataInsert').'"', false);
    }

    public function test_unrelated_old_input_and_feedback_do_not_fill_or_open_registration(): void
    {
        $props = $this->props($this->withSession([
            '_old_input' => ['alias' => 'Other form value', 'city' => 'Other city', 'password' => 'secret-marker', 'user_id' => 999],
            'errors' => (new ViewErrorBag)->put('default', new MessageBag(['alias' => ['Other form error.']])),
            'error' => 'Device unavailable',
        ])->get('/dashboard'));
        $this->assertNull($props['registration']['values']['alias']);
        $this->assertSame('Toronto', $props['registration']['values']['city']);
        $this->assertFalse($props['registration']['initiallyOpen']);
        $this->assertSame([], $props['registration']['errors']);
        $this->assertNull($props['registration']['sessionError']);
        $this->assertSame('Device unavailable', $props['sessionError']);
        $this->assertStringNotContainsString('secret-marker', json_encode($props));
    }

    public function test_invalid_native_modal_submission_redirects_back_and_reopens_with_only_registration_feedback(): void
    {
        $alias = '</script><img src=x onerror=alert(1)>';
        $this->from('/dashboard?search=roof&show=20')->post('/dataInsert', $this->fields([
            'alias' => $alias, 'city' => '', 'latitude' => 91,
            'password' => 'secret-marker', 'api_token' => 'secret-marker', 'user_id' => 999,
        ]))->assertRedirect('/dashboard?search=roof&show=20')
            ->assertSessionHasErrors(['city', 'latitude'])
            ->assertSessionHasInput('_registration_modal', '1');
        $this->assertSame(0, DeviceRegister::count());
        $this->assertSame(0, GeoCode::count());

        $response = $this->get('/dashboard?search=roof&show=20');
        $props = $this->props($response);
        $registration = $props['registration'];
        $this->assertTrue($registration['initiallyOpen']);
        $this->assertSame($alias, $registration['values']['alias']);
        $this->assertSame('MODAL-TRACKER-1', $registration['values']['serial_no']);
        $this->assertNull($registration['values']['city']);
        $this->assertSame('91', (string) $registration['values']['latitude']);
        $this->assertSame(['city', 'latitude'], array_keys($registration['errors']));
        $this->assertArrayNotHasKey('errors', $props);
        foreach (['password', 'api_token', 'user_id', '_registration_modal'] as $field) {
            $this->assertArrayNotHasKey($field, $registration['values']);
        }
        $response->assertDontSee($alias, false)->assertDontSee('secret-marker');
    }

    public function test_modal_feedback_is_scoped_to_registration_fields_and_preserves_session_error_location(): void
    {
        $props = $this->props($this->withSession([
            '_old_input' => ['_registration_modal' => '1'],
            'errors' => (new ViewErrorBag)->put('default', new MessageBag(['search' => ['Search error.']])),
        ])->get('/dashboard'));
        $this->assertFalse($props['registration']['initiallyOpen']);
        $this->assertSame([], $props['registration']['errors']);
        $props = $this->props($this->withSession([
            '_old_input' => ['_registration_modal' => '1'], 'error' => 'Registration unavailable',
        ])->get('/dashboard'));
        $this->assertTrue($props['registration']['initiallyOpen']);
        $this->assertSame('Registration unavailable', $props['registration']['sessionError']);
        $this->assertNull($props['sessionError']);
    }

    public function test_successful_modal_post_uses_existing_ownership_geocode_and_notification_rules(): void
    {
        $this->from('/dashboard')->post('/dataInsert', $this->fields([
            'user_id' => 999, 'status_notification' => 1, 'sms_notification' => 1,
        ]))->assertRedirect('/dashboard')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('device_registers', [
            'serial_no' => 'MODAL-TRACKER-1', 'alias' => 'Garden tracker', 'user_id' => $this->owner->id,
            'status_notification' => false, 'sms_notification' => false,
        ]);
        $this->assertDatabaseHas('geocode', [
            'serial_no' => 'MODAL-TRACKER-1', 'latitude' => 49.2827, 'longitude' => -123.1207, 'status' => 'none',
        ]);
        $props = $this->props($this->get('/dashboard'));
        $this->assertFalse($props['registration']['initiallyOpen']);
        $this->assertNull($props['registration']['values']['alias']);
        $this->assertSame('Device registered successfully.', $props['success']);
        $this->assertSame('Garden tracker', $props['devices'][0]['alias']);
    }
}
