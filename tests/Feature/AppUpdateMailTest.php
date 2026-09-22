<?php

namespace Tests\Feature;

use App\Jobs\SendAppUpdateMail;
use App\Mail\AppUpdateMail;
use App\Mail\LowVoltageMail;
use App\Mail\TheftVandalismMail;
use App\Models\Device;
use App\Models\User;
use App\Services\AppUpdateDelivery;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use RuntimeException;
use Tests\TestCase;

class AppUpdateMailTest extends TestCase
{
    use RefreshDatabase;

    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mail.default' => 'array', 'mail.from.address' => 'updates@example.test',
            'mailpacing.per_minute' => 100,
        ]);
        Event::listen(MessageSent::class, function (MessageSent $event): void {
            $this->sent[] = $event->message;
        });
    }

    public function test_delivery_uses_the_encrypted_database_mail_queue_and_deduplicates_users(): void
    {
        $first = User::factory()->create(['email' => 'first@example.test']);
        $second = User::factory()->create(['email' => 'second@example.test']);
        $mail = (new FixtureAppUpdateMail())
            ->to('unexpected-to@example.test')
            ->cc('unexpected-cc@example.test')
            ->bcc('unexpected-bcc@example.test');

        $count = app(AppUpdateDelivery::class)->queue(
            collect([$first, $first->fresh(), $second, new User(['email' => 'unsaved@example.test'])]),
            $mail
        );

        $this->assertSame(2, $count);
        $this->assertCount(0, $this->sent);
        $this->assertSame('database', config('queue.connections.app-updates.driver'));
        $rows = DB::table('jobs')->orderBy('id')->get();
        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertSame('mail', $row->queue);
            $payload = json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame(SendAppUpdateMail::class, $payload['displayName']);
            foreach (['first@example.test', 'second@example.test', 'unexpected-', FixtureAppUpdateMail::PRIVATE_TEXT] as $private) {
                $this->assertStringNotContainsString($private, $row->payload);
            }
            $job = unserialize(app('encrypter')->decrypt($payload['data']['command']));
            $this->assertInstanceOf(SendAppUpdateMail::class, $job);
            $this->assertInstanceOf(ShouldBeEncrypted::class, $job);
        }

        $this->workOnce();
        $this->workOnce();

        $this->assertCount(2, $this->sent);
        $this->assertSame(['first@example.test'], array_keys($this->sent[0]->getTo()));
        $this->assertSame(['second@example.test'], array_keys($this->sent[1]->getTo()));
        foreach ($this->sent as $message) {
            $this->assertEmpty($message->getCc());
            $this->assertEmpty($message->getBcc());
        }
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
        $this->assertSame('unexpected-to@example.test', $mail->to[0]['address']);
        $this->assertSame('unexpected-cc@example.test', $mail->cc[0]['address']);
        $this->assertSame('unexpected-bcc@example.test', $mail->bcc[0]['address']);
    }

    public function test_the_worker_resolves_the_current_email_and_sends_queueable_mail_once(): void
    {
        $user = User::factory()->create(['email' => 'old@example.test']);
        app(AppUpdateDelivery::class)->queue([$user], new FixtureAppUpdateMail());
        $user->update(['email' => 'current@example.test']);

        $this->workOnce();

        $this->assertCount(1, $this->sent);
        $this->assertSame(['current@example.test'], array_keys($this->sent[0]->getTo()));
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
    }

    public function test_deleted_users_and_currently_invalid_email_addresses_are_skipped(): void
    {
        $deleted = User::factory()->create();
        $invalid = User::factory()->create();
        app(AppUpdateDelivery::class)->queue([$deleted, $invalid], new FixtureAppUpdateMail());
        $deleted->delete();
        $invalid->update(['email' => 'invalid-address']);

        $this->workOnce();
        $this->workOnce();

        $this->assertCount(0, $this->sent);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
    }

    public function test_rate_limited_mail_is_released_and_delivered_after_the_limit_resets(): void
    {
        RateLimiter::for('outbound-mail', fn () => Limit::perMinute(1)->by('bounded-outbound-mail'));
        app(AppUpdateDelivery::class)->queue(User::factory()->count(2)->create(), new FixtureAppUpdateMail());

        $this->workOnce();
        $this->workOnce();

        $this->assertCount(1, $this->sent);
        $pending = DB::table('jobs')->sole();
        $this->assertSame(1, (int) $pending->attempts);
        $this->assertGreaterThan(now()->timestamp, (int) $pending->available_at);
        $this->assertDatabaseCount('failed_jobs', 0);

        $this->travel(65)->seconds();
        $this->workOnce();

        $this->assertCount(2, $this->sent);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_delivery_failures_are_retried_with_backoff_then_delivered(): void
    {
        $fail = true;
        Event::listen(MessageSending::class, function () use (&$fail): void {
            if ($fail) {
                throw new RuntimeException('Simulated local mail transport failure.');
            }
        });
        app(AppUpdateDelivery::class)->queue([User::factory()->create()], new FixtureAppUpdateMail());

        $this->workOnce();

        $this->assertCount(0, $this->sent);
        $pending = DB::table('jobs')->sole();
        $this->assertSame(1, (int) $pending->attempts);
        $this->assertEqualsWithDelta(now()->addMinute()->timestamp, (int) $pending->available_at, 2);
        $this->assertDatabaseCount('failed_jobs', 0);

        $fail = false;
        $this->travel(65)->seconds();
        $this->workOnce();

        $this->assertCount(1, $this->sent);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
    }

    public function test_retry_deadline_is_fixed_when_the_job_is_created(): void
    {
        $job = new SendAppUpdateMail(1, new FixtureAppUpdateMail());
        $deadline = $job->retryUntil();

        $this->assertEqualsWithDelta(now()->addDay()->timestamp, $deadline, 1);
        $this->assertSame(0, $job->tries);
        $this->assertSame(5, $job->maxExceptions);
        $this->assertSame(30, $job->timeout);
        $this->assertSame([60, 120, 300, 600], $job->backoff());
        $this->travel(3)->hours();
        $this->assertSame($deadline, $job->retryUntil());
    }

    public function test_repeated_delivery_exceptions_eventually_fail_with_an_encrypted_payload(): void
    {
        Event::listen(MessageSending::class, function (): void {
            throw new RuntimeException('Simulated persistent local transport failure.');
        });
        $user = User::factory()->create(['email' => 'private-failed-recipient@example.test']);
        app(AppUpdateDelivery::class)->queue([$user], new FixtureAppUpdateMail());

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->workOnce();
            $this->travel(11)->minutes();
        }

        $this->assertCount(0, $this->sent);
        $this->assertDatabaseCount('jobs', 0);
        $failure = DB::table('failed_jobs')->sole();
        $this->assertSame('app-updates', $failure->connection);
        $this->assertSame('mail', $failure->queue);
        $this->assertStringNotContainsString($user->email, $failure->payload);
        $this->assertStringNotContainsString(FixtureAppUpdateMail::PRIVATE_TEXT, $failure->payload);
        $payload = json_decode($failure->payload, true, 512, JSON_THROW_ON_ERROR);
        $this->assertInstanceOf(SendAppUpdateMail::class, unserialize(app('encrypter')->decrypt($payload['data']['command'])));
    }

    /** @dataProvider deviceMailClasses */
    public function test_device_alerts_recheck_current_ownership_preferences_and_device_existence(string $mailClass): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $devices = [];
        foreach (['valid', 'transferred', 'opted-out', 'deleted'] as $state) {
            $devices[$state] = Device::create([
                'user_id' => $owner->id,
                'serial_no' => 'mail-'.$state,
                'status_notification' => true,
            ]);
            app(AppUpdateDelivery::class)->queue([$owner], new $mailClass(
                $owner->name, 'mail-'.$state, 'Private fixture address', 34.0522, -118.2437
            ));
        }
        $devices['transferred']->update(['user_id' => $other->id]);
        $devices['opted-out']->update(['status_notification' => false]);
        $devices['deleted']->update(['state' => 'archived']);

        foreach ($devices as $device) {
            $this->workOnce();
        }

        $this->assertCount(1, $this->sent);
        $this->assertSame([$owner->email], array_keys($this->sent[0]->getTo()));
        $this->assertStringContainsString('mail-valid', $this->sent[0]->getBody());
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
    }

    public static function deviceMailClasses(): array
    {
        return [
            'low voltage' => [LowVoltageMail::class],
            'theft or vandalism' => [TheftVandalismMail::class],
        ];
    }

    private function workOnce(): void
    {
        $exitCode = Artisan::call('queue:work', [
            'connection' => 'app-updates', '--queue' => 'mail', '--once' => true,
            '--sleep' => 0, '--tries' => 0,
        ]);
        $this->assertSame(0, $exitCode, Artisan::output());
    }
}

// Deliberately queueable: the outer job must send it directly, without creating
// a second SendQueuedMailable job that bypasses the dedicated mail middleware.
class FixtureAppUpdateMail extends AppUpdateMail implements ShouldQueue
{
    public const PRIVATE_TEXT = 'Private test address 123 Example Avenue';

    public string $privateText = self::PRIVATE_TEXT;

    public function build()
    {
        return $this->subject('Application update')->html('<p>'.$this->privateText.'</p>');
    }
}
