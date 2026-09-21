<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\CustomVerifyEmail;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class VerificationSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->user = User::create(['name' => 'Recipient', 'email' => 'recipient@example.test', 'password' => 'hash']);
    }

    private function link(array $overrides = [], $expiration = null): string
    {
        return URL::temporarySignedRoute('verification.verify', $expiration ?? now()->addHour(), array_merge([
            'id' => $this->user->id, 'hash' => sha1($this->user->getEmailForVerification()),
        ], $overrides));
    }

    public function test_logged_out_recipient_can_verify_a_valid_link_once_without_being_logged_in(): void
    {
        Event::fake([Verified::class]);
        $link = $this->link();
        $this->get($link)->assertRedirect('/login')->assertSessionHas('verified', true);
        $this->assertNotNull($this->user->fresh()->email_verified_at);
        $this->assertGuest();
        $this->get($link)->assertRedirect('/login');
        Event::assertDispatchedTimes(Verified::class, 1);
        Notification::assertNothingSent();
    }

    public function test_tampered_signature_hash_id_and_expired_or_nonexpiring_links_cannot_verify(): void
    {
        $valid = $this->link();
        $links = [
            $valid.'&extra=tampered',
            str_replace('/'.$this->user->id.'/', '/999999/', $valid),
            $this->link(['hash' => sha1('other@example.test')]),
            $this->link([], now()->subMinute()),
            URL::signedRoute('verification.verify', ['id' => $this->user->id, 'hash' => sha1($this->user->email)]),
            route('verification.verify', ['id' => $this->user->id, 'hash' => sha1($this->user->email)]),
        ];
        foreach ($links as $link) {
            $this->get($link)->assertForbidden();
            $this->assertNull($this->user->fresh()->email_verified_at);
        }
    }

    public function test_email_change_invalidates_a_previously_signed_link(): void
    {
        $link = $this->link();
        $this->user->update(['email' => 'new-address@example.test']);
        $this->get($link)->assertForbidden();
        $this->assertNull($this->user->fresh()->email_verified_at);
    }

    public function test_notification_generates_a_signed_link_with_a_one_hour_expiration(): void
    {
        $message = (new CustomVerifyEmail())->toMail($this->user);
        $request = \Illuminate\Http\Request::create($message->actionUrl);
        $this->assertTrue(URL::hasValidSignature($request));
        $this->assertEqualsWithDelta(now()->addHour()->timestamp, (int) $request->query('expires'), 2);
    }

    public function test_logged_out_resend_sends_exactly_one_notification_and_supports_session_email(): void
    {
        $this->from('/login')->post('/email/resend', ['email' => $this->user->email])
            ->assertRedirect('/login')->assertSessionHas('status');
        Notification::assertSentToTimes($this->user, CustomVerifyEmail::class, 1);
        Notification::fake();
        $this->withSession(['login_email' => $this->user->email])->from('/login')->post('/email/resend')
            ->assertRedirect('/login')->assertSessionHas('status');
        Notification::assertSentToTimes($this->user, CustomVerifyEmail::class, 1);
    }

    public function test_authenticated_verification_notice_can_resend_without_a_hidden_email(): void
    {
        $this->get('/email/verify')->assertRedirect('/login');
        $this->actingAs($this->user)->get('/email/verify')->assertOk();
        $this->from('/email/verify')->post('/email/resend')->assertRedirect('/email/verify');
        Notification::assertSentToTimes($this->user, CustomVerifyEmail::class, 1);
    }

    public function test_resend_is_throttled_and_does_not_notify_verified_or_invalid_recipients(): void
    {
        $this->postJson('/email/resend', ['email' => ['invalid']])->assertUnprocessable();
        $this->user->update(['email_verified_at' => now()]);
        $this->post('/email/resend', ['email' => $this->user->email])->assertRedirect('/login');
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->post('/email/resend', ['email' => 'missing@example.test'])->assertRedirect();
        }
        $this->post('/email/resend', ['email' => $this->user->email])->assertStatus(429);
        Notification::assertNothingSent();
    }
}
