<?php

namespace Tests\Feature\Web;

use App\Models\DowntimeReason;
use App\Models\Line;
use App\Models\MachineConnection;
use App\Models\MachineTag;
use App\Models\MaintenanceEvent;
use App\Models\ProductionDowntime;
use App\Models\Shift;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Models\WorkstationState;
use App\Services\Dashboard\PlantOverviewService;
use App\Services\Machine\MachineSignalIngestor;
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

    /**
     * A station, optionally with an open state row. `$seen` is when its
     * collector was last heard from (the row's `updated_at`); default now.
     */
    private function station(Line $line, ?string $state = null, string $since = '08:00', ?string $seen = null, string $source = 'machine'): Workstation
    {
        $station = Workstation::factory()->create(['line_id' => $line->id]);
        if ($state) {
            $row = WorkstationState::create([
                'workstation_id' => $station->id, 'state' => $state,
                'started_at' => $this->at($since), 'ended_at' => null, 'source' => $source,
            ]);
            if ($seen) {
                DB::table('workstation_states')->where('id', $row->id)->update(['updated_at' => $this->at($seen)]);
            }
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

    public function test_minutes_left_is_exact_for_the_frontend_to_round(): void
    {
        Shift::create([
            'name' => 'Shift A', 'code' => 'A',
            'start_time' => '06:00:00', 'end_time' => '12:59:00',
            'line_id' => null, 'is_active' => true,
        ]);

        // 2 h 59 min: the header shows it as such, not truncated to "2 h".
        $this->assertSame(179, $this->overview()['shift']['minutes_left']);
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

    // ── several open stops on one station ──────────────────────────────

    public function test_older_open_stop_is_not_lost(): void
    {
        $line = Line::factory()->create();
        $station = $this->station($line, WorkstationState::STOPPED, '08:30');
        $this->openStop($line, $station, DowntimeReason::factory()->unplanned()->create(['name' => 'Jammed conveyor']), '08:30');
        $this->openStop($line, $station, DowntimeReason::factory()->planned()->create(['name' => 'Lunch break']), '09:10');

        $row = $this->lineState($line);

        $this->assertSame('down', $row['state']);
        $this->assertSame('Jammed conveyor', $row['reason']);
        $this->assertSame($this->at('08:30')->toIso8601String(), $row['since']);
    }

    public function test_oldest_classified_reason_wins_over_newer_placeholder(): void
    {
        $line = Line::factory()->create();
        $station = $this->station($line, WorkstationState::FAULT, '08:30');
        $this->openStop($line, $station, DowntimeReason::factory()->unplanned()->create(['name' => 'Jammed conveyor']), '08:30');
        $this->openStop($line, $station, DowntimeReason::factory()->unplanned()->create(['code' => 'AUTO-FAULT', 'name' => 'Machine fault (auto)']), '09:00', true);

        $row = $this->lineState($line);

        $this->assertSame('down', $row['state']);
        $this->assertSame('Jammed conveyor', $row['reason']);
        $this->assertTrue($row['needs_reason']);
        $this->assertSame($this->at('08:30')->toIso8601String(), $row['since']);
    }

    // ── placeholder stops ask for a reason, whatever the state ─────────

    public function test_maintenance_with_placeholder_stop_needs_reason(): void
    {
        $line = Line::factory()->create();
        $station = $this->station($line, WorkstationState::MAINTENANCE);
        $this->openStop($line, $station, DowntimeReason::factory()->planned()->create(['code' => 'AUTO-MAINT', 'name' => 'Machine maintenance (auto)']), '09:00', true);

        $row = $this->lineState($line);

        $this->assertSame('down', $row['state']);
        $this->assertSame('Maintenance', $row['reason']);
        $this->assertTrue($row['needs_reason']);
    }

    public function test_maintenance_auto_reason_name_is_not_shown(): void
    {
        $line = Line::factory()->create();
        $station = $this->station($line, WorkstationState::MAINTENANCE);
        // Planned AUTO-* stops are opened without the flag: self-explanatory.
        $this->openStop($line, $station, DowntimeReason::factory()->planned()->create(['code' => 'AUTO-MAINT', 'name' => 'Machine maintenance (auto)']));

        $row = $this->lineState($line);

        $this->assertSame('down', $row['state']);
        $this->assertSame('Maintenance', $row['reason']);
        $this->assertFalse($row['needs_reason']);
    }

    public function test_waiting_with_placeholder_stop_needs_reason(): void
    {
        $line = Line::factory()->create();
        $station = $this->station($line, WorkstationState::WAITING);
        $this->openStop($line, $station, DowntimeReason::factory()->unplanned()->create(['code' => 'AUTO-WAIT', 'name' => 'Machine waiting (auto)']), '09:00', true);

        $row = $this->lineState($line);

        $this->assertSame('blocked', $row['state']);
        $this->assertSame('Waiting for material', $row['reason']);
        $this->assertTrue($row['needs_reason']);
    }

    // ── silent collector → "No data" ───────────────────────────────────

    public function test_machine_state_silent_beyond_threshold_is_unknown(): void
    {
        $line = Line::factory()->create();
        $this->station($line, WorkstationState::RUNNING, '07:30', '09:49');

        $row = $this->lineState($line);

        $this->assertSame('unknown', $row['state']);
        $this->assertSame($this->at('09:49')->toIso8601String(), $row['since'], 'no data since the last signal');
        $this->assertNull($row['reason']);
        $this->assertFalse($row['needs_reason']);
        $this->assertSame('machine', $row['source']);
        $this->assertTrue($row['has_collector']);
    }

    public function test_machine_state_at_threshold_is_still_live(): void
    {
        $line = Line::factory()->create();
        $this->station($line, WorkstationState::RUNNING, '07:30', '09:50');

        $this->assertSame('running', $this->lineState($line)['state']);
    }

    public function test_never_refreshed_state_goes_silent_from_its_start(): void
    {
        $line = Line::factory()->create();
        $this->station($line, WorkstationState::FAULT, '10:00');

        Carbon::setTestNow($this->at('10:11'));
        $row = $this->lineState($line);

        $this->assertSame('unknown', $row['state']);
        $this->assertSame($this->at('10:00')->toIso8601String(), $row['since']);
    }

    public function test_unknown_line_returns_after_a_new_signal(): void
    {
        $line = Line::factory()->create();
        $station = $this->station($line);
        $connection = MachineConnection::create([
            'name' => 'Press', 'protocol' => 'modbus', 'is_active' => true, 'status' => 'disconnected',
        ]);
        $tag = MachineTag::create([
            'machine_connection_id' => $connection->id, 'workstation_id' => $station->id,
            'name' => 'state', 'address' => '0', 'signal_type' => MachineTag::SIGNAL_STATE,
            'data_type' => 'int16', 'register_type' => 'holding',
            'transform' => ['value_map' => ['1' => WorkstationState::RUNNING]],
        ]);
        $ingestor = app(MachineSignalIngestor::class);
        $ingestor->ingest($tag, 1, now());

        Carbon::setTestNow($this->at('10:15'));
        $this->assertSame('unknown', $this->lineState($line)['state']);

        // The poller comes back and re-reads the same RUNNING state.
        $ingestor->ingest($tag, 1, now());

        $row = $this->lineState($line);
        $this->assertSame('running', $row['state']);
        $this->assertSame($this->at('10:00')->toIso8601String(), $row['since'], 'still running since the original start');
        $this->assertSame(1, WorkstationState::where('workstation_id', $station->id)->count());
    }

    public function test_silent_collector_still_shows_maintenance_and_blocked_orders(): void
    {
        $maintained = Line::factory()->create(['name' => 'Maintained']);
        $this->station($maintained, WorkstationState::RUNNING, '07:30', '09:00');
        MaintenanceEvent::create([
            'title' => 'Bearing swap', 'event_type' => MaintenanceEvent::TYPE_CORRECTIVE,
            'status' => MaintenanceEvent::STATUS_IN_PROGRESS,
            'line_id' => $maintained->id, 'started_at' => $this->at('09:30'),
        ]);
        $blocked = Line::factory()->create(['name' => 'Blocked']);
        $this->station($blocked, WorkstationState::RUNNING, '07:30', '09:00');
        WorkOrder::factory()->create(['line_id' => $blocked->id, 'status' => WorkOrder::STATUS_BLOCKED]);

        $lines = collect($this->overview()['lines'])->keyBy('id');

        $this->assertSame('down', $lines[$maintained->id]['state']);
        $this->assertSame('blocked', $lines[$blocked->id]['state']);
    }

    public function test_stop_logged_by_hand_on_a_silent_station_still_shows(): void
    {
        $line = Line::factory()->create();
        $station = $this->station($line, WorkstationState::RUNNING, '07:30', '09:00');
        ProductionDowntime::create([
            'line_id' => $line->id, 'workstation_id' => $station->id,
            'downtime_reason_id' => DowntimeReason::factory()->unplanned()->create(['name' => 'Power supply failure'])->id,
            'needs_reason' => false, 'reported_by' => $this->admin->id,
            'started_at' => $this->at('09:05'),
        ]);

        $row = $this->lineState($line);

        $this->assertSame('down', $row['state']);
        $this->assertSame('Power supply failure', $row['reason']);
        $this->assertSame('manual', $row['source']);
        $this->assertSame($this->at('09:05')->toIso8601String(), $row['since']);
    }

    public function test_classified_machine_stop_on_a_silent_station_still_shows(): void
    {
        $line = Line::factory()->create();
        $station = $this->station($line, WorkstationState::FAULT, '08:30', '09:00');
        // Opened by the machine, then given its cause by a supervisor.
        $this->openStop($line, $station, DowntimeReason::factory()->unplanned()->create(['name' => 'Hydraulic leak']), '08:30');

        $row = $this->lineState($line);

        $this->assertSame('down', $row['state']);
        $this->assertSame('Hydraulic leak', $row['reason']);
    }

    public function test_untouched_machine_stop_on_a_silent_station_is_no_data(): void
    {
        $line = Line::factory()->create();
        $station = $this->station($line, WorkstationState::FAULT, '08:30', '09:00');
        $this->openStop($line, $station, DowntimeReason::factory()->unplanned()->create(['code' => 'AUTO-FAULT', 'name' => 'Machine fault (auto)']), '08:30', true);

        $row = $this->lineState($line);

        $this->assertSame('unknown', $row['state']);
        $this->assertNull($row['reason']);
    }

    public function test_gateway_heartbeat_keeps_an_unchanging_fault_live(): void
    {
        $line = Line::factory()->create();
        $station = $this->station($line);
        $connection = MachineConnection::create([
            'name' => 'Press PLC', 'protocol' => 'opcua', 'is_active' => true, 'status' => 'connected',
        ]);
        $tag = MachineTag::create([
            'machine_connection_id' => $connection->id, 'workstation_id' => $station->id,
            'name' => 'State', 'address' => 'ns=2;s=State', 'signal_type' => MachineTag::SIGNAL_STATE,
            'data_type' => 'int16', 'transform' => ['value_map' => ['3' => WorkstationState::FAULT]],
        ]);

        // The press faults; the gateway posts that once (it only posts on change).
        $this->actingAs($this->admin)
            ->postJson("/api/v1/machine-connections/{$connection->id}/signals", ['readings' => [['tag_id' => $tag->id, 'value' => 3]]])
            ->assertOk();

        // 15 minutes later nothing has changed, but the gateway still beats.
        Carbon::setTestNow($this->at('10:15'));
        $this->postJson("/api/v1/machine-connections/{$connection->id}/heartbeat")->assertOk();
        $this->app['auth']->shouldUse('web'); // the API request switched the default guard

        $row = $this->lineState($line);
        $this->assertSame('down', $row['state']);
        $this->assertSame($this->at('10:00')->toIso8601String(), $row['since']);
    }

    public function test_without_a_heartbeat_the_same_fault_reads_no_data(): void
    {
        $line = Line::factory()->create();
        $this->station($line, WorkstationState::FAULT, '10:00');

        Carbon::setTestNow($this->at('10:15'));

        $this->assertSame('unknown', $this->lineState($line)['state']);
    }

    public function test_stop_logged_by_hand_on_a_running_station_shows(): void
    {
        $line = Line::factory()->create();
        $station = $this->station($line, WorkstationState::RUNNING, '07:30');
        ProductionDowntime::create([
            'line_id' => $line->id, 'workstation_id' => $station->id,
            'downtime_reason_id' => DowntimeReason::factory()->unplanned()->create(['name' => 'Quality hold'])->id,
            'needs_reason' => false, 'reported_by' => $this->admin->id,
            'started_at' => $this->at('09:20'),
        ]);

        $row = $this->lineState($line);

        $this->assertSame('down', $row['state']);
        $this->assertSame('Quality hold', $row['reason']);
        $this->assertSame('manual', $row['source']);
    }

    public function test_stop_carried_by_a_fresh_fault_is_not_counted_twice(): void
    {
        $line = Line::factory()->create();
        $station = $this->station($line, WorkstationState::FAULT, '09:00');
        $this->openStop($line, $station, DowntimeReason::factory()->unplanned()->create(['name' => 'Jammed conveyor']), '09:00');

        $row = $this->lineState($line);

        $this->assertSame('down', $row['state']);
        $this->assertSame('Jammed conveyor', $row['reason']);
        $this->assertSame('machine', $row['source']);
    }

    public function test_one_fresh_station_decides_when_another_is_silent(): void
    {
        $line = Line::factory()->create();
        $this->station($line, WorkstationState::IDLE);
        $this->station($line, WorkstationState::FAULT, '08:00', '09:00');

        $row = $this->lineState($line);

        $this->assertSame('idle', $row['state']);
        $this->assertSame('machine', $row['source']);
    }

    public function test_manual_state_never_goes_silent(): void
    {
        $line = Line::factory()->create();
        $this->station($line, WorkstationState::FAULT, '09:00', '09:30', 'manual');

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
                    // One silent collector per line, so both paths are exercised.
                    $station = $this->station($line, $j === 1 ? WorkstationState::FAULT : WorkstationState::RUNNING, '08:00', $j === 2 ? '09:40' : null);
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

    public function test_open_stops_are_read_once(): void
    {
        $this->actingAs($this->admin);
        $line = Line::factory()->create();
        $station = $this->station($line, WorkstationState::FAULT);
        $this->openStop($line, $station, DowntimeReason::factory()->unplanned()->create());
        $this->openStop($line, null, DowntimeReason::factory()->unplanned()->create());

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(PlantOverviewService::class)->lineStates();
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $this->assertCount(1, $queries->filter(fn (string $q) => str_contains($q, 'from "production_downtimes"')));
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

    public function test_overview_carries_planned_vs_actual_production(): void
    {
        Shift::create([
            'name' => 'Shift A', 'code' => 'A',
            'start_time' => '06:00:00', 'end_time' => '14:00:00',
            'line_id' => null, 'is_active' => true,
        ]);
        $line = Line::factory()->create();
        $order = WorkOrder::factory()->create([
            'line_id' => $line->id, 'status' => WorkOrder::STATUS_IN_PROGRESS, 'planned_qty' => 800,
            'planned_start_at' => $this->at('06:00'), 'planned_end_at' => $this->at('14:00'),
        ]);
        $order->update(['produced_qty' => 150]);

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('overview.production.has_lines', true)
                ->where('overview.production.totals.planned', 800)
                ->where('overview.production.totals.actual', 150)
                ->where('overview.production.totals.pace_delta', -250)
                ->where('overview.production.plan_per_hour', 100)
                ->count('overview.production.hours', 8)
                ->has('overview.production.cumulative')
                ->has('overview.production.summary.kind')
                ->reloadOnly('overview', fn (AssertableInertia $reload) => $reload
                    ->where('overview.production.totals.actual', 150)));
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
