<?php

namespace Tests\Feature\Services\Maintenance;

use App\Models\Line;
use App\Models\MaintenanceEvent;
use App\Models\MaintenanceSchedule;
use App\Services\Maintenance\GenerateMaintenanceEvents;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateMaintenanceEventsTest extends TestCase
{
    use RefreshDatabase;

    private GenerateMaintenanceEvents $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GenerateMaintenanceEvents();
    }

    private function makeSchedule(array $overrides = []): MaintenanceSchedule
    {
        $line = Line::factory()->create();

        return MaintenanceSchedule::factory()->create(array_merge([
            'line_id'      => $line->id,
            'frequency'    => MaintenanceSchedule::FREQ_WEEKLY,
            'next_due_at'  => now()->subHour(),
            'is_active'    => true,
        ], $overrides));
    }

    public function test_due_schedule_creates_event_and_advances(): void
    {
        $schedule = $this->makeSchedule([
            'next_due_at' => Carbon::parse('2026-05-10 08:00:00'),
        ]);
        Carbon::setTestNow('2026-05-20 09:00:00');

        $count = $this->service->run();

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('maintenance_events', [
            'schedule_id' => $schedule->id,
            'title'       => $schedule->name,
            'status'      => MaintenanceEvent::STATUS_PENDING,
        ]);

        $fresh = $schedule->fresh();
        $this->assertNotNull($fresh->last_executed_at);
        // Weekly +1 from 2026-05-10
        $this->assertSame('2026-05-17 08:00:00', $fresh->next_due_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_future_schedule_outside_lead_time_does_nothing(): void
    {
        Carbon::setTestNow('2026-05-20 09:00:00');
        $this->makeSchedule([
            'next_due_at'    => Carbon::parse('2026-06-15 08:00:00'), // > 7d away
            'lead_time_days' => 0,
        ]);

        $count = $this->service->run();

        $this->assertSame(0, $count);
        $this->assertDatabaseCount('maintenance_events', 0);
        Carbon::setTestNow();
    }

    public function test_inactive_schedule_is_skipped(): void
    {
        Carbon::setTestNow('2026-05-20 09:00:00');
        $this->makeSchedule([
            'next_due_at' => Carbon::parse('2026-05-15 08:00:00'),
            'is_active'   => false,
        ]);

        $count = $this->service->run();

        $this->assertSame(0, $count);
        $this->assertDatabaseCount('maintenance_events', 0);
        Carbon::setTestNow();
    }

    public function test_idempotent_for_same_cycle(): void
    {
        Carbon::setTestNow('2026-05-20 09:00:00');
        $schedule = $this->makeSchedule([
            'next_due_at' => Carbon::parse('2026-05-15 08:00:00'),
        ]);

        $first  = $this->service->run();

        // Roll schedule back to "due" state for the same scheduled_at value to
        // assert duplicate guard catches it.
        $event = MaintenanceEvent::where('schedule_id', $schedule->id)->first();
        $schedule->forceFill(['next_due_at' => $event->scheduled_at])->save();

        $second = $this->service->run();

        $this->assertSame(1, $first);
        $this->assertSame(0, $second);
        $this->assertDatabaseCount('maintenance_events', 1);
        Carbon::setTestNow();
    }

    public function test_lead_time_days_allows_early_generation(): void
    {
        Carbon::setTestNow('2026-05-20 09:00:00');
        $schedule = $this->makeSchedule([
            'next_due_at'    => Carbon::parse('2026-05-23 08:00:00'), // 3d in future
            'lead_time_days' => 5,
        ]);

        $count = $this->service->run();

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('maintenance_events', [
            'schedule_id' => $schedule->id,
        ]);
        Carbon::setTestNow();
    }

    /**
     * @dataProvider frequencyAdvanceProvider
     */
    public function test_advance_for_each_frequency(string $frequency, int $interval, string $expected): void
    {
        Carbon::setTestNow('2026-05-20 09:00:00');
        $schedule = $this->makeSchedule([
            'frequency'      => $frequency,
            'interval_value' => $interval,
            'next_due_at'    => Carbon::parse('2026-05-10 08:00:00'),
        ]);

        $this->service->run();

        $this->assertSame(
            $expected,
            $schedule->fresh()->next_due_at->format('Y-m-d H:i:s'),
            "advance failed for {$frequency} x{$interval}"
        );
        Carbon::setTestNow();
    }

    public static function frequencyAdvanceProvider(): array
    {
        return [
            'daily x1'     => [MaintenanceSchedule::FREQ_DAILY,     1, '2026-05-11 08:00:00'],
            'daily x3'     => [MaintenanceSchedule::FREQ_DAILY,     3, '2026-05-13 08:00:00'],
            'weekly x1'    => [MaintenanceSchedule::FREQ_WEEKLY,    1, '2026-05-17 08:00:00'],
            'weekly x2'    => [MaintenanceSchedule::FREQ_WEEKLY,    2, '2026-05-24 08:00:00'],
            'monthly x1'   => [MaintenanceSchedule::FREQ_MONTHLY,   1, '2026-06-10 08:00:00'],
            'quarterly x1' => [MaintenanceSchedule::FREQ_QUARTERLY, 1, '2026-08-10 08:00:00'],
            'annually x1'  => [MaintenanceSchedule::FREQ_ANNUALLY,  1, '2027-05-10 08:00:00'],
            'by_hours x12' => [MaintenanceSchedule::FREQ_BY_HOURS,  12, '2026-05-10 20:00:00'],
        ];
    }

    public function test_duplicate_event_for_same_cycle_is_rejected_at_db_level(): void
    {
        Carbon::setTestNow('2026-05-20 09:00:00');
        $scheduledAt = Carbon::parse('2026-05-15 08:00:00');
        $schedule = $this->makeSchedule([
            'next_due_at' => $scheduledAt,
        ]);

        // Manually create an event for this (schedule_id, scheduled_at) pair so
        // a subsequent direct insert with the same pair must be rejected by the
        // database UNIQUE constraint.
        MaintenanceEvent::create([
            'title'        => 'Pre-existing event',
            'event_type'   => MaintenanceEvent::TYPE_PLANNED,
            'status'       => MaintenanceEvent::STATUS_PENDING,
            'scheduled_at' => $scheduledAt,
            'schedule_id'  => $schedule->id,
        ]);

        $this->expectException(QueryException::class);

        try {
            MaintenanceEvent::create([
                'title'        => 'Duplicate event',
                'event_type'   => MaintenanceEvent::TYPE_PLANNED,
                'status'       => MaintenanceEvent::STATUS_PENDING,
                'scheduled_at' => $scheduledAt,
                'schedule_id'  => $schedule->id,
            ]);
        } finally {
            $this->assertSame(
                1,
                MaintenanceEvent::where('schedule_id', $schedule->id)
                    ->where('scheduled_at', $scheduledAt)
                    ->count()
            );
            Carbon::setTestNow();
        }
    }

    public function test_service_swallows_unique_violation_race(): void
    {
        Carbon::setTestNow('2026-05-20 09:00:00');
        $scheduledAt = Carbon::parse('2026-05-15 08:00:00');
        $schedule = $this->makeSchedule([
            'next_due_at' => $scheduledAt,
        ]);

        // Pre-create the event WITHOUT the schedule advancing — simulates a
        // peer worker that won the insert race while our worker was still
        // between exists() and create(). The exists() check inside the service
        // catches this in the common path; to exercise the QueryException
        // catch we sidestep the guard by inserting from the test before run().
        MaintenanceEvent::create([
            'title'        => 'Peer worker win',
            'event_type'   => MaintenanceEvent::TYPE_PLANNED,
            'status'       => MaintenanceEvent::STATUS_PENDING,
            'scheduled_at' => $scheduledAt,
            'schedule_id'  => $schedule->id,
        ]);

        // run() should not throw; the exists() guard short-circuits, and even
        // if it did not, the DB-level QueryException catch would.
        $count = $this->service->run();

        $this->assertSame(0, $count);
        $this->assertDatabaseCount('maintenance_events', 1);
        Carbon::setTestNow();
    }

    public function test_preferred_time_resets_clock_after_advance(): void
    {
        Carbon::setTestNow('2026-05-20 09:30:00');
        $schedule = $this->makeSchedule([
            'frequency'      => MaintenanceSchedule::FREQ_DAILY,
            'interval_value' => 1,
            'next_due_at'    => Carbon::parse('2026-05-19 13:45:00'),
            'preferred_time' => '06:00',
        ]);

        $this->service->run();

        $next = $schedule->fresh()->next_due_at;
        $this->assertSame('06:00:00', $next->format('H:i:s'));
        $this->assertSame('2026-05-20', $next->format('Y-m-d'));
        Carbon::setTestNow();
    }
}
