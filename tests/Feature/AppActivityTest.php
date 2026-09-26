<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Device;
use App\Models\ExternalApiKey;
use App\Models\User;
use App\Notifications\DeviceStatusChangedActivity;
use App\Notifications\CustomVerifyEmail;
use Carbon\Carbon;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class AppActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-26 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_settings_default_to_enabled_and_only_the_current_user_can_change_them(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $token = ApiToken::issue($owner);

        $settings = $this->bearer($token)->getJson('/api/user-settings')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertExactJson(['data' => ['receive_app_activity_emails' => true, 'theme_mode' => 'adaptive']]);
        $this->assertStringContainsString('no-store', $settings->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $settings->headers->get('Cache-Control'));
        $this->assertDatabaseCount('user_settings', 0);
        $this->bearer($token)->patchJson('/api/user-settings', [
            'receive_app_activity_emails' => false, 'user_id' => $other->id,
        ])->assertOk()->assertExactJson(['data' => ['receive_app_activity_emails' => false, 'theme_mode' => 'adaptive']]);
        $this->bearer($token)->getJson('/api/user-settings')->assertOk()
            ->assertJsonPath('data.receive_app_activity_emails', false);
        $this->assertDatabaseHas('user_settings', ['user_id' => $owner->id, 'receive_app_activity_emails' => false]);
        $this->assertDatabaseMissing('user_settings', ['user_id' => $other->id]);
        $this->assertTrue($other->fresh()->receivesAppActivityEmails());
        $this->assertSame('adaptive', $other->fresh()->themeMode());

        $this->bearer($token)->patchJson('/api/user-settings', ['receive_app_activity_emails' => true])
            ->assertOk()->assertJsonPath('data.receive_app_activity_emails', true);
        $this->assertDatabaseCount('user_settings', 1);
    }

    public function test_settings_validate_boolean_values_without_changing_the_saved_preference(): void
    {
        $owner = User::factory()->create();
        $owner->settings()->create(['receive_app_activity_emails' => false]);
        foreach ([[], ['receive_app_activity_emails' => 'sometimes'], ['receive_app_activity_emails' => null]] as $payload) {
            $this->bearer(ApiToken::issue($owner))->patchJson('/api/user-settings', $payload)
                ->assertUnprocessable()->assertJsonValidationErrors('receive_app_activity_emails');
            $this->assertFalse($owner->fresh()->receivesAppActivityEmails());
        }
        $other = User::factory()->create();
        DB::table('user_settings')->insert(['user_id' => $other->id]);
        $this->assertTrue($other->fresh()->receivesAppActivityEmails());
    }

    public function test_theme_and_email_updates_preserve_each_other_without_sending_activity_or_mail(): void
    {
        Mail::fake();
        Notification::fake();
        Queue::fake();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $token = ApiToken::issue($owner);
        $this->assertSame('adaptive', $owner->settings->theme_mode);

        $this->bearer($token)->patchJson('/api/user-settings', ['theme_mode' => 'dark', 'user_id' => $other->id])
            ->assertOk()->assertExactJson(['data' => ['receive_app_activity_emails' => true, 'theme_mode' => 'dark']]);
        $this->bearer($token)->patchJson('/api/user-settings', ['receive_app_activity_emails' => false])
            ->assertOk()->assertExactJson(['data' => ['receive_app_activity_emails' => false, 'theme_mode' => 'dark']]);
        foreach (['light', 'adaptive', 'dark'] as $mode) {
            $saved = $this->bearer($token)->patchJson('/api/user-settings', ['theme_mode' => $mode])
                ->assertOk()->assertExactJson(['data' => ['receive_app_activity_emails' => false, 'theme_mode' => $mode]]);
            $this->assertStringContainsString('no-store', $saved->headers->get('Cache-Control'));
            $this->assertStringContainsString('private', $saved->headers->get('Cache-Control'));
            $this->assertSame($mode, $owner->fresh()->themeMode());
            $this->assertFalse($owner->fresh()->receivesAppActivityEmails());
        }
        $this->bearer($token)->patchJson('/api/user-settings', ['theme_mode' => 'light', 'receive_app_activity_emails' => true])
            ->assertOk()->assertExactJson(['data' => ['receive_app_activity_emails' => true, 'theme_mode' => 'light']]);
        $this->bearer($token)->getJson('/api/user-settings')->assertOk()
            ->assertExactJson(['data' => ['receive_app_activity_emails' => true, 'theme_mode' => 'light']]);

        $this->assertSame('adaptive', $other->fresh()->themeMode());
        $this->assertDatabaseCount('user_settings', 1);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('jobs', 0);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Notification::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_invalid_or_empty_theme_updates_preserve_both_saved_preferences(): void
    {
        $owner = User::factory()->create();
        $owner->settings()->create(['receive_app_activity_emails' => false, 'theme_mode' => 'dark']);
        $token = ApiToken::issue($owner);
        foreach (['sepia', null, '', [], 0, true] as $invalid) {
            $this->bearer($token)->patchJson('/api/user-settings', [
                'theme_mode' => $invalid, 'receive_app_activity_emails' => true,
            ])->assertUnprocessable()->assertJsonValidationErrors('theme_mode');
            $this->assertSame('dark', $owner->fresh()->themeMode());
            $this->assertFalse($owner->fresh()->receivesAppActivityEmails());
        }
        foreach ([[], ['unsupported_setting' => 'light']] as $payload) {
            $this->bearer($token)->patchJson('/api/user-settings', $payload)->assertUnprocessable()
                ->assertJsonValidationErrors(['theme_mode', 'receive_app_activity_emails']);
        }
        $this->bearer($token)->patchJson('/api/user-settings', ['theme_mode' => 'light', 'receive_app_activity_emails' => null])
            ->assertUnprocessable()->assertJsonValidationErrors('receive_app_activity_emails');
        $this->assertSame('dark', $owner->fresh()->themeMode());
        $this->assertFalse($owner->fresh()->receivesAppActivityEmails());
    }

    public function test_activity_is_actor_scoped_sanitized_and_marking_read_is_persistent_and_idempotent(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ownNotice = $this->notice($owner, 'Own device');
        $foreignNotice = $this->notice($other, 'Private other device');
        $token = ApiToken::issue($owner);

        $feed = $this->bearer($token)->getJson('/api/app-activity')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ownNotice->id)
            ->assertJsonPath('unread_count', 1)->assertJsonPath('next_cursor', null)
            ->assertDontSee('Private other device');
        $this->assertStringContainsString('no-store', $feed->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $feed->headers->get('Cache-Control'));
        $keys = array_keys($feed->json('data.0'));
        sort($keys);
        $this->assertSame(['body', 'created_at', 'id', 'read_at', 'title', 'url'], $keys);
        $this->assertSame(null, $feed->json('data.0.read_at'));
        $this->assertNotEmpty($feed->json('data.0.title'));
        $this->assertStringContainsString('online', $feed->json('data.0.body'));
        $this->assertStringContainsString('offline', $feed->json('data.0.body'));
        $this->assertNotNull(Carbon::parse($feed->json('data.0.created_at')));
        $this->assertNull($ownNotice->fresh()->read_at);

        $this->bearer($token)->patchJson('/api/app-activity/'.$ownNotice->id.'/read')
            ->assertOk()->assertJsonPath('data.id', $ownNotice->id)->assertJsonPath('unread_count', 0);
        $readAt = $ownNotice->fresh()->read_at;
        $this->assertNotNull($readAt);
        $this->travel(1)->minutes();
        $this->bearer($token)->patchJson('/api/app-activity/'.$ownNotice->id.'/read')->assertOk();
        $this->assertEquals($readAt, $ownNotice->fresh()->read_at);
        $this->bearer($token)->getJson('/api/app-activity')->assertOk()->assertJsonPath('unread_count', 0);
        $this->bearer($token)->patchJson('/api/app-activity/'.$foreignNotice->id.'/read')->assertNotFound();
        $this->assertNull($foreignNotice->fresh()->read_at);
    }

    public function test_concurrent_read_preserves_the_first_recorded_timestamp(): void
    {
        $owner = User::factory()->create();
        $notice = $this->notice($owner);
        $token = ApiToken::issue($owner);
        $this->travel(2)->seconds();
        $firstReadAt = now()->subSecond();
        $interleaved = false;

        DatabaseNotification::retrieved(function (DatabaseNotification $loaded) use ($notice, $firstReadAt, &$interleaved) {
            if ($loaded->id === $notice->id && !$interleaved) {
                $interleaved = true;
                // Another request marks it read after this request loaded an unread copy.
                DB::table('notifications')->where('id', $notice->id)->update(['read_at' => $firstReadAt]);
            }
        });

        $this->bearer($token)->patchJson('/api/app-activity/'.$notice->id.'/read')->assertOk()
            ->assertJsonPath('data.read_at', $firstReadAt->toISOString())->assertJsonPath('unread_count', 0);

        $this->assertTrue($interleaved);
        $this->assertEquals($firstReadAt, $notice->fresh()->read_at);
    }

    public function test_dismiss_and_clear_only_remove_the_current_users_activity(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $first = $this->notice($owner);
        $second = $this->notice($owner);
        $foreign = $this->notice($other);
        $token = ApiToken::issue($owner);

        $this->bearer($token)->deleteJson('/api/app-activity/'.$foreign->id)->assertNotFound();
        $this->bearer($token)->deleteJson('/api/app-activity/'.$first->id)->assertOk()->assertJsonPath('unread_count', 1);
        $this->assertDatabaseMissing('notifications', ['id' => $first->id]);
        $this->bearer($token)->deleteJson('/api/app-activity/'.$first->id)->assertNotFound();
        $this->bearer($token)->deleteJson('/api/app-activity')->assertOk()->assertJsonPath('unread_count', 0);
        $this->assertDatabaseMissing('notifications', ['id' => $second->id]);
        $this->assertDatabaseHas('notifications', ['id' => $foreign->id, 'read_at' => null]);
        $this->bearer($token)->getJson('/api/app-activity')->assertOk()
            ->assertExactJson(['data' => [], 'unread_count' => 0, 'next_cursor' => null]);
    }

    /** @dataProvider activityTimestampModes */
    public function test_activity_cursor_pagination_is_stable_and_counts_all_unread_items(bool $sameTimestamp): void
    {
        $owner = User::factory()->create();
        $expectedIds = [];
        for ($index = 0; $index < 23; $index++) {
            if (!$sameTimestamp) {
                $this->travel(1)->seconds();
            }
            $expectedIds[] = $this->notice($owner, 'Device '.$index)->id;
        }
        $token = ApiToken::issue($owner);
        $first = $this->bearer($token)->getJson('/api/app-activity')->assertOk()
            ->assertJsonCount(20, 'data')->assertJsonPath('unread_count', 23);
        $this->assertNotEmpty($first->json('next_cursor'));
        $next = $this->bearer($token)->getJson('/api/app-activity?cursor='.urlencode($first->json('next_cursor')))
            ->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('unread_count', 23)
            ->assertJsonPath('next_cursor', null);
        $actualIds = array_merge(array_column($first->json('data'), 'id'), array_column($next->json('data'), 'id'));
        if ($sameTimestamp) {
            rsort($expectedIds, SORT_STRING);
        } else {
            $expectedIds = array_reverse($expectedIds);
        }
        $this->assertSame($expectedIds, $actualIds);
        $this->assertCount(23, array_unique($actualIds));
        $this->bearer($token)->getJson('/api/app-activity?cursor=invalid-cursor')->assertUnprocessable();
    }

    public static function activityTimestampModes(): array
    {
        return ['distinct timestamps' => [false], 'same timestamp with UUID tie break' => [true]];
    }

    public function test_activity_feed_never_returns_an_unsafe_stored_navigation_url(): void
    {
        $owner = User::factory()->create();
        $notice = $this->notice($owner);
        $notice->update(['data' => array_merge($notice->data, ['url' => 'javascript:alert(1)'])]);

        $this->bearer(ApiToken::issue($owner))->getJson('/api/app-activity')->assertOk()
            ->assertJsonPath('data.0.url', null)->assertDontSee('javascript:');
    }

    public function test_external_keys_have_matching_settings_and_feed_behavior_for_the_developer_only(): void
    {
        $developer = User::factory()->create(['email' => 'activity-developer@example.test']);
        config(['app.developer_email' => $developer->email]);
        $owner = User::factory()->create();
        $ownNotice = $this->notice($developer, 'Developer device');
        $foreignNotice = $this->notice($owner, 'Owner private device');
        [, $secret] = ExternalApiKey::issue($developer, 'Activity integration', null);
        $appToken = ApiToken::issue($developer);

        $externalFeed = $this->bearer($secret)->getJson('/api/external/v1/app-activity')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ownNotice->id);
        $applicationFeed = $this->bearer($appToken)->getJson('/api/app-activity')->assertOk();
        $this->assertSame($applicationFeed->json(), $externalFeed->json());
        $this->bearer($secret)->getJson('/api/external/v1/user-settings')->assertOk()
            ->assertExactJson(['data' => ['receive_app_activity_emails' => true, 'theme_mode' => 'adaptive']]);
        $this->bearer($secret)->patchJson('/api/external/v1/user-settings', [
            'receive_app_activity_emails' => false, 'user_id' => $owner->id,
        ])->assertOk()->assertJsonPath('data.receive_app_activity_emails', false);
        $this->assertTrue($owner->fresh()->receivesAppActivityEmails());
        $this->bearer($appToken)->getJson('/api/user-settings')->assertOk()
            ->assertJsonPath('data.receive_app_activity_emails', false);
        $this->bearer($secret)->patchJson('/api/external/v1/user-settings', ['theme_mode' => 'dark'])
            ->assertOk()->assertExactJson(['data' => ['receive_app_activity_emails' => false, 'theme_mode' => 'dark']]);
        $this->bearer($appToken)->getJson('/api/user-settings')->assertOk()
            ->assertExactJson(['data' => ['receive_app_activity_emails' => false, 'theme_mode' => 'dark']]);
        $this->bearer($appToken)->patchJson('/api/user-settings', ['theme_mode' => 'light'])->assertOk();
        $this->bearer($secret)->getJson('/api/external/v1/user-settings')->assertOk()
            ->assertExactJson(['data' => ['receive_app_activity_emails' => false, 'theme_mode' => 'light']]);
        $this->bearer($secret)->patchJson('/api/external/v1/user-settings', ['theme_mode' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors('theme_mode');
        $this->assertSame('adaptive', $owner->fresh()->themeMode());
        $this->bearer($secret)->patchJson('/api/external/v1/app-activity/'.$foreignNotice->id.'/read')->assertNotFound();
        $this->bearer($secret)->deleteJson('/api/external/v1/app-activity/'.$foreignNotice->id)->assertNotFound();
        $this->bearer($secret)->patchJson('/api/external/v1/app-activity/'.$ownNotice->id.'/read')
            ->assertOk()->assertJsonPath('unread_count', 0);
        $this->bearer($secret)->deleteJson('/api/external/v1/app-activity/'.$ownNotice->id)->assertOk();
        $this->notice($developer);
        $this->bearer($secret)->deleteJson('/api/external/v1/app-activity')->assertOk();
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', ['id' => $foreignNotice->id, 'read_at' => null]);

        $endpoints = $this->bearer($secret)->getJson('/api/external/v1')->assertOk()->json('endpoints');
        foreach (['GET /api/external/v1/user-settings', 'PATCH /api/external/v1/user-settings',
            'GET /api/external/v1/app-activity', 'PATCH /api/external/v1/app-activity/{id}/read',
            'DELETE /api/external/v1/app-activity/{id}', 'DELETE /api/external/v1/app-activity'] as $endpoint) {
            $this->assertContains($endpoint, $endpoints);
        }
    }

    public function test_guests_unverified_users_and_wrong_token_types_cannot_access_activity_or_settings(): void
    {
        $developer = User::factory()->create(['email' => 'activity-developer@example.test']);
        config(['app.developer_email' => $developer->email]);
        [, $secret] = ExternalApiKey::issue($developer, 'Activity integration', null);
        foreach (['/api/user-settings', '/api/app-activity'] as $path) {
            $this->getJson($path)->assertUnauthorized();
            $this->bearer($secret)->getJson($path)->assertUnauthorized();
        }
        foreach (['user-settings', 'app-activity'] as $path) {
            $this->bearer(ApiToken::issue($developer))->getJson('/api/external/v1/'.$path)->assertUnauthorized();
        }
        $unverified = User::factory()->create(['email_verified_at' => null]);
        foreach (['/api/user-settings', '/api/app-activity'] as $path) {
            $this->bearer(ApiToken::issue($unverified))->getJson($path)->assertForbidden();
        }
    }

    public function test_activity_email_opt_out_keeps_password_reset_and_verification_emails_available(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['email_verified_at' => null]);
        $owner->settings()->create(['receive_app_activity_emails' => false]);

        $this->from('/login')->post('/email/resend', ['email' => $owner->email])->assertRedirect('/login');
        $this->from('/password/reset')->post('/password/email', ['email' => $owner->email])
            ->assertRedirect('/password/reset')->assertSessionHasNoErrors();

        Notification::assertSentToTimes($owner, CustomVerifyEmail::class, 1);
        Notification::assertSentToTimes($owner, ResetPassword::class, 1);
        $this->assertFalse($owner->fresh()->receivesAppActivityEmails());
    }

    public function test_browser_activity_and_settings_writes_require_csrf_and_external_routes_ignore_sessions(): void
    {
        $this->app->bind(\App\Http\Middleware\VerifyCsrfToken::class, function ($app) {
            return new class($app, $app['encrypter']) extends \App\Http\Middleware\VerifyCsrfToken {
                protected function runningUnitTests() { return false; }
            };
        });
        $owner = User::factory()->create();
        $notice = $this->notice($owner);
        $session = [Auth::guard('web')->getName() => $owner->id, '_token' => 'activity-csrf'];
        Auth::forgetGuards();
        $this->withSession($session)->withHeader('Origin', 'http://localhost')
            ->getJson('/api/user-settings')->assertOk();
        Auth::forgetGuards();
        $this->patchJson('/api/user-settings', ['receive_app_activity_emails' => false])->assertStatus(419);
        Auth::forgetGuards();
        $this->patchJson('/api/app-activity/'.$notice->id.'/read')->assertStatus(419);
        Auth::forgetGuards();
        $this->deleteJson('/api/app-activity')->assertStatus(419);
        $this->assertNull($notice->fresh()->read_at);
        $this->assertTrue($owner->fresh()->receivesAppActivityEmails());
        Auth::forgetGuards();
        $this->patchJson('/api/user-settings', [
            '_token' => 'activity-csrf', 'receive_app_activity_emails' => false,
        ])->assertOk();
        Auth::forgetGuards();
        $this->patchJson('/api/app-activity/'.$notice->id.'/read', ['_token' => 'activity-csrf'])->assertOk();
        Auth::forgetGuards();
        $this->getJson('/api/external/v1/user-settings')->assertUnauthorized();
        Auth::forgetGuards();
        $this->getJson('/api/external/v1/app-activity')->assertUnauthorized();
    }

    private function notice(User $owner, string $deviceName = 'Activity fixture'): DatabaseNotification
    {
        $device = Device::factory()->create(['user_id' => $owner->id, 'name' => $deviceName]);
        $notification = new DeviceStatusChangedActivity($device, 'online', 'offline');
        $notification->id = (string) Str::uuid();
        $owner->notify($notification);

        return DatabaseNotification::findOrFail($notification->id);
    }

    private function bearer(string $token): self
    {
        Auth::forgetGuards();
        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
