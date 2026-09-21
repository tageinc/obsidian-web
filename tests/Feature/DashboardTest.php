<?php

namespace Tests\Feature;

use App\Models\DeviceRegister;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_the_device_manager_with_only_the_users_devices(): void
    {
        $owner = $this->user('owner@example.test');
        $other = $this->user('other@example.test');
        DB::table('hardware')->insert(['id' => 1, 'name' => 'Solar Tracker', 'prefix' => 'SP1']);
        DeviceRegister::create(['serial_no' => 'mine', 'alias' => 'My solar tracker', 'hardware_id' => 1, 'user_id' => $owner->id]);
        DeviceRegister::create(['serial_no' => 'other', 'alias' => 'Other private tracker', 'hardware_id' => 1, 'user_id' => $other->id]);

        $this->actingAs($owner)->get('/dashboard')
            ->assertOk()->assertViewIs('device-manager')
            ->assertSee('My solar tracker')->assertDontSee('Other private tracker')
            ->assertSee('Register device')->assertSee('Edit profile')
            ->assertSee('id="map"', false);
    }

    public function test_home_and_old_device_manager_urls_redirect_to_dashboard_with_pagination(): void
    {
        $this->actingAs($this->user('owner@example.test'));
        $this->get('/')->assertRedirect('/dashboard');
        $this->get('/device-manager?show=20&page=2')->assertRedirect('/dashboard?show=20&page=2');
    }

    public function test_login_lands_on_dashboard_and_empty_dashboard_has_a_registration_link(): void
    {
        $this->user('owner@example.test');
        $this->post('/login', ['email' => 'owner@example.test', 'password' => 'password'])
            ->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk()->assertViewIs('device-manager')
            ->assertSee('No devices registered yet.')->assertSee(route('device-register'));
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

    public function test_legacy_redirect_preserves_device_feedback(): void
    {
        $this->actingAs($this->user('owner@example.test'));
        $this->followingRedirects()->get('/device-info/99999')
            ->assertOk()->assertSee('Device not found')->assertViewIs('device-manager');
    }

    private function user(string $email): User
    {
        return User::create([
            'name' => 'Dashboard user', 'email' => $email,
            'password' => Hash::make('password'), 'email_verified_at' => now(),
        ]);
    }
}
