<?php

namespace Tests\Feature\Dashboard;

use App\Models\Batch;
use App\Models\Line;
use App\Models\MachineEvent;
use App\Models\ProductType;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Models\WorkstationState;
use App\Services\Dashboard\LineProgressService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The "Lines" cards' data: current order per line, its progress, the last
 * hour's rate and the ETA derived from it.
 */
class LineProgressServiceTest extends TestCase
{
    use RefreshDatabase;

    private LineProgressService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-05-26 10:00:00'));
        $this->service = app(LineProgressService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function at(string $time): Carbon
    {
        return Carbon::parse('2026-05-26 '.$time);
    }

    private function order(Line $line, array $attributes = []): WorkOrder
    {
        return WorkOrder::factory()->create(array_merge([
            'line_id' => $line->id,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
            'planned_qty' => 450,
            'produced_qty' => 312,
            'planned_end_at' => $this->at('13:00'),
        ], $attributes));
    }

    private function counter(Workstation $station, string $time, float $delta, string $kind = 'good'): void
    {
        MachineEvent::create([
            'workstation_id' => $station->id,
            'event_type' => MachineEvent::TYPE_COUNTER,
            'payload' => ['kind' => $kind, 'delta' => $delta],
            'event_timestamp' => $this->at($time),
        ]);
    }

    private function batch(WorkOrder $order, array $attributes): Batch
    {
        return Batch::factory()->create(array_merge(['work_order_id' => $order->id], $attributes));
    }

    /** @return array<string, mixed> */
    private function row(Line $line, bool $hasCollector): array
    {
        return $this->service->forLines([['id' => $line->id, 'has_collector' => $hasCollector]], Carbon::now())[0];
    }

    // ── progress ───────────────────────────────────────────────────────

    public function test_progress_of_the_current_order(): void
    {
        $line = Line::factory()->create();
        $product = ProductType::factory()->create(['code' => 'AL-220']);
        $order = $this->order($line, ['product_type_id' => $product->id]);

        $row = $this->row($line, false);

        $this->assertSame($order->id, $row['order']['id']);
        $this->assertSame($order->order_no, $row['order']['order_no']);
        $this->assertSame('AL-220', $row['order']['product_code']);
        $this->assertEquals(312, $row['order']['produced']);
        $this->assertEquals(450, $row['order']['planned']);
        $this->assertSame(69, $row['order']['percent']);
        $this->assertSame(0, $row['more_count']);
    }

    public function test_percent_is_null_without_a_planned_quantity_and_capped_at_100(): void
    {
        $this->assertNull(LineProgressService::percent(0, 10));
        $this->assertSame(99, LineProgressService::percent(100, 99.9));
        $this->assertSame(100, LineProgressService::percent(100, 130));
    }

    // ── ETA ────────────────────────────────────────────────────────────

    public function test_on_track_eta_from_the_last_hours_machine_rate(): void
    {
        $line = Line::factory()->create();
        $a = Workstation::factory()->create(['line_id' => $line->id]);
        $b = Workstation::factory()->create(['line_id' => $line->id]);
        $this->order($line);

        // Station A made 120 good pieces in the hour, B 100: the line's rate is
        // the max (serial line), not the sum.
        $this->counter($a, '09:10', 60);
        $this->counter($a, '09:50', 60);
        $this->counter($b, '09:30', 100);
        // Ignored: before the window, rejects, and a different event type.
        $this->counter($a, '08:55', 500);
        $this->counter($a, '09:40', 40, 'reject');

        $row = $this->row($line, true);

        $this->assertEquals(120, $row['rate_per_hour']);
        $this->assertSame('rate', $row['eta_source']);
        // 138 pieces left at 120/h = 1 h 9 min.
        $this->assertSame($this->at('11:09')->toIso8601String(), $row['eta']);
        $this->assertFalse($row['late']);
    }

    public function test_late_when_the_rate_eta_is_after_the_planned_end(): void
    {
        $line = Line::factory()->create();
        $station = Workstation::factory()->create(['line_id' => $line->id]);
        $this->order($line, ['planned_end_at' => $this->at('10:30')]);
        $this->counter($station, '09:30', 60);

        $row = $this->row($line, true);

        $this->assertSame('rate', $row['eta_source']);
        $this->assertSame($this->at('12:18')->toIso8601String(), $row['eta']);
        $this->assertTrue($row['late']);
    }

    public function test_without_a_rate_the_eta_is_the_planned_end(): void
    {
        $line = Line::factory()->create();
        Workstation::factory()->create(['line_id' => $line->id]);
        $this->order($line, ['planned_end_at' => $this->at('12:00')]);

        $row = $this->row($line, true);

        $this->assertNull($row['rate_per_hour']);
        $this->assertSame('plan', $row['eta_source']);
        $this->assertSame($this->at('12:00')->toIso8601String(), $row['eta']);
        $this->assertFalse($row['late']);
    }

    public function test_without_a_rate_a_passed_planned_end_is_late(): void
    {
        $line = Line::factory()->create();
        $this->order($line, ['planned_end_at' => $this->at('09:00')]);

        $row = $this->row($line, false);

        $this->assertSame('plan', $row['eta_source']);
        $this->assertTrue($row['late']);
    }

    public function test_without_rate_or_planned_end_there_is_no_eta(): void
    {
        $line = Line::factory()->create();
        $this->order($line, ['planned_end_at' => null]);

        $row = $this->row($line, false);

        $this->assertNull($row['eta']);
        $this->assertNull($row['eta_source']);
        $this->assertFalse($row['late']);
    }

    public function test_line_without_collector_takes_its_rate_from_batches_completed_in_the_hour(): void
    {
        $line = Line::factory()->create();
        $order = $this->order($line, ['planned_end_at' => null]);
        $this->batch($order, ['status' => Batch::STATUS_DONE, 'produced_qty' => 46, 'started_at' => $this->at('09:00'), 'completed_at' => $this->at('09:30')]);
        $this->batch($order, ['status' => Batch::STATUS_DONE, 'produced_qty' => 100, 'started_at' => $this->at('07:00'), 'completed_at' => $this->at('08:00')]);

        $row = $this->row($line, false);

        $this->assertEquals(46, $row['rate_per_hour']);
        // 138 left at 46/h = 3 h.
        $this->assertSame($this->at('13:00')->toIso8601String(), $row['eta']);
        $this->assertFalse($row['late']);
    }

    public function test_compute_eta_edge_cases(): void
    {
        $now = $this->at('10:00');

        $done = LineProgressService::computeEta(100, 100, 50, $this->at('11:00'), $now);
        $this->assertTrue($done['eta']->eq($now));
        $this->assertFalse($done['late']);

        $over = LineProgressService::computeEta(100, 140, 50, null, $now);
        $this->assertTrue($over['eta']->eq($now));
        $this->assertFalse($over['late']);

        $zeroRate = LineProgressService::computeEta(100, 10, 0.0, $this->at('11:00'), $now);
        $this->assertSame('plan', $zeroRate['source']);
    }

    // ── which order ────────────────────────────────────────────────────

    public function test_earliest_started_order_is_current_and_the_rest_are_counted(): void
    {
        $line = Line::factory()->create();
        $first = $this->order($line, ['produced_qty' => 10]);
        $second = $this->order($line, ['produced_qty' => 20]);
        // The order with the larger id started first.
        $this->batch($first, ['status' => Batch::STATUS_IN_PROGRESS, 'started_at' => $this->at('09:00')]);
        $this->batch($second, ['status' => Batch::STATUS_IN_PROGRESS, 'started_at' => $this->at('07:00')]);
        $this->order($line, ['status' => WorkOrder::STATUS_PENDING]); // not a candidate

        $row = $this->row($line, false);

        $this->assertSame($second->id, $row['order']['id']);
        $this->assertSame(1, $row['more_count']);
    }

    public function test_line_without_an_order(): void
    {
        $line = Line::factory()->create();
        $this->order($line, ['status' => WorkOrder::STATUS_DONE]);

        $row = $this->row($line, false);

        $this->assertSame($line->id, $row['line_id']);
        $this->assertNull($row['order']);
        $this->assertSame(0, $row['more_count']);
        $this->assertNull($row['eta']);
        $this->assertFalse($row['late']);
    }

    public function test_line_with_only_a_blocked_order_shows_it(): void
    {
        $line = Line::factory()->create();
        $blocked = $this->order($line, ['status' => WorkOrder::STATUS_BLOCKED]);

        $this->assertSame($blocked->id, $this->row($line, false)['order']['id']);
    }

    public function test_in_progress_order_wins_over_a_blocked_one(): void
    {
        $line = Line::factory()->create();
        $blocked = $this->order($line, ['status' => WorkOrder::STATUS_BLOCKED]);
        $running = $this->order($line);
        $this->batch($blocked, ['status' => Batch::STATUS_IN_PROGRESS, 'started_at' => $this->at('06:00')]);

        $row = $this->row($line, false);

        $this->assertSame($running->id, $row['order']['id']);
        $this->assertSame(1, $row['more_count']);
    }

    // ── scale & tenancy ────────────────────────────────────────────────

    public function test_query_count_does_not_grow_with_line_count(): void
    {
        $build = function (int $count): array {
            $rows = [];
            foreach (range(1, $count) as $i) {
                $line = Line::factory()->create();
                $station = Workstation::factory()->create(['line_id' => $line->id]);
                $order = $this->order($line);
                $this->batch($order, ['status' => Batch::STATUS_DONE, 'produced_qty' => 5, 'started_at' => $this->at('09:00'), 'completed_at' => $this->at('09:30')]);
                $this->counter($station, '09:30', 10);
                $rows[] = ['id' => $line->id, 'has_collector' => $i % 2 === 0];
            }

            return $rows;
        };

        $count = function (array $lines): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->service->forLines($lines, Carbon::now());
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $small = $count($build(2));
        $large = $count($build(10));

        $this->assertSame($small, $large, 'forLines() must not issue per-line queries');
    }

    public function test_other_tenants_orders_are_not_seen(): void
    {
        $tenantA = Tenant::create(['name' => 'Tenant A']);
        $tenantB = Tenant::create(['name' => 'Tenant B']);
        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);

        $line = Line::factory()->create(['tenant_id' => $tenantA->id]);
        // A foreign tenant's order pointing at the line must not become current.
        $this->order($line, ['tenant_id' => $tenantB->id]);

        $this->actingAs($userA);

        $this->assertNull($this->row($line, false)['order']);
    }

    public function test_station_states_matter_only_through_has_collector(): void
    {
        // has_collector comes from PlantOverviewService; a station with a state
        // but no counters simply has no machine rate.
        $line = Line::factory()->create();
        $station = Workstation::factory()->create(['line_id' => $line->id]);
        WorkstationState::create([
            'workstation_id' => $station->id, 'state' => WorkstationState::RUNNING,
            'started_at' => $this->at('08:00'), 'ended_at' => null,
        ]);
        $this->order($line);

        $this->assertNull($this->row($line, true)['rate_per_hour']);
    }
}
