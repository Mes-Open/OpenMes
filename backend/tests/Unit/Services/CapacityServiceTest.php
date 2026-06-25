<?php

namespace Tests\Unit\Services;

use App\Models\Crew;
use App\Models\CrewBreakWindow;
use App\Models\Line;
use App\Models\MaintenanceEvent;
use App\Models\Shift;
use App\Models\Worker;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Services\Schedule\CapacityService;
use App\Services\Workforce\WorkerAvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit coverage for the schedule-capacity computation (line axis):
 *
 * - available hours derived from shift duration × matching working days
 * - cross-midnight shifts
 * - planned hours from minute-level plans (distributed across buckets)
 * - planned hours from the estimate fallback chain (lumped on due date)
 * - unestimated orders surfaced as a count, never silently zeroed
 * - load percentage and the "no capacity" (null) case
 */
class CapacityServiceTest extends TestCase
{
    use RefreshDatabase;

    private CapacityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CapacityService(new WorkerAvailabilityService);
    }

    private function crewWithWorker(Line $line, int $workerCount = 1): Crew
    {
        $crew = Crew::factory()->create(['is_active' => true]);
        $ws = Workstation::factory()->create(['line_id' => $line->id, 'is_active' => true]);
        Worker::factory()->count($workerCount)->create([
            'crew_id' => $crew->id,
            'workstation_id' => $ws->id,
            'is_active' => true,
        ]);

        return $crew;
    }

    private function line(array $attrs = []): Line
    {
        return Line::factory()->create(array_merge(['is_active' => true], $attrs));
    }

    /** Mon–Fri shift of the given span on every weekday. */
    private function shift(array $attrs = []): Shift
    {
        return Shift::create(array_merge([
            'name' => 'Day',
            'start_time' => '06:00:00',
            'end_time' => '14:00:00', // 8h
            'days_of_week' => [1, 2, 3, 4, 5],
            'is_active' => true,
            'sort_order' => 1,
        ], $attrs));
    }

    private function cell(array $grid, int $lineId, string $bucketKey): array
    {
        $resource = collect($grid['resources'])->firstWhere('id', $lineId);
        $this->assertNotNull($resource, "resource {$lineId} present");

        return $resource['cells'][$bucketKey];
    }

    public function test_available_hours_from_shifts_over_a_week(): void
    {
        $line = $this->line();
        $this->shift(['line_id' => $line->id]); // 8h × 5 weekdays = 40h

        $monday = Carbon::now()->startOfWeek();
        $grid = $this->service->lineCapacity($monday, $monday->copy()->endOfWeek(), 'week');

        $bucket = $grid['buckets'][0]['key'];
        $cell = $this->cell($grid, $line->id, $bucket);

        $this->assertSame(40.0, $cell['available_h']);
        $this->assertSame(0.0, $cell['planned_h']);
        $this->assertSame(0, $cell['load_pct']);
    }

    public function test_available_hours_with_day_name_strings(): void
    {
        // The demo seeder stores days_of_week as day-name strings rather than
        // the canonical ISO integers; the computation must handle both.
        $line = $this->line();
        $this->shift([
            'line_id' => $line->id,
            'days_of_week' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        ]); // 8h × 5 weekdays = 40h

        $monday = Carbon::now()->startOfWeek();
        $grid = $this->service->lineCapacity($monday, $monday->copy()->endOfWeek(), 'week');

        $cell = $this->cell($grid, $line->id, $grid['buckets'][0]['key']);
        $this->assertSame(40.0, $cell['available_h']);
    }

    public function test_global_shift_applies_to_every_line(): void
    {
        $line = $this->line();
        $this->shift(['line_id' => null]); // global → applies to this line too

        $monday = Carbon::now()->startOfWeek();
        $grid = $this->service->lineCapacity($monday, $monday->copy()->endOfWeek(), 'week');

        $cell = $this->cell($grid, $line->id, $grid['buckets'][0]['key']);
        $this->assertSame(40.0, $cell['available_h']);
    }

    public function test_cross_midnight_shift_counts_full_span(): void
    {
        $line = $this->line();
        // 22:00 → 06:00 = 8h, only on Mondays.
        $this->shift(['line_id' => $line->id, 'start_time' => '22:00:00', 'end_time' => '06:00:00', 'days_of_week' => [1]]);

        $monday = Carbon::now()->startOfWeek();
        $grid = $this->service->lineCapacity($monday, $monday->copy()->endOfWeek(), 'week');

        $cell = $this->cell($grid, $line->id, $grid['buckets'][0]['key']);
        $this->assertSame(8.0, $cell['available_h']);
    }

    public function test_minute_planned_order_contributes_planned_hours(): void
    {
        $line = $this->line();
        $this->shift(['line_id' => $line->id]); // 40h/week

        $monday = Carbon::now()->startOfWeek();
        WorkOrder::factory()->create([
            'line_id' => $line->id,
            'status' => WorkOrder::STATUS_PENDING,
            'planned_start_at' => $monday->copy()->setTimeFromTimeString('06:00:00'),
            'planned_end_at' => $monday->copy()->setTimeFromTimeString('14:00:00'), // 8h
        ]);

        $grid = $this->service->lineCapacity($monday, $monday->copy()->endOfWeek(), 'week');
        $cell = $this->cell($grid, $line->id, $grid['buckets'][0]['key']);

        $this->assertSame(8.0, $cell['planned_h']);
        $this->assertSame(20, $cell['load_pct']); // 8 / 40
        $this->assertSame(0, $cell['unestimated_count']);
    }

    public function test_estimate_fallback_uses_process_snapshot(): void
    {
        $line = $this->line();
        $this->shift(['line_id' => $line->id]);

        $monday = Carbon::now()->startOfWeek();
        WorkOrder::factory()->create([
            'line_id' => $line->id,
            'status' => WorkOrder::STATUS_PENDING,
            'due_date' => $monday->copy()->addDay(),
            'planned_start_at' => null,
            'planned_end_at' => null,
            'process_snapshot' => [
                'steps' => [
                    ['estimated_duration_minutes' => 120],
                    ['estimated_duration_minutes' => 60],
                ],
            ],
        ]);

        $grid = $this->service->lineCapacity($monday, $monday->copy()->endOfWeek(), 'week');
        $cell = $this->cell($grid, $line->id, $grid['buckets'][0]['key']);

        $this->assertSame(3.0, $cell['planned_h']); // 180 min
        $this->assertSame(0, $cell['unestimated_count']);
    }

    public function test_unestimated_order_is_counted_not_zeroed(): void
    {
        $line = $this->line();
        $this->shift(['line_id' => $line->id]);

        $monday = Carbon::now()->startOfWeek();
        WorkOrder::factory()->create([
            'line_id' => $line->id,
            'status' => WorkOrder::STATUS_PENDING,
            'due_date' => $monday->copy()->addDay(),
            'planned_start_at' => null,
            'planned_end_at' => null,
            'process_snapshot' => null,
        ]);

        $grid = $this->service->lineCapacity($monday, $monday->copy()->endOfWeek(), 'week');
        $cell = $this->cell($grid, $line->id, $grid['buckets'][0]['key']);

        $this->assertSame(0.0, $cell['planned_h']);
        $this->assertSame(1, $cell['unestimated_count']);
    }

    public function test_inverted_minute_span_is_surfaced_as_unestimated_not_dropped(): void
    {
        $line = $this->line();
        $this->shift(['line_id' => $line->id]);

        $monday = Carbon::now()->startOfWeek();
        // planned_end_at before planned_start_at — an invalid span the model permits.
        WorkOrder::factory()->create([
            'line_id' => $line->id,
            'status' => WorkOrder::STATUS_PENDING,
            'due_date' => null,
            'planned_start_at' => $monday->copy()->setTime(14, 0),
            'planned_end_at' => $monday->copy()->setTime(10, 0),
        ]);

        $grid = $this->service->lineCapacity($monday, $monday->copy()->endOfWeek(), 'week');
        $cell = $this->cell($grid, $line->id, $grid['buckets'][0]['key']);

        // It must not silently vanish: zero planned hours but surfaced as unestimated.
        $this->assertSame(0.0, $cell['planned_h']);
        $this->assertSame(1, $cell['unestimated_count']);
    }

    public function test_zero_length_shift_contributes_no_hours(): void
    {
        $line = $this->line();
        // start == end must mean a 0-minute shift, not a full 24h day.
        $this->shift(['line_id' => $line->id, 'start_time' => '08:00:00', 'end_time' => '08:00:00']);

        $monday = Carbon::now()->startOfWeek();
        $grid = $this->service->lineCapacity($monday, $monday->copy()->endOfWeek(), 'week');
        $cell = $this->cell($grid, $line->id, $grid['buckets'][0]['key']);

        $this->assertSame(0.0, $cell['available_h']);
    }

    public function test_maintenance_crossing_a_day_boundary_is_clamped_to_the_bucket(): void
    {
        $line = $this->line();
        $this->shift(['line_id' => $line->id, 'days_of_week' => [1, 2, 3, 4, 5, 6, 7]]); // 8h every day

        $day = Carbon::now()->startOfWeek(); // a Monday
        // 4h maintenance 22:00 → 02:00 next day: only ~2h falls inside this day.
        MaintenanceEvent::create([
            'title' => 'Overnight PM',
            'event_type' => 'planned',
            'status' => 'pending',
            'line_id' => $line->id,
            'scheduled_at' => $day->copy()->setTime(22, 0),
            'scheduled_end_at' => $day->copy()->addDay()->setTime(2, 0),
        ]);

        $grid = $this->service->lineCapacity($day, $day, 'day');
        $cell = $this->cell($grid, $line->id, $grid['buckets'][0]['key']);

        // Clamped: 8h − ~2h ≈ 6h. Without clamping it would wrongly subtract the
        // full 4h (→ 4h). Assert the clamp happened rather than an exact second.
        $this->assertGreaterThan(5.0, $cell['available_h']);
        $this->assertLessThan(7.0, $cell['available_h']);
    }

    public function test_load_pct_is_null_when_no_capacity(): void
    {
        $line = $this->line(); // no shifts at all → zero available hours

        $monday = Carbon::now()->startOfWeek();
        $grid = $this->service->lineCapacity($monday, $monday->copy()->endOfWeek(), 'week');
        $cell = $this->cell($grid, $line->id, $grid['buckets'][0]['key']);

        $this->assertSame(0.0, $cell['available_h']);
        $this->assertNull($cell['load_pct']);
    }

    // ── crew axis ──────────────────────────────────────────────────────────

    public function test_crew_available_hours_from_worker_line_shifts(): void
    {
        $line = $this->line();
        $this->shift(['line_id' => $line->id]); // 8h × 5 weekdays
        $crew = $this->crewWithWorker($line, 2); // two workers → 80h

        $monday = Carbon::now()->startOfWeek();
        $grid = $this->service->crewCapacity($monday, $monday->copy()->endOfWeek(), 'week');
        $cell = $this->cell($grid, $crew->id, $grid['buckets'][0]['key']);

        $this->assertSame(80.0, $cell['available_h']);
    }

    public function test_crew_break_windows_reduce_available_hours(): void
    {
        $line = $this->line();
        $this->shift(['line_id' => $line->id]); // 8h/day
        $crew = $this->crewWithWorker($line, 1);
        CrewBreakWindow::factory()->create([
            'crew_id' => $crew->id,
            'start_time' => '12:00',
            'end_time' => '12:30', // 30 min lunch
            'days_of_week' => [1, 2, 3, 4, 5],
            'is_active' => true,
        ]);

        $monday = Carbon::now()->startOfWeek();
        $grid = $this->service->crewCapacity($monday, $monday->copy()->endOfWeek(), 'week');
        $cell = $this->cell($grid, $crew->id, $grid['buckets'][0]['key']);

        // (480 − 30) min × 5 days = 2250 min = 37.5h
        $this->assertSame(37.5, $cell['available_h']);
    }

    public function test_break_window_with_empty_days_does_not_reduce_hours(): void
    {
        // An empty days_of_week means "applies on no day" (consistent with
        // CrewBreakWindow::appliesOn and Shift::current) — it must NOT be
        // treated as "every day" and silently eat capacity.
        $line = $this->line();
        $this->shift(['line_id' => $line->id]); // 8h × 5 = 40h
        $crew = $this->crewWithWorker($line, 1);
        CrewBreakWindow::factory()->create([
            'crew_id' => $crew->id,
            'start_time' => '12:00',
            'end_time' => '12:30',
            'days_of_week' => [],
            'is_active' => true,
        ]);

        $monday = Carbon::now()->startOfWeek();
        $grid = $this->service->crewCapacity($monday, $monday->copy()->endOfWeek(), 'week');
        $cell = $this->cell($grid, $crew->id, $grid['buckets'][0]['key']);

        $this->assertSame(40.0, $cell['available_h']);
    }

    public function test_crew_absence_removes_a_workers_hours(): void
    {
        $line = $this->line();
        $this->shift(['line_id' => $line->id]);
        $crew = $this->crewWithWorker($line, 1);
        $worker = $crew->workers()->first();

        $monday = Carbon::now()->startOfWeek();
        // Approved absence covering the whole Monday.
        $worker->absences()->create([
            'type' => 'vacation',
            'starts_on' => $monday->toDateString(),
            'ends_on' => $monday->toDateString(),
            'all_day' => true,
            'status' => 'approved',
        ]);

        $grid = $this->service->crewCapacity($monday, $monday->copy()->endOfWeek(), 'week');
        $cell = $this->cell($grid, $crew->id, $grid['buckets'][0]['key']);

        // Monday (8h) dropped → 4 weekdays × 8h = 32h.
        $this->assertSame(32.0, $cell['available_h']);
    }

    public function test_crew_planned_hours_reflect_demand_of_staffed_lines(): void
    {
        $line = $this->line();
        $this->shift(['line_id' => $line->id]);
        $crew = $this->crewWithWorker($line, 1);

        $monday = Carbon::now()->startOfWeek();
        // 8h of planned machine work on the crew's line → 8h labor demand.
        WorkOrder::factory()->create([
            'line_id' => $line->id,
            'status' => WorkOrder::STATUS_PENDING,
            'planned_start_at' => $monday->copy()->setTime(6, 0),
            'planned_end_at' => $monday->copy()->setTime(14, 0),
        ]);

        $grid = $this->service->crewCapacity($monday, $monday->copy()->endOfWeek(), 'week');
        $cell = $this->cell($grid, $crew->id, $grid['buckets'][0]['key']);

        $this->assertSame(8.0, $cell['planned_h']);
    }

    public function test_crew_demand_is_weighted_by_required_operators(): void
    {
        $line = $this->line();
        $this->shift(['line_id' => $line->id]);
        $crew = $this->crewWithWorker($line, 1);

        $monday = Carbon::now()->startOfWeek();
        // 8h machine work; duration-weighted operators = (60×2 + 60×1)/120 = 1.5
        // → 8 machine-hours × 1.5 = 12 labor-hours (true person-hours, not peak).
        WorkOrder::factory()->create([
            'line_id' => $line->id,
            'status' => WorkOrder::STATUS_PENDING,
            'planned_start_at' => $monday->copy()->setTime(6, 0),
            'planned_end_at' => $monday->copy()->setTime(14, 0),
            'process_snapshot' => ['steps' => [
                ['estimated_duration_minutes' => 60, 'required_operators' => 2],
                ['estimated_duration_minutes' => 60, 'required_operators' => 1],
            ]],
        ]);

        $grid = $this->service->crewCapacity($monday, $monday->copy()->endOfWeek(), 'week');
        $cell = $this->cell($grid, $crew->id, $grid['buckets'][0]['key']);

        $this->assertSame(12.0, $cell['planned_h']);
    }

    public function test_demand_on_unstaffed_lines_surfaces_in_an_unassigned_row(): void
    {
        // A line with planned work but no crew staffing it.
        $line = $this->line();
        $this->shift(['line_id' => $line->id]);
        $monday = Carbon::now()->startOfWeek();
        WorkOrder::factory()->create([
            'line_id' => $line->id,
            'status' => WorkOrder::STATUS_PENDING,
            'planned_start_at' => $monday->copy()->setTime(6, 0),
            'planned_end_at' => $monday->copy()->setTime(14, 0), // 8h, 1 operator
        ]);

        $grid = $this->service->crewCapacity($monday, $monday->copy()->endOfWeek(), 'week');
        $unassigned = collect($grid['resources'])->firstWhere('id', 0);

        $this->assertNotNull($unassigned, 'an Unassigned row should appear');
        $cell = $unassigned['cells'][$grid['buckets'][0]['key']];
        $this->assertSame(8.0, $cell['planned_h']);
        $this->assertSame(0.0, $cell['available_h']);
        $this->assertNull($cell['load_pct']); // no labor supply → over-capacity flag
    }

    public function test_no_unassigned_row_when_every_line_is_staffed(): void
    {
        $line = $this->line();
        $this->shift(['line_id' => $line->id]);
        $this->crewWithWorker($line, 1); // staffs the line
        WorkOrder::factory()->create([
            'line_id' => $line->id,
            'status' => WorkOrder::STATUS_PENDING,
            'planned_start_at' => Carbon::now()->startOfWeek()->setTime(6, 0),
            'planned_end_at' => Carbon::now()->startOfWeek()->setTime(14, 0),
        ]);

        $monday = Carbon::now()->startOfWeek();
        $grid = $this->service->crewCapacity($monday, $monday->copy()->endOfWeek(), 'week');

        $this->assertNull(collect($grid['resources'])->firstWhere('id', 0));
    }

    public function test_explicit_line_assignment_drives_crew_demand(): void
    {
        // Crew's workers are on one line (giving it available hours)...
        $workerLine = $this->line();
        $this->shift(['line_id' => $workerLine->id]);
        $crew = $this->crewWithWorker($workerLine, 1);

        // ...but it is explicitly assigned to a different line that holds the work.
        $demandLine = $this->line();
        $crew->lines()->sync([$demandLine->id]);

        $monday = Carbon::now()->startOfWeek();
        WorkOrder::factory()->create([
            'line_id' => $demandLine->id,
            'status' => WorkOrder::STATUS_PENDING,
            'planned_start_at' => $monday->copy()->setTime(6, 0),
            'planned_end_at' => $monday->copy()->setTime(14, 0), // 8h
        ]);

        $grid = $this->service->crewCapacity($monday, $monday->copy()->endOfWeek(), 'week');
        $cell = $this->cell($grid, $crew->id, $grid['buckets'][0]['key']);

        // Demand follows the explicit assignment, not the workers' line.
        $this->assertSame(8.0, $cell['planned_h']);
    }

    public function test_shared_line_demand_is_split_between_staffing_crews(): void
    {
        $line = $this->line();
        $this->shift(['line_id' => $line->id]);
        $crewA = $this->crewWithWorker($line, 1);
        $crewB = $this->crewWithWorker($line, 1);

        $monday = Carbon::now()->startOfWeek();
        WorkOrder::factory()->create([
            'line_id' => $line->id,
            'status' => WorkOrder::STATUS_PENDING,
            'planned_start_at' => $monday->copy()->setTime(6, 0),
            'planned_end_at' => $monday->copy()->setTime(14, 0), // 8h
        ]);

        $grid = $this->service->crewCapacity($monday, $monday->copy()->endOfWeek(), 'week');

        // 8h split equally across the two crews staffing the line.
        $this->assertSame(4.0, $this->cell($grid, $crewA->id, $grid['buckets'][0]['key'])['planned_h']);
        $this->assertSame(4.0, $this->cell($grid, $crewB->id, $grid['buckets'][0]['key'])['planned_h']);
    }

    public function test_shift_duration_is_dst_proof(): void
    {
        // Under a DST timezone, a nominal 8h shift on the spring-forward day must
        // still count 8h — clock-span math, not wall-clock instants.
        config(['app.timezone' => 'Europe/Warsaw']);
        Carbon::setTestNow(Carbon::parse('2026-03-29 12:00', 'Europe/Warsaw')); // spring-forward Sunday

        $line = $this->line();
        // 00:00–08:00 spans the lost 02:00→03:00 hour; nominal span is still 8h.
        $this->shift([
            'line_id' => $line->id,
            'start_time' => '00:00:00',
            'end_time' => '08:00:00',
            'days_of_week' => [1, 2, 3, 4, 5, 6, 7],
        ]);

        $monday = Carbon::now()->startOfWeek();
        $grid = $this->service->lineCapacity($monday, $monday->copy()->endOfWeek(), 'week');
        $cell = $this->cell($grid, $line->id, $grid['buckets'][0]['key']);

        // 8h × 7 days = 56h — no day loses an hour to the DST transition.
        $this->assertSame(56.0, $cell['available_h']);

        Carbon::setTestNow();
    }

    // ── cell drill-down ──────────────────────────────────────────────────────

    public function test_cell_orders_lists_contributing_work_orders_with_hours(): void
    {
        $line = $this->line();
        $monday = Carbon::now()->startOfWeek();

        $wo = WorkOrder::factory()->create([
            'line_id' => $line->id,
            'status' => WorkOrder::STATUS_PENDING,
            'due_date' => $monday->copy()->addDay(),
            'planned_start_at' => null,
            'planned_end_at' => null,
            'process_snapshot' => ['steps' => [['estimated_duration_minutes' => 120]]],
        ]);
        // An order due outside the bucket must not be listed.
        WorkOrder::factory()->create([
            'line_id' => $line->id,
            'status' => WorkOrder::STATUS_PENDING,
            'due_date' => $monday->copy()->addWeeks(3),
        ]);

        $orders = $this->service->cellOrders($line->id, $monday->copy()->startOfWeek(), $monday->copy()->endOfWeek());

        $this->assertCount(1, $orders);
        $this->assertSame($wo->id, $orders[0]['id']);
        $this->assertSame(2.0, $orders[0]['hours']);
        $this->assertFalse($orders[0]['unestimated']);
        $this->assertFalse($orders[0]['minute_planned']);
        // The drill-down must surface week_number/shift_number so the reschedule
        // can echo them back and avoid wiping a week-planned order's placement.
        $this->assertArrayHasKey('week_number', $orders[0]);
        $this->assertArrayHasKey('shift_number', $orders[0]);
    }
}
