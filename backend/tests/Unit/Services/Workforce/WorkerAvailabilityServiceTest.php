<?php

namespace Tests\Unit\Services\Workforce;

use App\Models\Crew;
use App\Models\CrewBreakWindow;
use App\Models\Worker;
use App\Models\WorkerAbsence;
use App\Services\Workforce\WorkerAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WorkerAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private WorkerAvailabilityService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new WorkerAvailabilityService;
    }

    public function test_worker_is_unavailable_during_an_approved_absence(): void
    {
        $worker = Worker::factory()->create();
        WorkerAbsence::factory()->create([
            'worker_id' => $worker->id,
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-07-05',
            'status' => 'approved',
        ]);

        $this->assertFalse($this->svc->isAvailable($worker, now()->parse('2026-07-03'), now()->parse('2026-07-03')));
        $this->assertTrue($this->svc->isAvailable($worker, now()->parse('2026-07-10'), now()->parse('2026-07-10')));
        $this->assertTrue($this->svc->isAbsentOn($worker, now()->parse('2026-07-01')));
        $this->assertFalse($this->svc->isAbsentOn($worker, now()->parse('2026-06-30')));
    }

    public function test_pending_or_rejected_absences_do_not_make_a_worker_unavailable(): void
    {
        $worker = Worker::factory()->create();
        WorkerAbsence::factory()->create([
            'worker_id' => $worker->id,
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-07-05',
            'status' => 'pending',
        ]);

        $this->assertTrue($this->svc->isAvailable($worker, now()->parse('2026-07-03'), now()->parse('2026-07-03')));
    }

    public function test_absent_worker_ids_lists_only_approved_absentees_on_the_date(): void
    {
        $absent = Worker::factory()->create();
        $present = Worker::factory()->create();

        WorkerAbsence::factory()->create([
            'worker_id' => $absent->id,
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-07-05',
            'status' => 'approved',
        ]);

        $ids = $this->svc->absentWorkerIds(now()->parse('2026-07-02'));

        $this->assertContains($absent->id, $ids);
        $this->assertNotContains($present->id, $ids);
    }

    public function test_worker_is_on_break_only_inside_the_crew_break_window(): void
    {
        $moment = Carbon::parse('2026-07-01 12:15');
        $crew = Crew::factory()->create();
        $worker = Worker::factory()->create(['crew_id' => $crew->id]);

        CrewBreakWindow::factory()->create([
            'crew_id' => $crew->id,
            'start_time' => '12:00',
            'end_time' => '12:30',
            'days_of_week' => [$moment->dayOfWeekIso],
            'is_active' => true,
        ]);

        $this->assertTrue($this->svc->isOnBreak($worker, $moment));
        $this->assertFalse($this->svc->isOnBreak($worker, $moment->copy()->setTime(11, 0)));
        $this->assertFalse($this->svc->isOnBreak($worker, $moment->copy()->setTime(12, 30))); // end is exclusive
        $this->assertFalse($this->svc->isOnBreak($worker, $moment->copy()->setTime(13, 0)));
    }

    public function test_break_window_on_a_different_weekday_does_not_apply(): void
    {
        $moment = Carbon::parse('2026-07-01 12:15');
        $crew = Crew::factory()->create();
        $worker = Worker::factory()->create(['crew_id' => $crew->id]);

        CrewBreakWindow::factory()->create([
            'crew_id' => $crew->id,
            'start_time' => '12:00',
            'end_time' => '12:30',
            'days_of_week' => [$moment->dayOfWeekIso % 7 + 1], // some other weekday
            'is_active' => true,
        ]);

        $this->assertFalse($this->svc->isOnBreak($worker, $moment));
    }

    public function test_inactive_break_window_and_crewless_worker_are_never_on_break(): void
    {
        $moment = Carbon::parse('2026-07-01 12:15');
        $crew = Crew::factory()->create();
        $worker = Worker::factory()->create(['crew_id' => $crew->id]);
        $crewless = Worker::factory()->create(['crew_id' => null]);

        CrewBreakWindow::factory()->create([
            'crew_id' => $crew->id,
            'start_time' => '12:00',
            'end_time' => '12:30',
            'days_of_week' => [$moment->dayOfWeekIso],
            'is_active' => false,
        ]);

        $this->assertFalse($this->svc->isOnBreak($worker, $moment));   // window inactive
        $this->assertFalse($this->svc->isOnBreak($crewless, $moment));  // no crew
    }

    public function test_crew_break_windows_on_returns_applicable_windows_ordered_by_start(): void
    {
        $date = Carbon::parse('2026-07-01');
        $crew = Crew::factory()->create();

        $afternoon = CrewBreakWindow::factory()->create([
            'crew_id' => $crew->id, 'name' => 'Tea', 'start_time' => '15:00', 'end_time' => '15:15',
            'days_of_week' => [$date->dayOfWeekIso], 'is_active' => true,
        ]);
        $lunch = CrewBreakWindow::factory()->create([
            'crew_id' => $crew->id, 'name' => 'Lunch', 'start_time' => '12:00', 'end_time' => '12:30',
            'days_of_week' => [$date->dayOfWeekIso], 'is_active' => true,
        ]);
        // Different weekday — must be excluded.
        CrewBreakWindow::factory()->create([
            'crew_id' => $crew->id, 'name' => 'Other day', 'start_time' => '09:00', 'end_time' => '09:15',
            'days_of_week' => [$date->dayOfWeekIso % 7 + 1], 'is_active' => true,
        ]);

        $windows = $this->svc->crewBreakWindowsOn($crew, $date);

        $this->assertSame([$lunch->id, $afternoon->id], $windows->pluck('id')->all());
    }
}
