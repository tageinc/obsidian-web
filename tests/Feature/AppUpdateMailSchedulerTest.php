<?php

namespace Tests\Feature;

use App\Console\Commands\ProcessAppUpdateMail;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Date;
use Mockery;
use Tests\TestCase;

class AppUpdateMailSchedulerTest extends TestCase
{
    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    /** @dataProvider scheduledTimes */
    public function test_mail_worker_is_due_each_minute_on_the_existing_scheduler(string $time): void
    {
        Date::setTestNow(Date::parse($time));
        $event = $this->mailWorker();

        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->isDue($this->app));
        $this->assertSame(storage_path('logs/mail-scheduler.log'), $event->output);
        $this->assertTrue($event->shouldAppendOutput);
    }

    public static function scheduledTimes(): array
    {
        return [
            'on the hour' => ['2026-09-21T12:00:00Z'],
            'between status checks' => ['2026-09-21T12:01:00Z'],
            'midnight' => ['2026-09-22T00:00:00Z'],
        ];
    }

    public function test_mail_worker_does_not_overlap_an_existing_run(): void
    {
        $event = $this->mailWorker();

        $this->assertTrue($event->withoutOverlapping);
        $this->assertSame(5, $event->expiresAt);
        $this->assertTrue($event->filtersPass($this->app));
        $this->assertTrue($event->mutex->create($event));

        try {
            $this->assertFalse($event->filtersPass($this->app));
        } finally {
            $event->mutex->forget($event);
        }

        $this->assertTrue($event->filtersPass($this->app));
    }

    /** @dataProvider workerExitCodes */
    public function test_command_runs_only_the_bounded_app_updates_worker(int $exitCode): void
    {
        // Intercept Artisan delegation: this test never executes an email job.
        $command = Mockery::mock(ProcessAppUpdateMail::class)->makePartial();
        $command->shouldReceive('call')->once()->with('queue:work', [
            'connection' => 'app-updates',
            '--queue' => 'mail',
            '--stop-when-empty' => true,
            '--max-time' => 45,
            '--max-jobs' => 100,
            '--sleep' => 1,
            '--timeout' => 30,
            '--tries' => 0,
        ])->andReturn($exitCode);

        $this->assertSame($exitCode, $command->handle());
    }

    public static function workerExitCodes(): array
    {
        return [
            'completed' => [0],
            'worker failed' => [1],
        ];
    }

    public function test_app_updates_have_a_database_queue_without_changing_the_default(): void
    {
        $this->assertSame('sync', config('queue.default'));
        $this->assertSame([
            'driver' => 'database',
            'connection' => null,
            'table' => 'jobs',
            'queue' => 'mail',
            'retry_after' => 90,
            'after_commit' => false,
        ], config('queue.connections.app-updates'));
    }

    private function mailWorker(): ScheduledEvent
    {
        $events = collect($this->app->make(Schedule::class)->events())
            ->filter(fn ($event) => strpos($event->command, 'mail:work') !== false);
        $this->assertCount(1, $events);

        return $events->first();
    }
}
