<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ScheduledOperationStatus;
use App\Models\Line;
use App\Models\ScheduledOperation;
use App\Models\ScheduleSimulation;
use App\Models\Shift;
use App\Models\WorkOrder;
use App\Services\Dashboard\ProductionTargets;
use App\Support\OeeBand;
use App\Support\ShiftWindow;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The OEE target: a line's planning efficiency (100 = unset → 85), and the
 * plant's target as those weighted by planned minutes in the shift.
 */
class ProductionTargetsTest extends TestCase
{
    use RefreshDatabase;

    private ProductionTargets $targets;

    private Carbon $from;

    private Carbon $to;

    protected function setUp(): void
    {
        parent::setUp();

        $this->targets = app(ProductionTargets::class);
        $this->from = Carbon::parse('2026-10-20 06:00:00');
        $this->to = Carbon::parse('2026-10-20 14:00:00');
    }

    private function operation(Line $line, string $start, string $end, array $overrides = []): ScheduledOperation
    {
        return ScheduledOperation::create(array_merge([
            'work_order_id' => WorkOrder::factory()->create(['line_id' => $line->id])->id,
            'step_number' => 1,
            'line_id' => $line->id,
            'planned_start_at' => Carbon::parse($start),
            'planned_end_at' => Carbon::parse($end),
            'status' => ScheduledOperationStatus::Scheduled->value,
        ], $overrides));
    }

    public function test_line_planning_efficiency_below_100_is_the_target(): void
    {
        $line = Line::factory()->create(['planning_efficiency_pct' => 80]);

        $this->assertSame(80.0, $this->targets->oeeTargetForLine($line));
    }

    public function test_default_planning_efficiency_falls_back_to_the_green_threshold(): void
    {
        $line = Line::factory()->create(['planning_efficiency_pct' => 100]);

        $this->assertSame(OeeBand::GREEN_AT_LEAST, $this->targets->oeeTargetForLine($line));
        $this->assertSame(85.0, $this->targets->oeeTargetForLine($line));
    }

    public function test_out_of_range_value_falls_back_to_the_green_threshold(): void
    {
        $line = new Line(['planning_efficiency_pct' => 0]);

        $this->assertSame(85.0, $this->targets->oeeTargetForLine($line));
    }

    public function test_plant_target_is_weighted_by_planned_minutes(): void
    {
        $a = Line::factory()->create(['planning_efficiency_pct' => 80]);
        $b = Line::factory()->create(['planning_efficiency_pct' => 100]);

        $this->operation($a, '2026-10-20 06:00', '2026-10-20 12:00'); // 360 min
        $this->operation($b, '2026-10-20 06:00', '2026-10-20 08:00'); // 120 min

        // (80·360 + 85·120) / 480
        $this->assertEqualsWithDelta(81.25, $this->targets->plantOeeTargetBetween($this->from, $this->to), 0.0001);
    }

    public function test_no_plan_anywhere_gives_the_default(): void
    {
        Line::factory()->create(['planning_efficiency_pct' => 70]);

        $this->assertSame(85.0, $this->targets->plantOeeTargetBetween($this->from, $this->to));
    }

    public function test_line_without_plan_is_skipped(): void
    {
        $a = Line::factory()->create(['planning_efficiency_pct' => 70]);
        Line::factory()->create(['planning_efficiency_pct' => 90]);

        $this->operation($a, '2026-10-20 07:00', '2026-10-20 09:00');

        $this->assertSame(70.0, $this->targets->plantOeeTargetBetween($this->from, $this->to));
    }

