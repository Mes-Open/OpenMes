<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ScheduledOperationStatus;
use App\Models\DowntimeReason;
use App\Models\Line;
use App\Models\MachineEvent;
use App\Models\ProductionDowntime;
use App\Models\ScheduledOperation;
use App\Models\Shift;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Models\WorkstationState;
use App\Services\Dashboard\PlantOverviewService;
use App\Services\Dashboard\ProductionTargets;
use App\Services\Production\ShiftMonitorService;
use App\Support\ShiftWindow;
use App\Support\TabRegistry;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The "OEE · this shift" section of the admin dashboard: live plant and line
 * OEE from the shift monitor's machine data, against ProductionTargets.
 */
class PlantOverviewOeeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        // A Tuesday, four hours into the 06:00–14:00 shift.
        Carbon::setTestNow(Carbon::parse('2026-05-26 10:00:00'));

        Shift::create([
            'name' => 'Morning', 'code' => 'S1',
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

    private function at(string $time): Carbon
    {
        return Carbon::parse('2026-05-26 '.$time);
    }

    /** @return array<string, mixed> */
    private function oee(?User $user = null): array
    {
        $this->actingAs($user ?? $this->admin);

        return app(PlantOverviewService::class)->shiftOee();
    }

    /** @return array<string, mixed> */
    private function lineRow(array $oee, Line $line): array
    {
        $row = collect($oee['lines'])->firstWhere('id', $line->id);
        $this->assertNotNull($row, "Line {$line->name} missing from shift OEE");

        return $row;
    }

    private function station(Line $line, int $idealPerHour = 600): Workstation
    {
        return Workstation::factory()->create(['line_id' => $line->id, 'ideal_rate_per_hour' => $idealPerHour]);
    }

    private function state(Workstation $station, string $state, string $from, ?string $to = null): void
    {
        WorkstationState::create([
            'workstation_id' => $station->id, 'state' => $state,
            'started_at' => $this->at($from), 'ended_at' => $to ? $this->at($to) : null,
        ]);
    }

    private function fault(Workstation $station, string $from, string $to): void
    {
        $this->state($station, WorkstationState::FAULT, $from, $to);
        ProductionDowntime::create([
            'line_id' => $station->line_id,
            'workstation_id' => $station->id,
            'downtime_reason_id' => DowntimeReason::factory()->unplanned()->create()->id,
            'needs_reason' => false,
            'started_at' => $this->at($from),
            'ended_at' => $this->at($to),
        ]);
    }

    private function pulse(Workstation $station, float $good, float $reject = 0, string $at = '06:00:10'): void
    {
        $base = [
            'workstation_id' => $station->id,
            'event_type' => MachineEvent::TYPE_COUNTER,
            'event_timestamp' => $this->at($at),
        ];
        MachineEvent::insert($base + ['payload' => json_encode(['delta' => $good])]);
        if ($reject > 0) {
            MachineEvent::insert($base + ['payload' => json_encode(['delta' => $reject, 'kind' => 'reject'])]);
        }
    }

    /** RUNNING all shift with a 30-minute fault, 1500 good + 50 scrap. */
    private function busyStation(Line $line, int $idealPerHour = 600): Workstation
    {
        $station = $this->station($line, $idealPerHour);
        $this->state($station, WorkstationState::RUNNING, '06:00', '09:00');
        $this->fault($station, '09:00', '09:30');
        $this->state($station, WorkstationState::RUNNING, '09:30');
        $this->pulse($station, 1500, 50);

        return $station;
    }

    /** RUNNING since 08:00 on a slow nameplate, beating it. */
    private function lateStation(Line $line): Workstation
    {
        $station = $this->station($line, 60);
        $this->state($station, WorkstationState::RUNNING, '08:00');
        $this->pulse($station, 150, 0, '08:00:10');

        return $station;
    }

    /** @return array<int, array<string, mixed>> */
    private function inputs(Workstation ...$stations): array
    {
        return app(ShiftMonitorService::class)->oeeInputs(collect($stations), ShiftWindow::current());
    }

    // ── agreement with Plant Board ─────────────────────────────────────

    public function test_a_single_station_line_matches_its_plant_board_tile(): void
    {
        $line = Line::factory()->create();
        $station = $this->busyStation($line);

        $tile = app(ShiftMonitorService::class)->board(collect([$station->fresh()]), ShiftWindow::current())[0];
        $row = $this->lineRow($this->oee(), $line);

        foreach (['availability', 'performance', 'quality', 'oee'] as $key) {
            $this->assertSame($tile[$key], $row[$key], "{$key} differs from Plant Board");
        }
        $this->assertEqualsWithDelta(87.5, $row['availability'], 0.01);
        $this->assertTrue($row['has_data']);
    }

    // ── aggregation ────────────────────────────────────────────────────

    public function test_line_figures_weigh_stations_by_time_and_pieces(): void
    {
        $line = Line::factory()->create();
        $busy = $this->busyStation($line);
        $late = $this->lateStation($line);

        $in = $this->inputs($busy, $late);
        $a = $in[$busy->id];
        $b = $in[$late->id];

        $availability = ($a['running'] + $b['running']) / ($a['opportunity'] + $b['opportunity']) * 100;
        $performance = (min($a['produced'], $a['expected']) + min($b['produced'], $b['expected'])) / ($a['expected'] + $b['expected']) * 100;
        $quality = ($a['good'] + $b['good']) / ($a['produced'] + $b['produced']) * 100;

        $row = $this->lineRow($this->oee(), $line);

        $this->assertSame(round($availability, 1), $row['availability']);
        $this->assertSame(round($performance, 1), $row['performance']);
        $this->assertSame(round($quality, 1), $row['quality']);
        $this->assertSame(round($availability * $performance * $quality / 10000, 1), $row['oee']);

        // The late station beats its nameplate — capped at its own expectation,
        // so it can't make up for the busy station's speed loss.
        $this->assertSame(100.0, $b['oee']['performance']);
        // …and its two hours weigh less than the busy station's four.
        $plainAverage = round(($a['oee']['availability'] + $b['oee']['availability']) / 2, 1);
        $this->assertNotSame($plainAverage, $row['availability']);
    }

    public function test_plant_figures_aggregate_stations_not_line_percentages(): void
    {
        $lineA = Line::factory()->create(['name' => 'A']);
        $lineB = Line::factory()->create(['name' => 'B']);
        $busy = $this->busyStation($lineA);
        $late = $this->lateStation($lineB);

        $expected = PlantOverviewService::aggregateOee(array_values($this->inputs($busy, $late)));
        $oee = $this->oee();

        foreach (['availability', 'performance', 'quality', 'oee'] as $key) {
            $this->assertSame($expected[$key], $oee[$key]);
        }
        $this->assertTrue($oee['has_data']);

        $plainAverage = round(($this->lineRow($oee, $lineA)['availability'] + $this->lineRow($oee, $lineB)['availability']) / 2, 1);
        $this->assertNotSame($plainAverage, $oee['availability']);
    }

    public function test_aggregate_of_nothing_is_null_without_dividing_by_zero(): void
    {
        $this->assertSame(
            ['oee' => null, 'availability' => null, 'performance' => null, 'quality' => null, 'has_data' => false],
            PlantOverviewService::aggregateOee([]),
        );

        // Ran, but no nameplate rate and no pieces: availability only.
        $result = PlantOverviewService::aggregateOee([
            ['opportunity' => 60, 'running' => 45, 'expected' => 0.0, 'produced' => 0, 'good' => 0],
        ]);
        $this->assertSame(75.0, $result['availability']);
        $this->assertNull($result['performance']);
        $this->assertNull($result['quality']);
        $this->assertNull($result['oee']);
        $this->assertTrue($result['has_data']);
    }

    // ── biggest loss ───────────────────────────────────────────────────

    public function test_biggest_loss_is_the_lowest_component(): void
    {
        $this->assertSame('availability', PlantOverviewService::biggestLoss(['availability' => 60.0, 'performance' => 90.0, 'quality' => 99.0]));
        $this->assertSame('performance', PlantOverviewService::biggestLoss(['availability' => 95.0, 'performance' => 70.0, 'quality' => 99.0]));
        $this->assertSame('quality', PlantOverviewService::biggestLoss(['availability' => 95.0, 'performance' => 90.0, 'quality' => 80.0]));
    }

    public function test_biggest_loss_ties_go_in_component_order_and_nulls_are_skipped(): void
    {
        $this->assertSame('availability', PlantOverviewService::biggestLoss(['availability' => 80.0, 'performance' => 80.0, 'quality' => 80.0]));
        $this->assertSame('performance', PlantOverviewService::biggestLoss(['availability' => 90.0, 'performance' => 80.0, 'quality' => 80.0]));
        $this->assertSame('quality', PlantOverviewService::biggestLoss(['availability' => null, 'performance' => null, 'quality' => 80.0]));
        $this->assertNull(PlantOverviewService::biggestLoss(['availability' => null, 'performance' => null, 'quality' => null]));
    }

    public function test_long_stop_makes_availability_the_biggest_loss(): void
    {
        $line = Line::factory()->create();
        $station = $this->station($line, 600);
        $this->state($station, WorkstationState::RUNNING, '06:00', '07:00');
        $this->fault($station, '07:00', '09:00');
        $this->state($station, WorkstationState::RUNNING, '09:00');
        // At nameplate for the two running hours, no scrap.
        $this->pulse($station, 1200);

        $oee = $this->oee();

        $this->assertSame('availability', $oee['biggest_loss']);
        $this->assertEqualsWithDelta(50.0, $oee['availability'], 0.01);
    }

    // ── targets ────────────────────────────────────────────────────────

    public function test_each_line_carries_its_own_target(): void
    {
        $tuned = Line::factory()->create(['planning_efficiency_pct' => 80]);
        $default = Line::factory()->create(['planning_efficiency_pct' => 100]);

        $oee = $this->oee();

        $this->assertSame(80.0, $this->lineRow($oee, $tuned)['target']);
        $this->assertSame(85.0, $this->lineRow($oee, $default)['target']);
        // Nothing planned this shift: the plant default.
        $this->assertSame(85.0, $oee['target']);
    }

    public function test_plant_target_is_the_production_targets_weighted_figure(): void
    {
        $a = Line::factory()->create(['planning_efficiency_pct' => 80]);
        $b = Line::factory()->create(['planning_efficiency_pct' => 100]);
        $this->plan($a, '06:00', '12:00'); // 360 min
        $this->plan($b, '06:00', '08:00'); // 120 min

        $oee = $this->oee();

        $this->assertSame(round(app(ProductionTargets::class)->plantOeeTarget(ShiftWindow::current()), 1), $oee['target']);
        // (80·360 + 85·120) / 480
        $this->assertSame(81.3, $oee['target']);
    }

    public function test_gap_and_on_target_against_the_plant_target(): void
    {
        $line = Line::factory()->create();
        $this->busyStation($line);

        $below = $this->oee();
        $this->assertLessThan(85.0, $below['oee']);
        $this->assertFalse($below['on_target']);
        $this->assertSame(round(85.0 - $below['oee'], 1), $below['gap_pts']);

        // Same line held to a target it already beats.
        $line->update(['planning_efficiency_pct' => 50]);
        $this->plan($line, '06:00', '14:00');

        $above = $this->oee();
        $this->assertSame(50.0, $above['target']);
        $this->assertTrue($above['on_target']);
        $this->assertNull($above['gap_pts']);
    }

    private function plan(Line $line, string $start, string $end): void
    {
        ScheduledOperation::create([
            'work_order_id' => WorkOrder::factory()->create(['line_id' => $line->id])->id,
            'step_number' => 1,
            'line_id' => $line->id,
            'planned_start_at' => $this->at($start),
            'planned_end_at' => $this->at($end),
            'status' => ScheduledOperationStatus::Scheduled->value,
        ]);
    }

    // ── no data ────────────────────────────────────────────────────────

    public function test_lines_without_machine_data_are_null_not_zero(): void
    {
        $silent = Line::factory()->create();
        $this->station($silent); // never reported
        $empty = Line::factory()->create(); // no stations at all

        $oee = $this->oee();

        foreach ([$silent, $empty] as $line) {
            $row = $this->lineRow($oee, $line);
            $this->assertFalse($row['has_data']);
            $this->assertNull($row['oee']);
            $this->assertNull($row['availability']);
            $this->assertNull($row['performance']);
            $this->assertNull($row['quality']);
        }

        $this->assertFalse($oee['has_data']);
        $this->assertNull($oee['oee']);
        $this->assertNull($oee['biggest_loss']);
        $this->assertNull($oee['gap_pts']);
        $this->assertNull($oee['on_target']);
        $this->assertSame(85.0, $oee['target']);
    }

    public function test_no_active_lines_gives_an_empty_list(): void
    {
        Line::factory()->create(['is_active' => false]);

        $oee = $this->oee();

        $this->assertSame([], $oee['lines']);
        $this->assertFalse($oee['has_data']);
        $this->assertNull($oee['oee']);
    }

    // ── sorting ────────────────────────────────────────────────────────

    public function test_lines_are_sorted_best_first_with_no_data_last(): void
    {
        $worse = Line::factory()->create(['name' => 'A worse']);
        $silent = Line::factory()->create(['name' => 'B silent']);
        $better = Line::factory()->create(['name' => 'C better']);

        $this->busyStation($worse);
        $good = $this->station($better, 600);
        $this->state($good, WorkstationState::RUNNING, '06:00');
        $this->pulse($good, 2400);

        $this->assertSame(
            [$better->id, $worse->id, $silent->id],
            array_column($this->oee()['lines'], 'id'),
        );
    }

    // ── query budget ───────────────────────────────────────────────────

    public function test_query_count_does_not_grow_with_lines_or_stations(): void
    {
        $this->actingAs($this->admin);
        $service = app(PlantOverviewService::class);

        $build = function (int $lines, int $stations) {
            foreach (range(1, $lines) as $i) {
                $line = Line::factory()->create();
                $this->plan($line, '06:00', '12:00');
                foreach (range(1, $stations) as $j) {
                    $this->busyStation($line);
                }
            }
        };

        $count = function () use ($service) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $service->shiftOee();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $build(1, 1);
        $small = $count();

        $build(4, 3);
        $large = $count();

        $this->assertSame($small, $large);
    }

    // ── delivery, access & tenancy ─────────────────────────────────────

    public function test_dashboard_delivers_the_oee_payload_and_refreshes_it(): void
    {
        $line = Line::factory()->create(['planning_efficiency_pct' => 80]);
        $this->busyStation($line);

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/Dashboard')
                ->has('overview.oee.target')
                ->has('overview.oee.lines', 1)
                ->where('overview.oee.lines.0.target', 80)
                ->reloadOnly('overview', fn (AssertableInertia $reload) => $reload
                    ->has('overview.oee.lines')
                    ->missing('materialsStats')));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_user_without_dashboard_tab_does_not_get_the_payload(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('Supervisor'); // holds tab:orders, not tab:dashboard

        $this->actingAs($supervisor)
            ->get(route('admin.dashboard'))
            ->assertRedirect(TabRegistry::firstAccessibleUrl($supervisor));
    }

    public function test_shift_oee_is_tenant_isolated(): void
    {
        $tenantA = Tenant::create(['name' => 'Tenant A']);
        $tenantB = Tenant::create(['name' => 'Tenant B']);
        $adminA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $adminA->assignRole('Admin');

        $lineA = Line::factory()->create(['tenant_id' => $tenantA->id]);
        $lineB = Line::factory()->create(['tenant_id' => $tenantB->id]);
        $station = $this->station($lineA, 600);
        $this->state($station, WorkstationState::RUNNING, '06:00');
        $this->pulse($station, 2400);
        // Tenant B's long fault would drag A's availability down if it leaked.
        $other = $this->station($lineB, 600);
        $this->fault($other, '06:00', '10:00');

        $oee = $this->oee($adminA);

        $this->assertSame([$lineA->id], array_column($oee['lines'], 'id'));
        $this->assertEqualsWithDelta(100.0, $oee['availability'], 0.01);
    }
}
