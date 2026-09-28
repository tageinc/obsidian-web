<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Logger;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Monolog\Handler\TestHandler;
use Tests\Concerns\UsesFrontendManifest;
use Tests\TestCase;

class AuthenticationMailDeliveryTest extends TestCase
{
    use RefreshDatabase;
    use UsesFrontendManifest;

    private const ORIGINAL_PASSWORD = 'synthetic-original-password';
    private const REPLACEMENT_PASSWORD = 'synthetic-replacement-password';
    private const TRANSPORT_SECRET = 'synthetic-smtp-secret-must-not-be-exposed';

    private ArrayTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useFrontendManifest();
        config(['mail.default' => 'array', 'frontend.vue3.auth' => true]);
        $this->transport = $this->app['mailer']->getSwiftMailer()->getTransport();
        $this->assertInstanceOf(ArrayTransport::class, $this->transport);
    }

    public function test_rendered_reset_mail_works_despite_activity_opt_out_and_token_is_one_use(): void
    {
        $user = $this->recipient(true);
        $this->from('/password/reset')->post('/password/email', ['email' => $user->email])
            ->assertRedirect('/password/reset')->assertSessionHas('status', trans('passwords.sent'));

        $url = $this->deliveredLink($user, '/password/reset/');
        $token = basename(parse_url($url, PHP_URL_PATH));
        $this->assertTrue(Password::broker()->tokenExists($user, $token));
        $page = $this->get($url)->assertOk()->assertSee('data-vue-page="auth"', false);
        preg_match('/<script id="frontend-auth" type="application\/json">(.*?)<\/script>/s', $page->getContent(), $matches);
        $props = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('password-reset', $props['mode']);
        $this->assertSame($token, $props['resetToken']);
        $this->assertSame($user->email, $props['values']['email']);
        $credentials = $this->resetCredentials($user, $token);
        $this->post('/password/reset', $credentials)->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check(self::REPLACEMENT_PASSWORD, $user->fresh()->password));
        $this->assertFalse(Password::broker()->tokenExists($user, $token));
        $this->post('/logout')->assertRedirect('/');
        $this->postJson('/password/reset', array_merge($credentials, [
            'password' => 'another-synthetic-password',
            'password_confirmation' => 'another-synthetic-password',
        ]))->assertStatus(422)->assertJsonValidationErrors('email');
        $this->assertTrue(Hash::check(self::REPLACEMENT_PASSWORD, $user->fresh()->password));
        $this->assertCount(1, $this->transport->messages());
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_delivered_reset_token_expires_without_changing_password(): void
    {
        $user = $this->recipient(true);
        $this->postJson('/password/email', ['email' => $user->email])->assertOk();
        $url = $this->deliveredLink($user, '/password/reset/');
        $token = basename(parse_url($url, PHP_URL_PATH));
        $expiry = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');
        DB::table('password_resets')->where('email', $user->email)
            ->update(['created_at' => now()->subMinutes($expiry)->subSecond()]);

        $this->postJson('/password/reset', $this->resetCredentials($user, $token))
            ->assertStatus(422)->assertJsonValidationErrors('email');
        $this->assertTrue(Hash::check(self::ORIGINAL_PASSWORD, $user->fresh()->password));
        $this->assertGuest();
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_rendered_verification_mail_uses_a_signed_link_despite_activity_opt_out(): void
    {
        $user = $this->recipient();
        $this->from('/login')->post('/email/resend', ['email' => $user->email])
            ->assertRedirect('/login')->assertSessionHas('status', 'Verification email sent to: '.$user->email);
        $url = $this->deliveredLink($user, '/email/verify/');
        $this->assertStringContainsString('expires=', $url);
        $this->assertStringContainsString('signature=', $url);
        $this->get($url.'&tampered=1')->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
        $this->get($url)->assertRedirect('/login')->assertSessionHas('verified', true);
        $verifiedAt = $user->fresh()->email_verified_at;
        $this->get($url)->assertRedirect('/login');
        $this->assertEquals($verifiedAt, $user->fresh()->email_verified_at);
        $this->assertGuest();
        $this->assertCount(1, $this->transport->messages());
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_delivered_verification_link_expires_without_verifying_recipient(): void
    {
        $user = $this->recipient();
        $this->post('/email/resend', ['email' => $user->email])->assertRedirect();
        $url = $this->deliveredLink($user, '/email/verify/');
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->travelTo(now()->setTimestamp((int) $query['expires'] + 1));
        try {
            $this->get($url)->assertForbidden();
            $this->assertNull($user->fresh()->email_verified_at);
            $this->assertDatabaseCount('notifications', 0);
        } finally {
            $this->travelBack();
        }
    }

    /** @dataProvider resetFailureResponses */
    public function test_reset_delivery_failure_is_safe_removes_undelivered_token_and_allows_retry(bool $json): void
    {
        $user = $this->recipient(true);
        $handler = $this->captureLogs();
        $this->failTransport();
        $payload = ['email' => $user->email, 'password' => self::TRANSPORT_SECRET, 'irrelevant' => 'must-not-flash'];
        if ($json) {
            $response = $this->postJson('/password/email', $payload)->assertStatus(422)
                ->assertJsonPath('errors.email.0', trans('passwords.delivery_failed'));
        } else {
            $response = $this->from('/password/reset')->post('/password/email', $payload)
                ->assertRedirect('/password/reset')->assertSessionHasErrors(['email' => trans('passwords.delivery_failed')])
                ->assertSessionHas('_old_input', ['email' => $user->email]);
        }
        $this->assertStringNotContainsString(self::TRANSPORT_SECRET, $response->getContent());
        $this->assertDatabaseMissing('password_resets', ['email' => $user->email]);
        $this->assertTrue(Hash::check(self::ORIGINAL_PASSWORD, $user->fresh()->password));
        $this->assertCount(0, $this->transport->messages());
        $this->assertSafeFailureLog($handler, 'auth.password_reset_delivery_failed');

        $this->restoreTransport();
        $this->postJson('/password/email', ['email' => $user->email])->assertOk()
            ->assertJsonPath('message', trans('passwords.sent'));
        $url = $this->deliveredLink($user, '/password/reset/');
        $this->assertTrue(Password::broker()->tokenExists($user, basename(parse_url($url, PHP_URL_PATH))));
        $this->assertDatabaseCount('notifications', 0);
    }

    public static function resetFailureResponses(): array
    {
        return ['native form' => [false], 'JSON request' => [true]];
    }

    public function test_failed_delivery_does_not_remove_a_concurrently_replaced_reset_token(): void
    {
        $user = $this->recipient(true);
        $newerToken = null;
        $this->failTransport(function () use ($user, &$newerToken) {
            $newerToken = Password::broker()->createToken($user);
        });
        $this->postJson('/password/email', ['email' => $user->email])
            ->assertStatus(422)->assertJsonValidationErrors('email');
        $this->assertNotNull($newerToken);
        $this->assertTrue(Password::broker()->tokenExists($user, $newerToken));
        $this->assertTrue(Hash::check(self::ORIGINAL_PASSWORD, $user->fresh()->password));
    }

    public function test_verification_delivery_failure_is_safe_and_can_be_retried(): void
    {
        $user = $this->recipient();
        $handler = $this->captureLogs();
        $this->failTransport();
        $response = $this->from('/login')->post('/email/resend', ['email' => $user->email])
            ->assertRedirect('/login')->assertSessionHas('error', 'Failed to send verification email.');
        $this->assertStringNotContainsString(self::TRANSPORT_SECRET, $response->getContent());
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertCount(0, $this->transport->messages());
        $this->assertSafeFailureLog($handler, 'auth.verification_delivery_failed');
        $this->restoreTransport();
        $this->post('/email/resend', ['email' => $user->email])->assertRedirect()
            ->assertSessionHas('status', 'Verification email sent to: '.$user->email);
        $this->get($this->deliveredLink($user, '/email/verify/'))->assertRedirect('/login');
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_registration_delivers_one_rendered_verification_mail_and_link_verifies_account(): void
    {
        $fields = $this->registrationFields();
        $this->post('/register', $fields)->assertRedirect('/email/verify');
        $user = User::where('email', $fields['email'])->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at);
        $this->get($this->deliveredLink($user, '/email/verify/'))->assertRedirect('/login');
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertCount(1, $this->transport->messages());
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_registration_delivery_failure_keeps_recoverable_account_and_resend_completes_verification(): void
    {
        $fields = $this->registrationFields();
        $handler = $this->captureLogs();
        $this->failTransport();
        $response = $this->post('/register', $fields)->assertRedirect('/email/verify')
            ->assertSessionHas('error', 'Your account was created, but we could not send the verification email. Please try sending it again.');
        $user = User::where('email', $fields['email'])->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue(Hash::check(self::ORIGINAL_PASSWORD, $user->password));
        $this->assertStringNotContainsString(self::TRANSPORT_SECRET, $response->getContent());
        $this->assertCount(0, $this->transport->messages());
        $this->assertSafeFailureLog($handler, 'auth.verification_delivery_failed', ['source' => 'registration']);
        $this->get('/dashboard')->assertRedirect('/email/verify');
        $this->get('/email/verify')->assertOk()->assertSee('data-vue-page="auth"', false);

        $this->restoreTransport();
        $this->from('/email/verify')->post('/email/resend', ['email' => $user->email])
            ->assertRedirect('/email/verify')->assertSessionHas('status', 'Verification email sent to: '.$user->email);
        $this->get($this->deliveredLink($user, '/email/verify/'))->assertRedirect('/login');
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('notifications', 0);
    }

    private function registrationFields(): array
    {
        return [
            'name' => 'Synthetic new recipient', 'email' => 'registration-mail@example.test',
            'phone_number' => '6045550123', 'address_1' => '10 Test Street', 'address_2' => '',
            'city' => 'Vancouver', 'state' => 'BC', 'country' => 'CA', 'zip_code' => 'V6B 1A1',
            'password' => self::ORIGINAL_PASSWORD, 'password_confirmation' => self::ORIGINAL_PASSWORD,
        ];
    }

    private function recipient(bool $verified = false): User
    {
        $user = User::create([
            'name' => 'Synthetic authentication recipient', 'email' => 'auth-mail@example.test',
            'password' => Hash::make(self::ORIGINAL_PASSWORD),
            'email_verified_at' => $verified ? now() : null,
        ]);
        $user->settings()->create(['receive_app_activity_emails' => false]);

        return $user;
    }

    private function deliveredLink(User $user, string $path): string
    {
        $this->assertCount(1, $this->transport->messages());
        $message = $this->transport->messages()->first();
        $this->assertSame([$user->email], array_keys($message->getTo()));
        $this->assertNotEmpty($message->getSubject());
        preg_match_all('/href="([^"]+)"/', $message->getBody(), $matches);
        foreach ($matches[1] as $href) {
            $url = html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (str_contains(parse_url($url, PHP_URL_PATH) ?? '', $path)) {
                return $url;
            }
        }
        $this->fail('Rendered authentication message did not contain its action link.');
    }

    private function resetCredentials(User $user, string $token): array
    {
        return [
            'email' => $user->email, 'token' => $token,
            'password' => self::REPLACEMENT_PASSWORD, 'password_confirmation' => self::REPLACEMENT_PASSWORD,
        ];
    }

    private function failTransport(?callable $beforeFailure = null): void
    {
        $transport = \Mockery::mock(\Swift_Transport::class);
        $transport->shouldReceive('isStarted')->andReturn(true);
        $transport->shouldReceive('stop')->andReturn(true);
        $transport->shouldReceive('send')->once()->andReturnUsing(function () use ($beforeFailure) {
            if ($beforeFailure) {
                $beforeFailure();
            }
            throw new \Swift_TransportException(self::TRANSPORT_SECRET);
        });
        $this->app['mailer']->setSwiftMailer(new \Swift_Mailer($transport));
    }

    private function restoreTransport(): void
    {
        $this->app['mailer']->setSwiftMailer(new \Swift_Mailer($this->transport));
    }

    private function captureLogs(): TestHandler
    {
        $handler = new TestHandler();
        Log::swap(new Logger(new \Monolog\Logger('test', [$handler])));

        return $handler;
    }

    private function assertSafeFailureLog(TestHandler $handler, string $event, array $extraContext = []): void
    {
        $records = $handler->getRecords();
        $this->assertCount(1, $records);
        $this->assertSame($event, $records[0]['message']);
        $this->assertSame(array_merge(['exception' => \Swift_TransportException::class], $extraContext), $records[0]['context']);
        $this->assertStringNotContainsString(self::TRANSPORT_SECRET, json_encode($records));
    }
}
