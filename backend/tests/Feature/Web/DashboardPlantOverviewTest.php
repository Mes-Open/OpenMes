<?php

namespace Tests\Feature\Web;

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
use App\Support\TabRegistry;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The "Plant overview" header of the admin dashboard: the plant shift running
 * now and the live state of every active line, delivered as the `overview`
 * Inertia prop and partial-reloaded by the page.
 */
class DashboardPlantOverviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        // A Tuesday, mid-morning.
        Carbon::setTestNow(Carbon::parse('2026-05-26 10:00:00'));

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

    /** @return array{shift: array<string, mixed>, lines: array<int, array<string, mixed>>} */
    private function overview(?User $user = null): array
    {
        return $this->actingAs($user ?? $this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->inertiaProps('overview');
    }

    /** @return array<string, mixed> */
    private function lineState(Line $line): array
    {
        $row = collect($this->overview()['lines'])->firstWhere('id', $line->id);
        $this->assertNotNull($row, "Line {$line->name} missing from overview");

        return $row;
    }

    private function station(Line $line, ?string $state = null, string $since = '08:00'): Workstation
    {
        $station = Workstation::factory()->create(['line_id' => $line->id]);
        if ($state) {
            WorkstationState::create([
                'workstation_id' => $station->id, 'state' => $state,
                'started_at' => $this->at($since), 'ended_at' => null,
            ]);
        }

        return $station;
    }

    private function openStop(Line $line, ?Workstation $station, ?DowntimeReason $reason, string $since = '09:00', bool $needsReason = false): ProductionDowntime
    {
        return ProductionDowntime::create([
            'line_id' => $line->id,
            'workstation_id' => $station?->id,
            'downtime_reason_id' => $reason?->id,
            'needs_reason' => $needsReason,
            'started_at' => $this->at($since),
            'ended_at' => null,
        ]);
    }

    // ── shift ──────────────────────────────────────────────────────────

    public function test_current_shift_comes_from_configured_shifts(): void
    {
        Shift::create([
            'name' => 'Shift A', 'code' => 'A',
            'start_time' => '06:00:00', 'end_time' => '14:00:00',
            'line_id' => null, 'is_active' => true,
        ]);

        $shift = $this->overview()['shift'];

        $this->assertSame('Shift A', $shift['name']);
        $this->assertFalse($shift['is_default']);
        $this->assertTrue($shift['is_live']);
        $this->assertSame(240, $shift['minutes_left']);
        $this->assertSame($this->at('06:00')->toIso8601String(), $shift['start']);
        $this->assertSame($this->at('14:00')->toIso8601String(), $shift['end']);
    }

    public function test_no_configured_shift_falls_back_to_default_window(): void
    {
        $shift = $this->overview()['shift'];

        $this->assertTrue($shift['is_default']);
        $this->assertNull($shift['name']);
        $this->assertSame($this->at('06:00')->toIso8601String(), $shift['start']);
        $this->assertSame($this->at('18:00')->toIso8601String(), $shift['end']);
        $this->assertSame(480, $shift['minutes_left']);
    }

    // ── machine-reported states ────────────────────────────────────────

    public function test_running_station_makes_line_running(): void
    {
        $line = Line::factory()->create();
        $this->station($line, WorkstationState::RUNNING, '07:30');

        $row = $this->lineState($line);

        $this->assertSame('running', $row['state']);
        $this->assertSame('machine', $row['source']);
        $this->assertTrue($row['has_collector']);
        $this->assertSame($this->at('07:30')->toIso8601String(), $row['since']);
    }

    public function test_fault_is_down_with_the_open_stop_reason(): void
    {
        $line = Line::factory()->create();
        $station = $this->station($line, WorkstationState::FAULT, '09:10');
        $reason = DowntimeReason::factory()->unplanned()->create(['name' => 'Jammed conveyor']);
        $this->openStop($line, $station, $reason, '09:05');

        $row = $this->lineState($line);

        $this->assertSame('down', $row['state']);
        $this->assertSame('Jammed conveyor', $row['reason']);
        $this->assertSame('machine', $row['source']);
        $this->assertSame($this->at('09:05')->toIso8601String(), $row['since']);
    }

    public function test_fault_without_a_stop_says_fault(): void
    {
        $line = Line::factory()->create();
        $this->station($line, WorkstationState::FAULT);

        $row = $this->lineState($line);

        $this->assertSame('down', $row['state']);
        $this->assertSame('Fault', $row['reason']);
    }

    public function test_stopped_with_unplanned_stop_is_down(): void
    {
        $line = Line::factory()->create();
        $station = $this->station($line, WorkstationState::STOPPED);
        $this->openStop($line, $station, DowntimeReason::factory()->unplanned()->create(['name' => 'No operator']));

        $this->assertSame('down', $this->lineState($line)['state']);
    }

    public function test_stopped_with_unclassified_stop_is_down_and_needs_reason(): void
    {
        $line = Line::factory()->create();
        $station = $this->station($line, WorkstationState::STOPPED);
        // The machine opens it with an AUTO-* placeholder reason and the flag.
        $this->openStop($line, $station, DowntimeReason::factory()->unplanned()->create(['name' => 'AUTO-STOPPED']), '09:00', true);

        $row = $this->lineState($line);

        $this->assertSame('down', $row['state']);
        $this->assertNull($row['reason']);
        $this->assertTrue($row['needs_reason']);
    }

    public function test_stopped_with_planned_stop_is_idle(): void
    {
        $line = Line::factory()->create();
        $station = $this->station($line, WorkstationState::STOPPED);
        $this->openStop($line, $station, DowntimeReason::factory()->planned()->create(['name' => 'Lunch break']));

        $row = $this->lineState($line);

        $this->assertSame('idle', $row['state']);
        $this->assertSame('Lunch break', $row['reason']);
    }

    public function test_maintenance_state_is_down(): void
    {
        $line = Line::factory()->create();
        $this->station($line, WorkstationState::MAINTENANCE);

        $row = $this->lineState($line);

        $this->assertSame('down', $row['state']);
        $this->assertSame('Maintenance', $row['reason']);
    }

    public function test_waiting_is_blocked(): void
    {
        $line = Line::factory()->create();
        $this->station($line, WorkstationState::WAITING);

        $row = $this->lineState($line);

        $this->assertSame('blocked', $row['state']);
        $this->assertSame('Waiting for material', $row['reason']);
        $this->assertSame('machine', $row['source']);
    }

    public function test_idle_and_setup_are_idle(): void
    {
        $idle = Line::factory()->create(['name' => 'Idle line']);
        $this->station($idle, WorkstationState::IDLE);
        $setup = Line::factory()->create(['name' => 'Setup line']);
        $this->station($setup, WorkstationState::SETUP);

        $lines = collect($this->overview()['lines'])->keyBy('id');

        $this->assertSame('idle', $lines[$idle->id]['state']);
        $this->assertSame('idle', $lines[$setup->id]['state']);
        $this->assertSame('machine', $lines[$setup->id]['source']);
    }

    public function test_worst_station_wins(): void
    {
        $line = Line::factory()->create();
        $this->station($line, WorkstationState::RUNNING);
        $this->station($line, WorkstationState::FAULT);

        $this->assertSame('down', $this->lineState($line)['state']);
    }

    // ── signals that apply with or without a collector ─────────────────

    public function test_in_progress_maintenance_event_makes_line_down(): void
    {
        $line = Line::factory()->create();
        $this->station($line, WorkstationState::RUNNING);
        MaintenanceEvent::create([
            'title' => 'Bearing swap', 'event_type' => MaintenanceEvent::TYPE_CORRECTIVE,
            'status' => MaintenanceEvent::STATUS_IN_PROGRESS,
            'line_id' => $line->id, 'started_at' => $this->at('09:30'),
        ]);

        $row = $this->lineState($line);

        $this->assertSame('down', $row['state']);
        $this->assertSame('Maintenance', $row['reason']);
        $this->assertSame('manual', $row['source']);
        $this->assertSame($this->at('09:30')->toIso8601String(), $row['since']);
    }

    public function test_maintenance_event_on_a_station_counts_for_its_line(): void
    {
        $line = Line::factory()->create();
        $station = $this->station($line);
        MaintenanceEvent::create([
            'title' => 'Inspection', 'event_type' => MaintenanceEvent::TYPE_INSPECTION,
            'status' => MaintenanceEvent::STATUS_IN_PROGRESS,
            'workstation_id' => $station->id, 'started_at' => $this->at('09:30'),
        ]);

        $this->assertSame('down', $this->lineState($line)['state']);
    }

    public function test_maintenance_event_is_ignored_when_module_is_disabled(): void
    {
        ModuleRegistry::save(array_values(array_diff(ModuleRegistry::optionalKeys(), ['maintenance'])));
        $line = Line::factory()->create();
        MaintenanceEvent::create([
            'title' => 'Bearing swap', 'event_type' => MaintenanceEvent::TYPE_CORRECTIVE,
            'status' => MaintenanceEvent::STATUS_IN_PROGRESS,
            'line_id' => $line->id, 'started_at' => $this->at('09:30'),
        ]);

        $this->assertSame('idle', $this->lineState($line)['state']);
    }

    public function test_blocked_work_order_makes_line_blocked(): void
    {
        $line = Line::factory()->create();
        $this->station($line, WorkstationState::IDLE);
        WorkOrder::factory()->create(['line_id' => $line->id, 'status' => WorkOrder::STATUS_BLOCKED]);

        $row = $this->lineState($line);

        $this->assertSame('blocked', $row['state']);
        $this->assertSame('Work order blocked', $row['reason']);
        $this->assertSame('manual', $row['source']);
    }

    // ── lines without a machine collector ──────────────────────────────

    public function test_line_without_collector_runs_on_in_progress_order(): void
    {
        $line = Line::factory()->create();
        $this->station($line); // no state rows: nobody's machine reports
        WorkOrder::factory()->create(['line_id' => $line->id, 'status' => WorkOrder::STATUS_IN_PROGRESS]);

        $row = $this->lineState($line);

        $this->assertSame('running', $row['state']);
        $this->assertSame('manual', $row['source']);
        $this->assertFalse($row['has_collector']);
    }

    public function test_line_without_collector_is_down_on_manual_line_stop(): void
    {
        $line = Line::factory()->create();
        WorkOrder::factory()->create(['line_id' => $line->id, 'status' => WorkOrder::STATUS_IN_PROGRESS]);
        $this->openStop($line, null, DowntimeReason::factory()->unplanned()->create(['name' => 'Power cut']), '09:40');

        $row = $this->lineState($line);

        $this->assertSame('down', $row['state']);
        $this->assertSame('Power cut', $row['reason']);
        $this->assertSame('manual', $row['source']);
        $this->assertSame($this->at('09:40')->toIso8601String(), $row['since']);
    }

    public function test_line_without_collector_is_down_on_manual_station_stop(): void
    {
        $line = Line::factory()->create();
        $station = $this->station($line);
        $this->openStop($line, $station, DowntimeReason::factory()->unplanned()->create(['name' => 'Tool broke']));

        $row = $this->lineState($line);

        $this->assertSame('down', $row['state']);
        $this->assertSame('Tool broke', $row['reason']);
        $this->assertSame('manual', $row['source']);
    }

    public function test_machine_wins_over_in_progress_order_on_a_collector_line(): void
    {
        $line = Line::factory()->create();
        $this->station($line, WorkstationState::IDLE);
        WorkOrder::factory()->create(['line_id' => $line->id, 'status' => WorkOrder::STATUS_IN_PROGRESS]);

        $row = $this->lineState($line);

        $this->assertSame('idle', $row['state']);
        $this->assertSame('machine', $row['source']);
    }

    public function test_line_with_nothing_going_on_is_idle_without_a_since(): void
    {
        $line = Line::factory()->create();

        $row = $this->lineState($line);

        $this->assertSame('idle', $row['state']);
        $this->assertNull($row['since']);
        $this->assertNull($row['reason']);
        $this->assertSame('manual', $row['source']);
    }

    public function test_inactive_and_deleted_lines_are_excluded(): void
    {
        $active = Line::factory()->create();
        Line::factory()->create(['is_active' => false]);
        Line::factory()->create()->delete();

        $lines = $this->overview()['lines'];

        $this->assertSame([$active->id], array_column($lines, 'id'));
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
                    $station = $this->station($line, $j === 1 ? WorkstationState::FAULT : WorkstationState::RUNNING);
                    $this->openStop($line, $station, $reason);
                }
                $this->openStop($line, null, $reason);
                WorkOrder::factory()->create(['line_id' => $line->id, 'status' => WorkOrder::STATUS_BLOCKED]);
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
            $service->lineStates();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $build(1, 2);
        $small = $count();

        $build(9, 3);
        $this->assertCount(10, $service->lineStates());
        $large = $count();

        $this->assertSame($small, $large, 'lineStates() must not issue per-line queries');
        $this->assertLessThanOrEqual(12, $large);
    }

    // ── delivery & access ──────────────────────────────────────────────

    public function test_partial_reload_returns_only_overview(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/Dashboard')
                ->has('overview.shift')
                ->has('overview.lines')
                ->has('enabledWidgets')
                ->has('widgetOrder')
                ->has('materialsStats')
                ->reloadOnly('overview', fn (AssertableInertia $reload) => $reload
                    ->has('overview.shift')
                    ->missing('materialsStats')
                    ->missing('scrapStats')));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_user_without_dashboard_tab_is_redirected_as_before(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('Supervisor'); // holds tab:orders, not tab:dashboard

        $this->actingAs($supervisor)
            ->get(route('admin.dashboard'))
            ->assertRedirect(TabRegistry::firstAccessibleUrl($supervisor));
    }

    public function test_user_with_dashboard_but_without_orders_tab_cannot_see_order_tab(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');
        $operator->givePermissionTo(TabRegistry::permission('dashboard'));

        $this->actingAs($operator)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('overview.lines')
                ->where('auth.user.accessibleTabs', fn ($tabs) => ! collect($tabs)->contains('orders')));
    }

    public function test_line_states_are_tenant_isolated(): void
    {
        $tenantA = Tenant::create(['name' => 'Tenant A']);
        $tenantB = Tenant::create(['name' => 'Tenant B']);
        $adminA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $adminA->assignRole('Admin');
        $adminB = User::factory()->create(['tenant_id' => $tenantB->id]);
        $adminB->assignRole('Admin');

        $lineA = Line::factory()->create(['name' => 'Line of A', 'tenant_id' => $tenantA->id]);
        $lineB = Line::factory()->create(['name' => 'Line of B', 'tenant_id' => $tenantB->id]);
        $this->station($lineA, WorkstationState::FAULT);
        $this->station($lineB, WorkstationState::FAULT);

        $seenByA = $this->overview($adminA)['lines'];
        $this->assertSame([$lineA->id], array_column($seenByA, 'id'));
        $this->assertSame('Line of A', $seenByA[0]['name']);

        $seenByB = $this->overview($adminB)['lines'];
        $this->assertSame([$lineB->id], array_column($seenByB, 'id'));
    }
}
