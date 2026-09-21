<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\CustomVerifyEmail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\UsesFrontendManifest;
use Tests\TestCase;

class AuthFrontendTest extends TestCase
{
    use RefreshDatabase;
    use UsesFrontendManifest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useFrontendManifest();
        config(['frontend.vue3.auth' => true]);
        Notification::fake();
    }

    private function props($response): array
    {
        $response->assertOk()->assertSee('data-vue-page="auth"', false);
        preg_match('/<script id="frontend-auth" type="application\/json">(.*?)<\/script>/s', $response->getContent(), $matches);

        return json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
    }

    private function user(bool $verified = true): User
    {
        return User::create([
            'name' => 'Auth fixture', 'email' => 'auth-fixture@example.test',
            'password' => Hash::make('fixture-password'), 'email_verified_at' => $verified ? now() : null,
        ]);
    }

    public function test_all_six_auth_screens_emit_the_correct_native_form_contract(): void
    {
        foreach ([
            '/login' => ['login', '/login'],
            '/register' => ['register', '/register'],
            '/password/reset' => ['password-email', '/password/email'],
            '/password/reset/reset-fixture?email=reset%40example.test' => ['password-reset', '/password/reset'],
        ] as $path => [$mode, $action]) {
            $props = $this->props($this->get($path));
            $this->assertSame($mode, $props['mode']);
            $this->assertSame(url($action), $props['action']);
            $this->assertNotEmpty($props['csrfToken']);
            if ($mode === 'password-reset') {
                $this->assertSame('reset-fixture', $props['resetToken']);
                $this->assertSame('reset@example.test', $props['values']['email']);
            } else {
                $this->assertArrayNotHasKey('resetToken', $props);
            }
        }
        $user = $this->user(false);
        $this->actingAs($user);
        $verify = $this->props($this->get('/email/verify'));
        $this->assertSame('verify', $verify['mode']);
        $this->assertSame($user->email, $verify['values']['email']);
        $confirm = $this->props($this->get('/password/confirm'));
        $this->assertSame('password-confirm', $confirm['mode']);
        $this->assertArrayNotHasKey('values', $confirm);
    }

    public function test_old_auth_values_are_allowlisted_and_login_retains_resend_and_session_feedback(): void
    {
        $old = ['email' => 'retained@example.test', 'city' => 'Vancouver', 'zip_code' => 'V6B 1A1',
            'password' => 'secret-fixture', 'password_confirmation' => 'secret-fixture', 'token' => 'secret-fixture',
            'id' => 99, 'api_token' => 'secret-fixture'];
        $register = $this->props($this->withSession(['_old_input' => $old])->get('/register'));
        $this->assertSame('V6B 1A1', $register['values']['zip_code']);
        $this->assertStringNotContainsString('secret-fixture', json_encode($register));
        $this->assertArrayNotHasKey('id', $register['values']);
        $login = $this->props($this->withSession([
            '_old_input' => $old, 'error' => 'You need to verify your email address to access this page.',
            'message' => 'Your session has expired.',
        ])->get('/login'));
        $this->assertTrue($login['showResend']);
        $this->assertSame(url('/email/resend'), $login['links']['verificationResend']);
        $this->assertSame('Your session has expired.', $login['message']);
        $this->assertStringNotContainsString('secret-fixture', json_encode($login));
    }

    public function test_auth_flag_off_retains_each_legacy_native_form(): void
    {
        config(['frontend.vue3.auth' => false]);
        foreach (['/login', '/register', '/password/reset', '/password/reset/reset-fixture'] as $path) {
            $this->get($path)->assertOk()->assertDontSee('data-vue-page="auth"', false)->assertSee('method="POST"', false);
        }
        $this->actingAs($this->user(false));
        foreach (['/email/verify', '/password/confirm'] as $path) {
            $this->get($path)->assertOk()->assertDontSee('data-vue-page="auth"', false)->assertSee('method="POST"', false);
        }
    }

    public function test_registration_keeps_server_validation_and_sends_verification_once(): void
    {
        $fields = [
            'name' => 'New account', 'email' => 'new-account@example.test', 'phone_number' => '6045550123',
            'address_1' => '10 Test Street', 'address_2' => '', 'city' => 'Vancouver', 'state' => 'BC',
            'country' => 'CA', 'zip_code' => 'V6B 1A1', 'password' => 'fixture-password',
            'password_confirmation' => 'fixture-password',
        ];
        $this->from('/register')->post('/register', array_merge($fields, ['password_confirmation' => 'different']))
            ->assertRedirect('/register')->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 0);
        $this->post('/register', $fields)->assertRedirect('/email/verify');
        $user = User::where('email', $fields['email'])->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentToTimes($user, CustomVerifyEmail::class, 1);
    }

    public function test_password_email_reset_confirmation_and_logout_keep_server_sessions(): void
    {
        $user = $this->user();
        $this->from('/password/reset')->post('/password/email', ['email' => $user->email])
            ->assertRedirect('/password/reset')->assertSessionHas('status');
        $notification = Notification::sent($user, ResetPassword::class)->first();
        $this->assertNotNull($notification);
        $this->post('/password/reset', [
            'email' => $user->email, 'token' => $notification->token,
            'password' => 'replacement-password', 'password_confirmation' => 'replacement-password',
        ])->assertRedirect('/dashboard');
        $this->assertTrue(Hash::check('replacement-password', $user->fresh()->password));
        $this->assertAuthenticatedAs($user);
        $this->from('/password/confirm')->post('/password/confirm', ['password' => 'wrong-password'])
            ->assertRedirect('/password/confirm')->assertSessionHasErrors('password');
        $this->post('/password/confirm', ['password' => 'replacement-password'])
            ->assertRedirect('/dashboard')->assertSessionHas('auth.password_confirmed_at');
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }
}
