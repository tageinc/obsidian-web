<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\GeoCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\Concerns\UsesFrontendManifest;
use Tests\TestCase;

class DashboardCreationTest extends TestCase
{
    use RefreshDatabase;
    use UsesFrontendManifest;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useFrontendManifest();
        config(['frontend.vue3.dashboard' => true, 'frontend.vue3.create_device' => true]);
        $this->owner = User::create([
            'name' => 'Modal owner', 'email' => 'modal@example.test',
            'password' => 'hash-must-not-be-serialized', 'email_verified_at' => now(),
            'address_1' => '10 Profile Street', 'address_2' => 'Suite 3',
            'city' => 'Toronto', 'state' => 'ON', 'zip_code' => 'M5V 1A1', 'country' => 'CA',
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
            '_creation_modal' => '1', 'name' => 'Garden tracker',
            'serial_no' => 'MODAL-TRACKER-1', 'sku' => 'SP1', 'order_no' => 'ORDER-1',
            'address_1' => '20 Garden Street', 'address_2' => 'Rear garden', 'city' => 'Vancouver',
            'address_state' => 'BC', 'zip_code' => 'V6B 1A1', 'country' => 'CA',
            'latitude' => '49.2827', 'longitude' => '-123.1207',
        ], $overrides);
    }

    public function test_creation_prefill_matches_standalone_defaults_without_hardware(): void
    {
        $response = $this->get('/dashboard');
        $creation = $this->props($response)['creation'];
        $this->get('/create-device')->assertRedirect('/dashboard?create=1');
        $this->assertSame('10 Profile Street', $creation['values']['address_1']);
        $this->assertSame('CA', $creation['values']['country']);
        $this->assertNull($creation['values']['name']);
        $this->assertSame('34.052235', $creation['values']['latitude']);
        $this->assertSame('-118.243683', $creation['values']['longitude']);
        $this->assertArrayNotHasKey('hardware_id', $creation['values']);
        $this->assertSame(route('create-device.store'), $creation['action']);
        $this->assertSame(csrf_token(), $creation['csrfToken']);
        $this->assertFalse($creation['initiallyOpen']);
        $this->assertSame([], $creation['errors']);
        $this->assertSame([
            'name', 'serial_no', 'sku', 'order_no', 'latitude', 'longitude',
            'address_1', 'address_2', 'city', 'address_state', 'zip_code', 'country',
        ], array_keys($creation['values']));
        $response->assertDontSee('hash-must-not-be-serialized')->assertDontSee('Retired device');
    }

    public function test_country_defaults_to_us_and_flag_off_keeps_the_standalone_creation_url(): void
    {
        $this->owner->update(['country' => null]);
        $this->assertSame('US', $this->props($this->get('/dashboard'))['creation']['values']['country']);
        $this->assertTrue($this->props($this->get('/dashboard?create=1'))['creation']['initiallyOpen']);
        config(['frontend.vue3.create_device' => false]);
        $props = $this->props($this->get('/dashboard'));
        $this->assertNotNull($props['creation']);
        $this->assertSame(route('create-device'), $props['links']['create']);
        $this->get('/create-device')->assertRedirect('/dashboard?create=1');
        config(['frontend.vue3.dashboard' => false]);
        $this->get('/dashboard?create=1')->assertOk()->assertSee('frontend-create-device-launcher', false);
    }

    public function test_unrelated_old_input_and_feedback_do_not_fill_or_open_creation(): void
    {
        $props = $this->props($this->withSession([
            '_old_input' => ['name' => 'Other form value', 'city' => 'Other city', 'password' => 'secret-marker', 'user_id' => 999],
            'errors' => (new ViewErrorBag)->put('default', new MessageBag(['name' => ['Other form error.']])),
            'error' => 'Device unavailable',
        ])->get('/dashboard'));
        $this->assertNull($props['creation']['values']['name']);
        $this->assertSame('Toronto', $props['creation']['values']['city']);
        $this->assertFalse($props['creation']['initiallyOpen']);
        $this->assertSame([], $props['creation']['errors']);
        $this->assertNull($props['creation']['sessionError']);
        $this->assertSame('Device unavailable', $props['sessionError']);
        $this->assertStringNotContainsString('secret-marker', json_encode($props));
    }

    public function test_invalid_native_modal_submission_redirects_back_and_reopens_with_only_creation_feedback(): void
    {
        $name = '</script><img src=x onerror=alert(1)>';
        $this->from('/dashboard?search=roof&show=20')->post('/create-device', $this->fields([
            'name' => $name, 'city' => '', 'latitude' => 91,
            'password' => 'secret-marker', 'api_token' => 'secret-marker', 'user_id' => 999,
        ]))->assertRedirect('/dashboard')
            ->assertSessionHasErrors(['city', 'latitude'])
            ->assertSessionHasInput('_creation_modal', '1');
        $this->assertSame(0, Device::count());
        $this->assertSame(0, GeoCode::count());

        $response = $this->get('/dashboard?search=roof&show=20');
        $props = $this->props($response);
        $creation = $props['creation'];
        $this->assertTrue($creation['initiallyOpen']);
        $this->assertSame($name, $creation['values']['name']);
        $this->assertSame('MODAL-TRACKER-1', $creation['values']['serial_no']);
        $this->assertNull($creation['values']['city']);
        $this->assertSame('91', (string) $creation['values']['latitude']);
        $this->assertSame(['city', 'latitude'], array_keys($creation['errors']));
        $this->assertArrayNotHasKey('errors', $props);
        foreach (['password', 'api_token', 'user_id', '_creation_modal'] as $field) {
            $this->assertArrayNotHasKey($field, $creation['values']);
        }
        $response->assertDontSee($name, false)->assertDontSee('secret-marker');
    }

    public function test_modal_feedback_is_scoped_to_creation_fields_and_preserves_session_error_location(): void
    {
        $props = $this->props($this->withSession([
            '_old_input' => ['_creation_modal' => '1'],
            'errors' => (new ViewErrorBag)->put('default', new MessageBag(['search' => ['Search error.']])),
        ])->get('/dashboard'));
        $this->assertFalse($props['creation']['initiallyOpen']);
        $this->assertSame([], $props['creation']['errors']);
        $props = $this->props($this->withSession([
            '_old_input' => ['_creation_modal' => '1'], 'error' => 'Creation unavailable',
        ])->get('/dashboard'));
        $this->assertTrue($props['creation']['initiallyOpen']);
        $this->assertSame('Creation unavailable', $props['creation']['sessionError']);
        $this->assertNull($props['sessionError']);
    }

    public function test_successful_modal_post_uses_existing_ownership_geocode_and_notification_rules(): void
    {
        $this->from('/dashboard')->post('/create-device', $this->fields([
            'user_id' => 999, 'status_notification' => 1, 'sms_notification' => 1,
        ]))->assertRedirect('/dashboard')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('devices', [
            'serial_no' => 'MODAL-TRACKER-1', 'name' => 'Garden tracker', 'user_id' => $this->owner->id,
            'status_notification' => false, 'sms_notification' => false,
        ]);
        $this->assertDatabaseHas('geocode', [
            'serial_no' => 'MODAL-TRACKER-1', 'latitude' => 49.2827, 'longitude' => -123.1207, 'status' => 'none',
        ]);
        $props = $this->props($this->get('/dashboard'));
        $this->assertFalse($props['creation']['initiallyOpen']);
        $this->assertNull($props['creation']['values']['name']);
        $this->assertSame('Device created successfully.', $props['success']);
        $this->assertSame('Garden tracker', $props['devices'][0]['name']);
    }
}
