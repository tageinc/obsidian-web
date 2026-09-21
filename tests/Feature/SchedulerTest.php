<?php

namespace Tests\Feature;

use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Mockery;
use ReflectionProperty;
use Tests\TestCase;

class SchedulerTest extends TestCase
{
    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    /** @dataProvider hourlyTimes */
    public function test_status_check_is_due_only_on_the_hour_in_utc(string $time, bool $due): void
    {
        Date::setTestNow(Date::parse($time));
        $schedule = $this->app->make(Schedule::class);
        $event = $this->statusCheck($schedule);

        $this->assertSame('UTC', $event->timezone);
        $this->assertSame($due, $event->isDue($this->app));
        $this->assertTrue($event->withoutOverlapping);
        $this->assertFalse(collect($schedule->events())->contains(function ($scheduled) {
            return strpos($scheduled->command, 'device:generate-info') !== false;
        }));
    }

    public static function hourlyTimes(): array
    {
        return [
            'before the hour' => ['2026-09-21T11:59:00Z', false],
            'on the hour' => ['2026-09-21T12:00:00Z', true],
            'after the hour' => ['2026-09-21T12:01:00Z', false],
            'midnight' => ['2026-09-22T00:00:00Z', true],
            'same instant with an offset' => ['2026-09-21T05:00:00-07:00', true],
        ];
    }

    public function test_schedule_run_dispatches_the_due_kernel_command(): void
    {
        Date::setTestNow(Date::parse('2026-09-21T12:00:00Z'));
        Event::fake([ScheduledTaskStarting::class, ScheduledTaskFinished::class]);
        $schedule = $this->app->make(Schedule::class);
        $event = $this->interceptExecution($schedule);
        $event->shouldReceive('run')->once()->with($this->app);

        $this->artisan('schedule:run')->assertExitCode(0);

        Event::assertDispatched(ScheduledTaskStarting::class, fn ($dispatched) => $dispatched->task === $event);
        Event::assertDispatched(ScheduledTaskFinished::class, fn ($dispatched) => $dispatched->task === $event);
    }

    public function test_schedule_run_does_not_dispatch_the_command_between_hours(): void
    {
        Date::setTestNow(Date::parse('2026-09-21T12:01:00Z'));
        Event::fake([ScheduledTaskStarting::class]);
        $event = $this->interceptExecution($this->app->make(Schedule::class));
        $event->shouldNotReceive('run');

        $this->artisan('schedule:run')
            ->expectsOutput('No scheduled commands are ready to run.')
            ->assertExitCode(0);

        Event::assertNotDispatched(ScheduledTaskStarting::class);
    }

    public function test_schedule_run_skips_a_status_check_that_is_already_running(): void
    {
        Date::setTestNow(Date::parse('2026-09-21T12:00:00Z'));
        Event::fake([ScheduledTaskSkipped::class, ScheduledTaskStarting::class]);
        $schedule = $this->app->make(Schedule::class);
        $scheduled = $this->statusCheck($schedule);
        $this->assertTrue($scheduled->mutex->create($scheduled));
        $event = $this->interceptExecution($schedule);
        $event->shouldNotReceive('run');

        try {
            $this->artisan('schedule:run')->assertExitCode(0);

            Event::assertDispatched(ScheduledTaskSkipped::class, fn ($dispatched) => $dispatched->task === $event);
            Event::assertNotDispatched(ScheduledTaskStarting::class);
            $this->assertTrue($scheduled->mutex->exists($scheduled));
        } finally {
            $scheduled->mutex->forget($scheduled);
        }
    }

    private function statusCheck(Schedule $schedule): ScheduledEvent
    {
        $events = collect($schedule->events())->filter(function ($event) {
            return strpos($event->command, 'device:check-status') !== false;
        });
        $this->assertCount(1, $events);

        return $events->first();
    }

    private function interceptExecution(Schedule $schedule): ScheduledEvent
    {
        // Preserve the real Kernel frequency and overlap filters, but never launch
        // the status subprocess: it reads live devices and may send notifications.
        $event = Mockery::mock($this->statusCheck($schedule));
        $events = new ReflectionProperty(Schedule::class, 'events');
        $events->setAccessible(true);
        $events->setValue($schedule, [$event]);

        return $event;
    }
}
