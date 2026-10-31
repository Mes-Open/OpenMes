<?php

namespace Tests\Feature\Web;

use App\Models\Batch;
use App\Models\DowntimeReason;
use App\Models\Line;
use App\Models\MaintenanceEvent;
use App\Models\ProductionDowntime;
use App\Models\Shift;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Models\WorkstationState;
use App\Services\Dashboard\PlantOverviewService;
use App\Support\ModuleRegistry;
use App\Support\ShiftWindow;
use App\Support\TabRegistry;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The "Line activity · this shift" section of the admin dashboard: each active
 * line's state minute by minute since the plant shift began, and its uptime,
 * delivered as `overview.timeline`.
 */
class DashboardLineTimelineTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        // A Tuesday, mid-morning, four hours into Shift A.
        Carbon::setTestNow(Carbon::parse('2026-05-26 10:00:00'));

        Shift::create([
            'name' => 'Shift A', 'code' => 'A',
            'start_time' => '06:00:00', 'end_time' => '14:00:00',
            'line_id' => null, 'is_active' => true,
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function at(string $time, string $date = '2026-05-26'): Carbon
    {
        return Carbon::parse($date.' '.$time);
    }

    /** @return array<string, mixed> */
    private function timeline(?User $user = null): array
    {
        return $this->actingAs($user ?? $this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->inertiaProps('overview.timeline');
    }

    /** @return array<string, mixed> */
    private function row(Line $line): array
    {
        $row = collect($this->timeline()['lines'])->firstWhere('id', $line->id);
        $this->assertNotNull($row, "Line {$line->name} missing from timeline");

        return $row;
    }

    /**
     * Segments reduced to [state, from, minutes] for compact assertions.
     *
     * @return array<int, array{0: string, 1: int, 2: int}>
     */
    private function shape(array $row): array
    {
        return array_map(fn (array $s) => [$s['state'], $s['from'], $s['minutes']], $row['segments']);
    }

    private function station(Line $line): Workstation
    {
        return Workstation::factory()->create(['line_id' => $line->id]);
    }

    private function state(Workstation $station, string $state, Carbon $from, ?Carbon $to = null): void
    {
        WorkstationState::create([
            'workstation_id' => $station->id, 'state' => $state,
            'started_at' => $from, 'ended_at' => $to,
        ]);
    }

    private function stop(Line $line, ?Workstation $station, ?DowntimeReason $reason, Carbon $from, ?Carbon $to = null, bool $needsReason = false): ProductionDowntime
    {
        return ProductionDowntime::create([
            'line_id' => $line->id,
            'workstation_id' => $station?->id,
            'downtime_reason_id' => $reason?->id,
            'needs_reason' => $needsReason,
            'started_at' => $from,
            'ended_at' => $to,
        ]);
    }

    // ── aggregation ────────────────────────────────────────────────────

    public function test_one_faulted_station_makes_the_line_down_for_that_interval(): void
    {
        $line = Line::factory()->create();
        $a = $this->station($line);
        $b = $this->station($line);
        $this->state($a, WorkstationState::RUNNING, $this->at('06:00'));
        $this->state($b, WorkstationState::RUNNING, $this->at('06:00'), $this->at('08:00'));
        $this->state($b, WorkstationState::FAULT, $this->at('08:00'), $this->at('08:30'));
        $this->state($b, WorkstationState::RUNNING, $this->at('08:30'));
        $this->stop($line, $b, DowntimeReason::factory()->unplanned()->create(['name' => 'Jammed']), $this->at('08:00'), $this->at('08:30'));

        $row = $this->row($line);

        $this->assertSame([['running', 0, 120], ['down', 120, 30], ['running', 150, 90]], $this->shape($row));
        $this->assertSame('Jammed', $row['segments'][1]['reason']);
        $this->assertSame('machine', $row['segments'][1]['source']);
        $this->assertSame($this->at('08:00')->toIso8601String(), $row['segments'][1]['starts_at']);
        $this->assertSame($this->at('08:30')->toIso8601String(), $row['segments'][1]['ends_at']);
        $this->assertTrue($row['has_collector']);
        $this->assertTrue($row['has_data']);
    }

    public function test_waiting_planned_and_unclassified_stops_map_like_the_line_state(): void
    {
        $line = Line::factory()->create();
        $station = $this->station($line);
        $this->state($station, WorkstationState::WAITING, $this->at('06:00'), $this->at('07:00'));
        $this->state($station, WorkstationState::STOPPED, $this->at('07:00'), $this->at('08:00'));
        $this->state($station, WorkstationState::STOPPED, $this->at('08:00'));
        $this->stop($line, $station, DowntimeReason::factory()->planned()->create(['name' => 'Lunch']), $this->at('07:00'), $this->at('08:00'));
        $this->stop($line, $station, DowntimeReason::factory()->unplanned()->create(['name' => 'AUTO-STOPPED']), $this->at('08:00'), null, true);

        $row = $this->row($line);

        $this->assertSame([['blocked', 0, 60], ['idle', 60, 60], ['down', 120, 120]], $this->shape($row));
        $this->assertSame('Waiting for material', $row['segments'][0]['reason']);
        $this->assertSame('Lunch', $row['segments'][1]['reason']);
        $this->assertNull($row['segments'][2]['reason']);
        $this->assertTrue($row['segments'][2]['needs_reason']);
    }

    public function test_worst_station_wins_each_minute(): void
    {
        $line = Line::factory()->create();
        $a = $this->station($line);
        $b = $this->station($line);
        $c = $this->station($line);
        $this->state($a, WorkstationState::IDLE, $this->at('06:00'));
        $this->state($b, WorkstationState::RUNNING, $this->at('07:00'));
        $this->state($c, WorkstationState::WAITING, $this->at('08:00'), $this->at('09:00'));
        $this->state($c, WorkstationState::FAULT, $this->at('09:00'), $this->at('09:15'));

        $this->assertSame(
            [['idle', 0, 60], ['running', 60, 60], ['blocked', 120, 60], ['down', 180, 15], ['running', 195, 45]],
            $this->shape($this->row($line)),
        );
    }

    public function test_maintenance_event_makes_the_line_down(): void
    {
        $line = Line::factory()->create();
        $this->state($this->station($line), WorkstationState::RUNNING, $this->at('06:00'));
        MaintenanceEvent::create([
            'title' => 'Bearing swap', 'event_type' => MaintenanceEvent::TYPE_CORRECTIVE,
            'status' => MaintenanceEvent::STATUS_IN_PROGRESS,
            'line_id' => $line->id, 'started_at' => $this->at('09:00'),
        ]);

        $row = $this->row($line);

        $this->assertSame([['running', 0, 180], ['down', 180, 60]], $this->shape($row));
        $this->assertSame('Maintenance', $row['segments'][1]['reason']);
    }

    public function test_maintenance_event_is_ignored_when_the_module_is_disabled(): void
    {
        ModuleRegistry::save(array_values(array_diff(ModuleRegistry::optionalKeys(), ['maintenance'])));
        $line = Line::factory()->create();
        $this->state($this->station($line), WorkstationState::RUNNING, $this->at('06:00'));
        MaintenanceEvent::create([
            'title' => 'Bearing swap', 'event_type' => MaintenanceEvent::TYPE_CORRECTIVE,
            'status' => MaintenanceEvent::STATUS_IN_PROGRESS,
            'line_id' => $line->id, 'started_at' => $this->at('09:00'),
        ]);

        $this->assertSame([['running', 0, 240]], $this->shape($this->row($line)));
    }

    public function test_blocked_work_order_blocks_the_line_from_its_last_update(): void
    {
        $line = Line::factory()->create();
        $this->state($this->station($line), WorkstationState::IDLE, $this->at('06:00'));
        WorkOrder::factory()->create([
            'line_id' => $line->id, 'status' => WorkOrder::STATUS_BLOCKED, 'updated_at' => $this->at('09:30'),
        ]);

        $row = $this->row($line);

        $this->assertSame([['idle', 0, 210], ['blocked', 210, 30]], $this->shape($row));
        $this->assertSame('Work order blocked', $row['segments'][1]['reason']);
    }

    // ── uptime ─────────────────────────────────────────────────────────

    public function test_uptime_is_running_time_over_time_since_shift_start(): void
    {
        $line = Line::factory()->create();
        $station = $this->station($line);
        $this->state($station, WorkstationState::RUNNING, $this->at('06:00'), $this->at('08:00'));
        $this->state($station, WorkstationState::IDLE, $this->at('08:00'), $this->at('09:00'));
        $this->state($station, WorkstationState::RUNNING, $this->at('09:00'));

        $timeline = $this->timeline();
        $row = collect($timeline['lines'])->firstWhere('id', $line->id);

        $this->assertSame(480, $timeline['span']);
        $this->assertSame(240, $timeline['elapsed']);
        $this->assertTrue($timeline['is_live']);
        $this->assertSame($this->at('06:00')->toIso8601String(), $timeline['start']);
        $this->assertSame($this->at('14:00')->toIso8601String(), $timeline['end']);
        $this->assertSame(75, $row['uptime']);
        $this->assertSame(['running' => 180, 'idle' => 60, 'blocked' => 0, 'down' => 0, 'none' => 0], $row['minutes']);
    }

    public function test_a_finished_shift_is_drawn_whole_and_never_past_its_end(): void
    {
        $line = Line::factory()->create();
        $this->state($this->station($line), WorkstationState::RUNNING, $this->at('05:00'));

        $this->actingAs($this->admin);
        $window = ShiftWindow::startingOn(Shift::first(), $this->at('00:00', '2026-05-25'));
        $timeline = app(PlantOverviewService::class)->lineTimeline($window);
        $row = collect($timeline['lines'])->firstWhere('id', $line->id);

        $this->assertSame(480, $timeline['elapsed']);
        $this->assertFalse($timeline['is_live']);
        // The state began after that shift ended: nothing is known about it.
        $this->assertFalse($row['has_data']);

        $window = ShiftWindow::current();
        Carbon::setTestNow($this->at('15:00'));
        $timeline = app(PlantOverviewService::class)->lineTimeline($window);
        $row = collect($timeline['lines'])->firstWhere('id', $line->id);

        $this->assertSame(480, $timeline['elapsed']);
        $this->assertSame([['running', 0, 480]], $this->shape($row));
        $this->assertSame(100, $row['uptime']);
    }

    // ── shift over midnight ────────────────────────────────────────────

    public function test_night_shift_over_midnight(): void
    {
        Shift::query()->delete();
        Shift::create([
            'name' => 'Night', 'code' => 'N',
            'start_time' => '22:00:00', 'end_time' => '06:00:00',
            'line_id' => null, 'is_active' => true,
        ]);
        Carbon::setTestNow($this->at('02:00', '2026-05-27'));

        $line = Line::factory()->create();
        $station = $this->station($line);
        $this->state($station, WorkstationState::RUNNING, $this->at('21:30'), $this->at('23:00'));
        $this->state($station, WorkstationState::FAULT, $this->at('23:00'));

        $timeline = $this->timeline();
        $row = collect($timeline['lines'])->firstWhere('id', $line->id);

        $this->assertSame($this->at('22:00')->toIso8601String(), $timeline['start']);
        $this->assertSame($this->at('06:00', '2026-05-27')->toIso8601String(), $timeline['end']);
        $this->assertSame(480, $timeline['span']);
        $this->assertSame(240, $timeline['elapsed']);
        $this->assertSame([['running', 0, 60], ['down', 60, 180]], $this->shape($row));
        $this->assertSame($this->at('02:00', '2026-05-27')->toIso8601String(), $row['segments'][1]['ends_at']);
        $this->assertSame(25, $row['uptime']);
    }

    // ── no data, and lines without a collector ─────────────────────────

    public function test_line_with_no_data_has_no_segments_and_no_uptime(): void
    {
        $line = Line::factory()->create();
        $this->station($line);

        $row = $this->row($line);

        $this->assertFalse($row['has_data']);
        $this->assertSame([], $row['segments']);
        $this->assertNull($row['uptime']);
    }

    public function test_minutes_no_station_reported_are_none_and_not_running(): void
    {
        $line = Line::factory()->create();
        $this->state($this->station($line), WorkstationState::RUNNING, $this->at('08:00'));

        $row = $this->row($line);

        $this->assertSame([['none', 0, 120], ['running', 120, 120]], $this->shape($row));
        $this->assertSame(50, $row['uptime']);
        $this->assertSame(120, $row['minutes']['none']);
    }

    public function test_line_without_collector_uses_batches_and_manual_stops(): void
    {
        $line = Line::factory()->create();
        $this->station($line); // no state rows: nobody's machine reports
        $order = WorkOrder::factory()->create(['line_id' => $line->id, 'status' => WorkOrder::STATUS_IN_PROGRESS]);
        Batch::factory()->create([
            'work_order_id' => $order->id, 'status' => Batch::STATUS_DONE,
            'started_at' => $this->at('07:00'), 'completed_at' => $this->at('09:00'),
        ]);
        $this->stop($line, null, DowntimeReason::factory()->unplanned()->create(['name' => 'Power cut']), $this->at('09:00'), $this->at('09:20'));

        $row = $this->row($line);

        $this->assertFalse($row['has_collector']);
        $this->assertTrue($row['has_data']);
        $this->assertSame([['idle', 0, 60], ['running', 60, 120], ['down', 180, 20], ['idle', 200, 40]], $this->shape($row));
        $this->assertSame('Power cut', $row['segments'][2]['reason']);
        $this->assertSame('manual', $row['segments'][2]['source']);
        $this->assertSame(50, $row['uptime']);
    }

    public function test_line_without_collector_runs_on_an_in_progress_order_without_batches(): void
    {
        $line = Line::factory()->create();
        WorkOrder::factory()->create([
            'line_id' => $line->id, 'status' => WorkOrder::STATUS_IN_PROGRESS, 'updated_at' => $this->at('08:00'),
        ]);

        $this->assertSame([['idle', 0, 120], ['running', 120, 120]], $this->shape($this->row($line)));
    }

    public function test_batches_do_not_override_machine_states_on_a_collector_line(): void
    {
        $line = Line::factory()->create();
        $station = $this->station($line);
        $this->state($station, WorkstationState::IDLE, $this->at('06:00'));
        $order = WorkOrder::factory()->create(['line_id' => $line->id, 'status' => WorkOrder::STATUS_IN_PROGRESS]);
        Batch::factory()->create([
            'work_order_id' => $order->id, 'status' => Batch::STATUS_IN_PROGRESS, 'started_at' => $this->at('07:00'),
        ]);

        $this->assertSame([['idle', 0, 240]], $this->shape($this->row($line)));
    }

    public function test_no_active_lines_gives_an_empty_list(): void
    {
        Line::factory()->create(['is_active' => false]);

        $this->assertSame([], $this->timeline()['lines']);
    }

    // ── query budget ───────────────────────────────────────────────────

    public function test_query_count_does_not_grow_with_line_count(): void
    {
        $this->actingAs($this->admin);
        $service = app(PlantOverviewService::class);
        $reason = DowntimeReason::factory()->unplanned()->create();

        $build = function (int $lines, int $stations) use ($reason) {
            foreach (range(1, $lines) as $i) {
                $line = Line::factory()->create();
                foreach (range(1, $stations) as $j) {
                    $station = $this->station($line);
                    $this->state($station, WorkstationState::RUNNING, $this->at('06:00'), $this->at('08:00'));
                    $this->state($station, WorkstationState::FAULT, $this->at('08:00'));
                    $this->stop($line, $station, $reason, $this->at('08:00'));
                }
                $this->stop($line, null, $reason, $this->at('09:00'), $this->at('09:10'));
                $order = WorkOrder::factory()->create(['line_id' => $line->id, 'status' => WorkOrder::STATUS_BLOCKED]);
                Batch::factory()->create([
                    'work_order_id' => $order->id, 'status' => Batch::STATUS_DONE,
                    'started_at' => $this->at('07:00'), 'completed_at' => $this->at('08:00'),
                ]);
                MaintenanceEvent::create([
                    'title' => 'PM', 'event_type' => MaintenanceEvent::TYPE_PLANNED,
                    'status' => MaintenanceEvent::STATUS_IN_PROGRESS,
                    'line_id' => $line->id, 'started_at' => $this->at('09:00'),
                ]);
            }
        };

        $count = function () use ($service) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $service->lineTimeline();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $build(1, 2);
        $small = $count();

        $build(9, 3);
        $this->assertCount(10, $service->lineTimeline()['lines']);
        $large = $count();

        $this->assertSame($small, $large, 'lineTimeline() must not issue per-line queries');
        $this->assertLessThanOrEqual(12, $large);
    }

    // ── delivery & access ──────────────────────────────────────────────

    public function test_timeline_rides_on_the_overview_prop_and_its_partial_reload(): void
    {
        $line = Line::factory()->create();
        $this->state($this->station($line), WorkstationState::RUNNING, $this->at('06:00'));

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/Dashboard')
                ->has('overview.timeline.lines', 1, fn (AssertableInertia $row) => $row
                    ->where('id', $line->id)
                    ->hasAll(['name', 'has_collector', 'has_data', 'uptime', 'minutes', 'segments']))
                ->reloadOnly('overview', fn (AssertableInertia $reload) => $reload
                    ->has('overview.timeline.lines', 1)
                    ->missing('materialsStats')));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_user_without_dashboard_tab_is_redirected_as_before(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('Supervisor');

        $this->actingAs($supervisor)
            ->get(route('admin.dashboard'))
            ->assertRedirect(TabRegistry::firstAccessibleUrl($supervisor));
    }

    public function test_timeline_is_tenant_isolated(): void
    {
        $tenantA = Tenant::create(['name' => 'Tenant A']);
        $tenantB = Tenant::create(['name' => 'Tenant B']);
        $adminA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $adminA->assignRole('Admin');

        $lineA = Line::factory()->create(['tenant_id' => $tenantA->id]);
        $lineB = Line::factory()->create(['tenant_id' => $tenantB->id]);
        $this->state($this->station($lineA), WorkstationState::RUNNING, $this->at('06:00'));
        $this->state($this->station($lineB), WorkstationState::FAULT, $this->at('06:00'));

        $lines = $this->timeline($adminA)['lines'];

        $this->assertSame([$lineA->id], array_column($lines, 'id'));
        $this->assertSame([['running', 0, 240]], $this->shape($lines[0]));
    }
}
