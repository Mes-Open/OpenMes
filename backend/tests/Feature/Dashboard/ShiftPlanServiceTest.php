<?php

namespace Tests\Feature\Dashboard;

use App\Enums\DowntimeKind;
use App\Enums\ScheduledOperationStatus;
use App\Models\DowntimeReason;
use App\Models\Line;
use App\Models\ProductionDowntime;
use App\Models\ScheduledOperation;
use App\Models\ScheduleSimulation;
use App\Models\Shift;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Dashboard\ShiftPlanService;
use App\Support\ShiftWindow;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Planned vs actual output per shift hour: the plan spread from the schedule,
 * actual output from the produced_qty ledger, running totals, pace and the
 * one-line summary.
 */
class ShiftPlanServiceTest extends TestCase
{
    use RefreshDatabase;

    private ShiftPlanService $service;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ShiftPlanService::class);
        $this->line = Line::factory()->create(['name' => 'L1']);

        // A Tuesday; the day shift runs 06:00–14:00.
        Shift::create([
            'name' => 'Day', 'code' => 'D',
            'start_time' => '06:00:00', 'end_time' => '14:00:00',
            'line_id' => null, 'is_active' => true,
        ]);
        $this->travelTo($this->at('10:30'));
    }

    private function at(string $time, string $date = '2026-10-20'): Carbon
    {
        return Carbon::parse("{$date} {$time}");
    }

    private function order(string $start, string $end, float $qty, array $overrides = []): WorkOrder
    {
        return WorkOrder::factory()->create(array_merge([
            'line_id' => $this->line->id,
            'planned_qty' => $qty,
            'planned_start_at' => Carbon::parse($start),
            'planned_end_at' => Carbon::parse($end),
            'status' => WorkOrder::STATUS_ACCEPTED,
        ], $overrides));
    }

    /** Set produced_qty as it was at the given clock time. */
    private function produceAt(WorkOrder $order, string $time, float $total): void
    {
        $this->travelTo($this->at($time));
        $order->refresh()->update(['produced_qty' => $total]);
        $this->travelTo($this->at('10:30'));
    }

    private function shift(?int $lineId = null): array
    {
        return $this->service->forShift(ShiftWindow::current(), $lineId);
    }

    /** @return array<string, ?float> hour start HH:MM => value */
    private function perHour(array $data, string $key): array
    {
        return collect($data['hours'])
            ->mapWithKeys(fn ($h) => [Carbon::parse($h['start'])->format('H:i') => $h[$key]])
            ->all();
    }

    // ── plan ───────────────────────────────────────────────────────────

    public function test_order_inside_the_shift_is_spread_evenly(): void
    {
        $this->order('2026-10-20 08:00', '2026-10-20 12:00', 400);

        $data = $this->shift();

        $this->assertCount(8, $data['hours']);
        $this->assertSame(400.0, $data['totals']['planned']);
        $this->assertEquals(
            ['06:00' => 0, '07:00' => 0, '08:00' => 100, '09:00' => 100, '10:00' => 100, '11:00' => 100, '12:00' => 0, '13:00' => 0],
            $this->perHour($data, 'planned'),
        );
        $this->assertSame(50.0, $data['plan_per_hour']);
        // 08:00–10:30 is 2.5 of the 4 planned hours.
        $this->assertSame(250.0, $data['totals']['planned_to_now']);
    }

    public function test_order_partly_in_the_shift_counts_only_its_overlap(): void
    {
        $this->order('2026-10-20 04:00', '2026-10-20 08:00', 400);

        $data = $this->shift();

        $this->assertSame(200.0, $data['totals']['planned']);
        $hours = $this->perHour($data, 'planned');
        $this->assertEquals(100, $hours['06:00']);
        $this->assertEquals(100, $hours['07:00']);
        $this->assertEquals(0, $hours['08:00']);
    }

    public function test_night_shift_across_midnight(): void
    {
        Shift::query()->delete();
        Shift::create([
            'name' => 'Night', 'code' => 'N',
            'start_time' => '22:00:00', 'end_time' => '06:00:00',
            'line_id' => null, 'is_active' => true,
        ]);
        // 20:00–02:00, 600 pcs → 100 pcs/h, 400 in the shift (22:00–02:00).
        $order = $this->order('2026-10-20 20:00', '2026-10-21 02:00', 600);
        $this->travelTo($this->at('01:30', '2026-10-21'));
        $order->update(['produced_qty' => 30]);
        $this->travelTo($this->at('03:00', '2026-10-21'));

        $window = ShiftWindow::current();
        $data = $this->service->forShift($window);

        $this->assertSame('2026-10-20', $window->businessDate);
        $this->assertSame(400.0, $data['totals']['planned']);
        $this->assertEquals(
            ['22:00' => 100, '23:00' => 100, '00:00' => 100, '01:00' => 100, '02:00' => 0, '03:00' => 0, '04:00' => 0, '05:00' => 0],
            $this->perHour($data, 'planned'),
        );
        $this->assertEquals(30, $this->perHour($data, 'actual')['01:00']);
        $this->assertSame('2026-10-21T06:00:00+00:00', Carbon::parse($data['end'])->utc()->toIso8601String());
    }

    public function test_several_orders_on_a_line_and_the_line_filter(): void
    {
        $other = Line::factory()->create();
        $this->order('2026-10-20 06:00', '2026-10-20 08:00', 200);
        $this->order('2026-10-20 07:00', '2026-10-20 09:00', 100);
        $this->order('2026-10-20 06:00', '2026-10-20 14:00', 800, ['line_id' => $other->id]);

        $this->assertSame(1100.0, $this->shift()['totals']['planned']);
        $this->assertSame(3, $this->shift()['totals']['planned_orders']);

        $data = $this->shift($this->line->id);
        $this->assertSame(300.0, $data['totals']['planned']);
        $this->assertEquals(150, $this->perHour($data, 'planned')['07:00']);
    }

    public function test_which_orders_count_towards_the_plan(): void
    {
        $this->order('2026-10-20 06:00', '2026-10-20 14:00', 80, ['status' => WorkOrder::STATUS_DONE]);
        $this->order('2026-10-20 06:00', '2026-10-20 14:00', 1000, ['status' => WorkOrder::STATUS_CANCELLED]);
        $this->order('2026-10-20 06:00', '2026-10-20 14:00', 1000, ['status' => WorkOrder::STATUS_REJECTED]);
        $this->order('2026-10-20 06:00', '2026-10-20 14:00', 1000)->delete();
        // Placed by date only: no minute window, no hourly plan.
        WorkOrder::factory()->create([
            'line_id' => $this->line->id, 'planned_qty' => 1000, 'status' => WorkOrder::STATUS_ACCEPTED,
            'planned_start_at' => $this->at('06:00'), 'planned_end_at' => null,
        ]);

        $this->assertSame(80.0, $this->shift()['totals']['planned']);
    }

    public function test_an_applied_simulation_is_counted_once_and_drafts_are_ignored(): void
    {
        $order = $this->order('2026-10-20 06:00', '2026-10-20 10:00', 400);
        foreach ([['06:00', '08:00'], ['08:00', '10:00']] as $n => [$s, $e]) {
            ScheduledOperation::create([
                'work_order_id' => $order->id, 'step_number' => $n + 1, 'line_id' => $this->line->id,
                'planned_start_at' => $this->at($s), 'planned_end_at' => $this->at($e),
                'status' => ScheduledOperationStatus::Scheduled->value,
            ]);
        }
        $draft = ScheduleSimulation::create([
            'status' => ScheduleSimulation::STATUS_DRAFT,
            'horizon_start' => $this->at('06:00'),
            'horizon_end' => $this->at('14:00'),
        ]);
        ScheduledOperation::create([
            'schedule_simulation_id' => $draft->id,
            'work_order_id' => $order->id, 'step_number' => 1, 'line_id' => $this->line->id,
            'planned_start_at' => $this->at('12:00'), 'planned_end_at' => $this->at('14:00'),
            'status' => ScheduledOperationStatus::Scheduled->value,
        ]);

        $data = $this->shift();

        $this->assertSame(400.0, $data['totals']['planned']);
        $this->assertEquals(0, $this->perHour($data, 'planned')['12:00']);
    }

    public function test_no_plan_is_null_not_zero(): void
    {
        $order = WorkOrder::factory()->create(['line_id' => $this->line->id, 'status' => WorkOrder::STATUS_IN_PROGRESS]);
        $this->produceAt($order, '07:10', 12);

        $data = $this->shift();

        $this->assertNull($data['totals']['planned']);
        $this->assertNull($data['totals']['planned_to_now']);
        $this->assertNull($data['totals']['pace_delta']);
        $this->assertNull($data['totals']['pct_of_plan']);
        $this->assertNull($data['totals']['planned_orders']);
        $this->assertNull($data['plan_per_hour']);
        foreach ($data['hours'] as $hour) {
            $this->assertNull($hour['planned']);
        }
        foreach ($data['cumulative'] as $point) {
            $this->assertNull($point['planned']);
        }
        $this->assertSame(12.0, $data['totals']['actual']);
        $this->assertSame('no_plan', $data['summary']['kind']);
    }

    public function test_no_active_lines(): void
    {
        $this->line->update(['is_active' => false]);

        $data = $this->shift();

        $this->assertFalse($data['has_lines']);
        $this->assertSame([], $data['hours']);
        $this->assertSame('no_lines', $data['summary']['kind']);
    }

    // ── actual ─────────────────────────────────────────────────────────

    public function test_actual_output_per_hour_comes_from_produced_qty_changes(): void
    {
        $order = $this->order('2026-10-20 05:00', '2026-10-20 13:00', 800);
        // Before the shift: not this shift's output.
        $this->travelTo($this->at('05:50'));
        $order->update(['produced_qty' => 5]);

        $this->produceAt($order, '06:20', 45);   // +40
        $this->produceAt($order, '06:50', 90);   // +45
        $this->produceAt($order, '08:15', 190);  // +100
        $this->produceAt($order, '08:40', 180);  // −10 correction
        $this->produceAt($order, '10:10', 230);  // +50

        $data = $this->shift();
        $hours = $this->perHour($data, 'actual');

        $this->assertEquals(85, $hours['06:00']);
        $this->assertEquals(0, $hours['07:00']);
        $this->assertEquals(90, $hours['08:00']);
        $this->assertEquals(0, $hours['09:00']);
        $this->assertEquals(50, $hours['10:00']);
        $this->assertNull($hours['11:00']);
        $this->assertNull($hours['13:00']);
        // Ledger total for the shift = produced_qty minus what it was at 06:00.
        $this->assertSame(225.0, $data['totals']['actual']);
        $this->assertSame((float) $order->fresh()->produced_qty - 5, $data['totals']['actual']);
    }

    public function test_actual_counts_only_lines_in_scope_and_skips_deleted_orders(): void
    {
        $other = Line::factory()->create();
        $mine = $this->order('2026-10-20 06:00', '2026-10-20 14:00', 800);
        $theirs = $this->order('2026-10-20 06:00', '2026-10-20 14:00', 800, ['line_id' => $other->id]);
        $gone = $this->order('2026-10-20 06:00', '2026-10-20 14:00', 800);
        $this->produceAt($mine, '07:00', 10);
        $this->produceAt($theirs, '07:00', 20);
        $this->produceAt($gone, '07:00', 40);
        $gone->delete();

        $this->assertSame(10.0, $this->shift($this->line->id)['totals']['actual']);
        $this->assertSame(30.0, $this->shift()['totals']['actual']);
    }

    public function test_hour_phases_follow_the_clock(): void
    {
        $phases = collect($this->shift()['hours'])->pluck('phase')->all();

        $this->assertSame(['past', 'past', 'past', 'past', 'current', 'future', 'future', 'future'], $phases);
    }

    // ── running totals and pace ────────────────────────────────────────

    public function test_cumulative_series_and_behind_pace(): void
    {
        $order = $this->order('2026-10-20 06:00', '2026-10-20 14:00', 800); // 100 pcs/h
        $this->produceAt($order, '06:30', 80);
        $this->produceAt($order, '07:30', 150);
        $this->produceAt($order, '10:15', 300);

        $data = $this->shift();

        $planned = collect($data['cumulative'])->pluck('planned')->all();
        $sorted = $planned;
        sort($sorted);
        $this->assertSame($sorted, $planned, 'cumulative plan never goes down');

        $points = collect($data['cumulative'])->keyBy(fn ($p) => Carbon::parse($p['at'])->format('H:i'));
        $this->assertSame(0.0, $points['06:00']['actual']);
        $this->assertSame(80.0, $points['07:00']['actual']);
        $this->assertSame(150.0, $points['10:00']['actual']);
        $this->assertSame(300.0, $points['10:30']['actual']);
        $this->assertSame(450.0, $points['10:30']['planned']);
        $this->assertNull($points['11:00']['actual']);
        $this->assertSame(800.0, $points['14:00']['planned']);

        $this->assertSame(450.0, $data['totals']['planned_to_now']);
        $this->assertSame(-150, $data['totals']['pace_delta']);
        $this->assertSame(38, $data['totals']['pct_of_plan']);
    }

    public function test_ahead_of_pace(): void
    {
        $order = $this->order('2026-10-20 06:00', '2026-10-20 14:00', 800);
        $this->produceAt($order, '09:00', 500);

        $this->assertSame(50, $this->shift()['totals']['pace_delta']);
    }

    // ── summary ────────────────────────────────────────────────────────

    public function test_summary_below_plan_since_with_overlapping_unplanned_downtime(): void
    {
        $order = $this->order('2026-10-20 06:00', '2026-10-20 14:00', 800); // 100 pcs/h
        $this->produceAt($order, '06:50', 100); // 06:00 on plan
        $this->produceAt($order, '07:50', 210); // 07:00 above
        $this->produceAt($order, '08:50', 260); // 08:00 below
        $this->produceAt($order, '09:50', 300); // 09:00 below

        $unplanned = DowntimeReason::create(['code' => 'BRK', 'name' => 'Breakdown', 'kind' => DowntimeKind::Unplanned]);
        $planned = DowntimeReason::create(['code' => 'BRE', 'name' => 'Break', 'kind' => DowntimeKind::Planned]);
        ProductionDowntime::create([
            'line_id' => $this->line->id, 'downtime_reason_id' => $unplanned->id,
            'started_at' => $this->at('07:45'), 'ended_at' => $this->at('08:30'),
        ]);
        ProductionDowntime::create([
            'line_id' => $this->line->id, 'downtime_reason_id' => $planned->id,
            'started_at' => $this->at('09:00'), 'ended_at' => $this->at('09:30'),
        ]);
        ProductionDowntime::create([
            // Machine-reported, not yet explained by anyone.
            'line_id' => $this->line->id, 'downtime_reason_id' => $planned->id, 'needs_reason' => true,
            'started_at' => $this->at('10:20'), 'ended_at' => null,
        ]);

        $summary = $this->shift()['summary'];

        $this->assertSame('below', $summary['kind']);
        $this->assertSame($this->at('08:00')->toIso8601String(), $summary['since']);
        // 08:00–08:30 unplanned + 10:20–10:30 still open, unclassified; the planned break is not counted.
        $this->assertSame(40, $summary['downtime_minutes']);
        $this->assertSame(['L1'], $summary['downtime_lines']);
    }

    public function test_summary_above_and_on_plan(): void
    {
        $order = $this->order('2026-10-20 06:00', '2026-10-20 14:00', 800);
        $this->produceAt($order, '09:30', 150);

        $summary = $this->shift()['summary'];
        $this->assertSame('above', $summary['kind']);
        $this->assertSame($this->at('09:00')->toIso8601String(), $summary['since']);
        $this->assertNull($summary['downtime_minutes']);

        $this->produceAt($order, '09:40', 100);
        $this->assertSame('on', $this->shift()['summary']['kind']);
    }

    public function test_summary_before_the_first_hour_is_complete(): void
    {
        $this->order('2026-10-20 06:00', '2026-10-20 14:00', 800);
        $this->travelTo($this->at('06:20'));

        $this->assertSame('not_started', $this->shift()['summary']['kind']);
    }

    public function test_query_count_does_not_grow_with_lines_or_orders(): void
    {
        $count = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->shift();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };
        $build = function (int $lines) {
            foreach (range(1, $lines) as $i) {
                $this->line = Line::factory()->create();
                $order = $this->order('2026-10-20 06:00', '2026-10-20 14:00', 800);
                $this->produceAt($order, '06:30', 10); // keeps every line below plan
            }
        };

        $build(1);
        $small = $count();
        $build(8);
        $large = $count();

        $this->assertSame($small, $large);
        $this->assertLessThanOrEqual(6, $large);
    }

    // ── window and tenancy ─────────────────────────────────────────────

    public function test_fallback_window_without_shifts(): void
    {
        Shift::query()->delete();

        $data = $this->shift();

        $this->assertTrue($data['is_default']);
        $this->assertCount(12, $data['hours']);
        $this->assertSame($this->at('06:00')->toIso8601String(), $data['start']);
    }

    public function test_other_tenants_plan_and_output_do_not_leak(): void
    {
        $tenantA = Tenant::create(['name' => 'A']);
        $tenantB = Tenant::create(['name' => 'B']);
        $lineA = Line::factory()->create(['tenant_id' => $tenantA->id]);
        $lineB = Line::factory()->create(['tenant_id' => $tenantB->id]);
        $a = $this->order('2026-10-20 06:00', '2026-10-20 14:00', 80, ['line_id' => $lineA->id, 'tenant_id' => $tenantA->id]);
        $b = $this->order('2026-10-20 06:00', '2026-10-20 14:00', 999, ['line_id' => $lineB->id, 'tenant_id' => $tenantB->id]);
        $this->produceAt($a, '07:00', 7);
        $this->produceAt($b, '07:00', 99);

        $this->actingAs(User::factory()->create(['tenant_id' => $tenantA->id]));
        $data = $this->shift();

        $this->assertSame(80.0, $data['totals']['planned']);
        $this->assertSame(7.0, $data['totals']['actual']);
    }
}
