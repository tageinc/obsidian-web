<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\UsesFrontendManifest;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;
    use UsesFrontendManifest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useFrontendManifest();
    }

    public function test_dashboard_renders_the_device_manager_with_only_the_users_devices(): void
    {
        $owner = $this->user('owner@example.test');
        $other = $this->user('other@example.test');
        Device::create(['serial_no' => 'mine', 'name' => 'My solar tracker', 'user_id' => $owner->id]);
        Device::create(['serial_no' => 'other', 'name' => 'Other private tracker', 'user_id' => $other->id]);

        $this->actingAs($owner)->get('/dashboard')
            ->assertOk()->assertViewIs('device-manager')
            ->assertSee('My solar tracker')->assertDontSee('Other private tracker')
            ->assertSee('frontend-create-device-launcher')->assertDontSee('Edit profile')
            ->assertSee(route('profile'))
            ->assertSee('id="map"', false);
    }

    public function test_home_and_old_device_manager_urls_redirect_to_dashboard_with_pagination(): void
    {
        $this->actingAs($this->user('owner@example.test'));
        $this->get('/')->assertRedirect('/dashboard');
        $this->get('/device-manager?show=20&page=2')->assertRedirect('/dashboard?show=20&page=2');
    }

    public function test_login_lands_on_dashboard_and_empty_dashboard_has_a_creation_link(): void
    {
        $this->user('owner@example.test');
        $this->post('/login', ['email' => 'owner@example.test', 'password' => 'password'])
            ->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk()->assertViewIs('device-manager')
            ->assertSee('No devices registered yet.')->assertSee('data-vue-page="create-device-launcher"', false);
    }

    public function test_dashboard_requires_login_and_verified_email(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/device-manager')->assertRedirect('/login');
        $user = $this->user('unverified@example.test');
        $user->email_verified_at = null;
        $user->save();
        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('verification.notice'));
    }

    public function test_missing_device_returns_not_found(): void
    {
        $this->actingAs($this->user('owner@example.test'));
        $this->get('/devices/99999')->assertNotFound();
    }

    private function user(string $email): User
    {
        return User::create([
            'name' => 'Dashboard user', 'email' => $email,
            'password' => Hash::make('password'), 'email_verified_at' => now(),
        ]);
    }
}