    public function test_work_order_without_operations_counts_by_its_planned_times(): void
    {
        $a = Line::factory()->create(['planning_efficiency_pct' => 80]);
        $b = Line::factory()->create(['planning_efficiency_pct' => 60]);

        WorkOrder::factory()->create([
            'line_id' => $a->id,
            'status' => WorkOrder::STATUS_ACCEPTED,
            'planned_start_at' => Carbon::parse('2026-10-20 06:00'),
            'planned_end_at' => Carbon::parse('2026-10-20 09:00'),
        ]);
        $this->operation($b, '2026-10-20 06:00', '2026-10-20 07:00');

        $minutes = $this->targets->plannedMinutesByLine($this->from, $this->to);
        $this->assertSame(180, $minutes[$a->id]);
        $this->assertSame(60, $minutes[$b->id]);
        // (80·180 + 60·60) / 240
        $this->assertEqualsWithDelta(75.0, $this->targets->plantOeeTargetBetween($this->from, $this->to), 0.0001);
    }

    public function test_order_with_operations_is_not_counted_twice(): void
    {
        $line = Line::factory()->create(['planning_efficiency_pct' => 80]);
        $order = WorkOrder::factory()->create([
            'line_id' => $line->id,
            'status' => WorkOrder::STATUS_ACCEPTED,
            'planned_start_at' => Carbon::parse('2026-10-20 06:00'),
            'planned_end_at' => Carbon::parse('2026-10-20 14:00'),
        ]);
        $this->operation($line, '2026-10-20 10:00', '2026-10-20 11:00', ['work_order_id' => $order->id]);

        $this->assertSame([$line->id => 60], $this->targets->plannedMinutesByLine($this->from, $this->to));
    }

    public function test_overlapping_operations_are_merged_and_clipped_to_the_window(): void
    {
        $line = Line::factory()->create(['planning_efficiency_pct' => 80]);

        // Parallel workstations: 08:00–10:00 and 09:00–11:00 → 08:00–11:00.
        $this->operation($line, '2026-10-20 08:00', '2026-10-20 10:00');
        $this->operation($line, '2026-10-20 09:00', '2026-10-20 11:00');
        // Started before the shift: only 06:00–07:00 counts.
        $this->operation($line, '2026-10-20 04:00', '2026-10-20 07:00');
        // Runs past the end: only 13:30–14:00 counts.
        $this->operation($line, '2026-10-20 13:30', '2026-10-20 16:00');

        $this->assertSame([$line->id => 180 + 60 + 30], $this->targets->plannedMinutesByLine($this->from, $this->to));
    }

    public function test_simulations_infeasible_operations_and_inactive_lines_are_ignored(): void
    {
        $line = Line::factory()->create(['planning_efficiency_pct' => 70]);
        $inactive = Line::factory()->inactive()->create(['planning_efficiency_pct' => 50]);

        $simulation = ScheduleSimulation::create([
            'status' => ScheduleSimulation::STATUS_DRAFT,
            'horizon_start' => $this->from,
            'horizon_end' => $this->to,
        ]);
        $this->operation($line, '2026-10-20 06:00', '2026-10-20 14:00', ['schedule_simulation_id' => $simulation->id]);
        $this->operation($line, '2026-10-20 06:00', '2026-10-20 14:00', ['status' => ScheduledOperationStatus::InfeasibleMaterial->value]);
        $this->operation($inactive, '2026-10-20 06:00', '2026-10-20 14:00');

        $this->assertArrayNotHasKey($line->id, $this->targets->plannedMinutesByLine($this->from, $this->to));
        $this->assertSame(85.0, $this->targets->plantOeeTargetBetween($this->from, $this->to));
    }

    public function test_plant_target_reads_the_shift_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 23:00:00'));
        $shift = Shift::create(['name' => 'Night', 'code' => 'N', 'start_time' => '22:00:00', 'end_time' => '06:00:00', 'is_active' => true]);
        $window = ShiftWindow::occurrence($shift, Carbon::now());

        $line = Line::factory()->create(['planning_efficiency_pct' => 75]);
        $this->operation($line, '2026-10-21 01:00', '2026-10-21 03:00');

        $this->assertSame(75.0, $this->targets->plantOeeTarget($window));

        Carbon::setTestNow();
    }
}
