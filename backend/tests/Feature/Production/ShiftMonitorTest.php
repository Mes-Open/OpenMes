<?php

namespace Tests\Feature\Production;

use App\Enums\DowntimeKind;
use App\Events\Machine\ShiftMonitorChanged;
use App\Models\Batch;
use App\Models\DowntimeReason;
use App\Models\Issue;
use App\Models\Line;
use App\Models\MachineEvent;
use App\Models\ProductionDowntime;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Models\WorkstationState;
use App\Services\Production\ShiftMonitorService;
use App\Support\ShiftWindow;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Covers the live shift monitor: the derived timeline, the classify/escalate
 * endpoints, and who is allowed to reach them.
 */
class ShiftMonitorTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    private Workstation $workstation;

    private Shift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        // A 06:00–14:00 shift with "now" pinned inside it, so the monitor has a
        // live window whatever time the suite actually runs at.
        Carbon::setTestNow(Carbon::parse('2026-05-26 10:00:00'));

        $this->line = Line::factory()->create();
        $this->workstation = Workstation::factory()->create([
            'line_id' => $this->line->id,
            'ideal_rate_per_hour' => 600, // 10 pcs/min
        ]);
        $this->shift = Shift::create([
            'name' => 'Morning', 'code' => 'S1',
            'start_time' => '06:00:00', 'end_time' => '14:00:00',
            'line_id' => $this->line->id, 'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function window(): ShiftWindow
    {
        return ShiftWindow::occurrence($this->shift, Carbon::now());
    }

    /** Minutes-from-shift-start → an absolute timestamp. */
    private function at(int $minute): Carbon
    {
        return $this->window()->start->copy()->addMinutes($minute);
    }

    private function state(string $state, int $from, ?int $to): WorkstationState
    {
        return WorkstationState::create([
            'workstation_id' => $this->workstation->id,
            'state' => $state,
            'started_at' => $this->at($from),
            'ended_at' => $to === null ? null : $this->at($to),
        ]);
    }

    /** A counter pulse every minute of [$from, $to). */
    private function counters(int $from, int $to, int $perMinute, string $kind = 'good'): void
    {
        for ($m = $from; $m < $to; $m++) {
            MachineEvent::create([
                'workstation_id' => $this->workstation->id,
                'event_type' => MachineEvent::TYPE_COUNTER,
                'event_timestamp' => $this->at($m),
                'payload' => ['delta' => $perMinute, 'kind' => $kind],
            ]);
        }
    }

    /** An auto-recorded stop over [$from, $to) minutes of the shift. */
    private function stop(int $from, ?int $to, bool $needsReason = true, string $code = 'AUTO-STOP'): ProductionDowntime
    {
        return ProductionDowntime::create([
            'line_id' => $this->line->id,
            'workstation_id' => $this->workstation->id,
            'downtime_reason_id' => $this->reason($code, DowntimeKind::Unplanned)->id,
            'needs_reason' => $needsReason,
            'started_at' => $this->at($from),
            'ended_at' => $to === null ? null : $this->at($to),
            'duration_minutes' => $to === null ? null : $to - $from,
        ]);
    }

    private function reason(string $code, DowntimeKind $kind): DowntimeReason
    {
        return DowntimeReason::firstOrCreate(
            ['code' => $code],
            ['name' => ucfirst($code), 'kind' => $kind->value, 'is_active' => true],
        );
    }

    private function supervisor(): User
    {
        Role::findOrCreate('Supervisor', 'web');
        $user = User::factory()->create();
        $user->assignRole('Supervisor');

        return $user;
    }

    public function test_running_minutes_below_nameplate_rate_are_reported_as_speed_loss(): void
    {
        // One RUNNING hour: half at nameplate, half at 40% of it.
        $this->state(WorkstationState::RUNNING, 0, 60);
        $this->counters(0, 30, 10);
        $this->counters(30, 60, 4);

        $snapshot = app(ShiftMonitorService::class)->snapshot($this->workstation, $this->window());

        $kinds = collect($snapshot['hours'][0]['segments'])
            ->groupBy('kind')
            ->map(fn ($group) => $group->sum('minutes'));

        $this->assertSame(30, $kinds['run'], 'minutes at nameplate should read as running');
        $this->assertSame(30, $kinds['slow'], 'minutes below nameplate should read as speed loss');
    }

    public function test_hourly_target_ignores_planned_stops(): void
    {
        // 40 min running, 20 min cleaning. Only the running time was an
        // opportunity to produce, so the target is 40 min × 10 pcs/min.
        $this->state(WorkstationState::RUNNING, 0, 40);
        $this->state(WorkstationState::CLEANING, 40, 60);
        $this->counters(0, 40, 10);

        $snapshot = app(ShiftMonitorService::class)->snapshot($this->workstation, $this->window());

        $this->assertSame(400, $snapshot['hours'][0]['target']);
        $this->assertSame(400, $snapshot['hours'][0]['actual']);
    }

    public function test_an_automatic_unplanned_stop_needs_a_cause_and_a_classified_one_does_not(): void
    {
        $this->state(WorkstationState::STOPPED, 0, 10);
        $this->state(WorkstationState::STOPPED, 20, 30);

        $this->stop(0, 10);
        $this->stop(20, 30, false, 'no_material');

        $snapshot = app(ShiftMonitorService::class)->snapshot($this->workstation, $this->window());
        $stops = collect($snapshot['hours'][0]['segments'])->where('kind', 'down')->values();

        $this->assertTrue($stops[0]['needsCause']);
        $this->assertNull($stops[0]['reason'], 'an unclassified stop must not show the AUTO placeholder as its cause');
        $this->assertFalse($stops[1]['needsCause']);
        // On the code, not the name: the test database pre-seeds this reason, so
        // firstOrCreate returns the seeded row rather than one named from here.
        $this->assertSame('no_material', $stops[1]['reasonCode']);

        $this->assertSame(1, $snapshot['attention']['count']);
    }

    public function test_the_cause_picker_excludes_the_auto_placeholder_reasons(): void
    {
        $this->reason('AUTO-STOP', DowntimeKind::Unplanned);
        $this->reason('no_material', DowntimeKind::Unplanned);
        $this->reason('cleaning', DowntimeKind::Planned);

        // Reference data, so it rides on the page rather than the snapshot.
        $groups = app(ShiftMonitorService::class)->reasonGroups();

        $codes = collect($groups)->flatMap(fn ($g) => collect($g['items'])->pluck('code'));

        $this->assertContains('no_material', $codes);
        $this->assertContains('cleaning', $codes);
        $this->assertNotContains('AUTO-STOP', $codes);
    }

    public function test_a_supervisor_can_assign_a_cause_to_a_stop(): void
    {
        $downtime = $this->stop(0, 10);
        $reason = $this->reason('no_material', DowntimeKind::Unplanned);
        $user = $this->supervisor();

        $this->actingAs($user)
            ->postJson(route('supervisor.shift-monitor.classify', $downtime), [
                'downtime_reason_id' => $reason->id,
                'notes' => 'Line ran dry waiting on media.',
            ])
            ->assertOk();

        $downtime->refresh();
        $this->assertSame($reason->id, $downtime->downtime_reason_id);
        $this->assertFalse($downtime->needs_reason);
        $this->assertSame($user->id, $downtime->classified_by_id);
        $this->assertNotNull($downtime->classified_at);
        $this->assertSame('Line ran dry waiting on media.', $downtime->notes);
    }

    public function test_classifying_rejects_an_auto_placeholder_or_a_missing_reason(): void
    {
        $downtime = $this->stop(0, 10);
        $auto = DowntimeReason::where('code', 'AUTO-STOP')->first();

        $supervisor = $this->supervisor();

        $this->actingAs($supervisor)
            ->postJson(route('supervisor.shift-monitor.classify', $downtime), ['downtime_reason_id' => $auto->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('downtime_reason_id');

        $this->actingAs($supervisor)
            ->postJson(route('supervisor.shift-monitor.classify', $downtime), ['downtime_reason_id' => 999999])
            ->assertStatus(422);

        $this->actingAs($supervisor)
            ->postJson(route('supervisor.shift-monitor.classify', $downtime), [])
            ->assertStatus(422);

        $this->assertTrue($downtime->fresh()->needs_reason, 'a rejected classification must leave the stop unclassified');
    }

    public function test_a_second_supervisor_cannot_silently_overwrite_a_cause(): void
    {
        $downtime = $this->stop(0, 10);
        $first = $this->reason('no_material', DowntimeKind::Unplanned);
        $second = $this->reason('breakdown', DowntimeKind::Unplanned);

        // Refreshes pause while a drawer is open, so both supervisors are
        // holding a snapshot that says this stop has no cause.
        $this->actingAs($this->supervisor())
            ->postJson(route('supervisor.shift-monitor.classify', $downtime), ['downtime_reason_id' => $first->id])
            ->assertOk();

        $this->actingAs($this->supervisor())
            ->postJson(route('supervisor.shift-monitor.classify', $downtime), ['downtime_reason_id' => $second->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('downtime');

        $this->assertSame($first->id, $downtime->fresh()->downtime_reason_id, 'the first decision stands');
    }

    public function test_a_cause_can_be_changed_from_a_current_view(): void
    {
        $downtime = $this->stop(0, 10);
        $first = $this->reason('no_material', DowntimeKind::Unplanned);
        $second = $this->reason('breakdown', DowntimeKind::Unplanned);
        $supervisor = $this->supervisor();

        $this->actingAs($supervisor)
            ->postJson(route('supervisor.shift-monitor.classify', $downtime), ['downtime_reason_id' => $first->id])
            ->assertOk();

        // Somebody who can see the current cause is correcting it, not racing.
        $this->actingAs($supervisor)
            ->postJson(route('supervisor.shift-monitor.classify', $downtime), [
                'downtime_reason_id' => $second->id,
                'seen_classified_at' => $downtime->fresh()->classified_at->toIso8601String(),
            ])
            ->assertOk();

        $this->assertSame($second->id, $downtime->fresh()->downtime_reason_id);
    }

    public function test_escalating_the_same_stop_twice_reuses_the_open_issue(): void
    {
        $order = WorkOrder::factory()->create(['line_id' => $this->line->id]);
        Batch::factory()->create([
            'work_order_id' => $order->id,
            'workstation_id' => $this->workstation->id,
            'status' => Batch::STATUS_IN_PROGRESS,
            'started_at' => $this->at(0),
            'completed_at' => null,
        ]);
        $this->seed(\Database\Seeders\IssueTypesSeeder::class);

        $downtime = $this->stop(10, 20);
        $supervisor = $this->supervisor();

        $this->actingAs($supervisor)
            ->postJson(route('supervisor.shift-monitor.escalate', $downtime), ['note' => 'Head jammed.'])
            ->assertOk();

        // A missed toast, a second click, or a second supervisor on the same
        // stop must not file maintenance a second ticket for one event.
        $this->actingAs($supervisor)
            ->postJson(route('supervisor.shift-monitor.escalate', $downtime), ['note' => 'Head jammed.'])
            ->assertOk();

        $issues = Issue::where('production_downtime_id', $downtime->id)->get();

        $this->assertCount(1, $issues);
        $this->assertSame($order->id, $issues->first()->work_order_id);
    }

    public function test_the_monitor_is_closed_to_guests_and_operators(): void
    {
        $this->get(route('supervisor.shift-monitor.index'))->assertRedirect(route('login'));

        Role::findOrCreate('Operator', 'web');
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator)->get(route('supervisor.shift-monitor.index'))->assertForbidden();

        $downtime = $this->stop(0, 10);

        $this->actingAs($operator)
            ->postJson(route('supervisor.shift-monitor.classify', $downtime), [
                'downtime_reason_id' => $this->reason('no_material', DowntimeKind::Unplanned)->id,
            ])
            ->assertForbidden();

        $this->assertTrue($downtime->fresh()->needs_reason);
    }

    public function test_the_check_endpoint_returns_a_snapshot_for_the_requested_station(): void
    {
        $this->state(WorkstationState::RUNNING, 0, 60);
        $this->counters(0, 60, 10);

        $this->actingAs($this->supervisor())
            ->getJson(route('supervisor.shift-monitor.check', [
                'workstation' => $this->workstation->id,
                'shift' => $this->shift->id,
            ]))
            ->assertOk()
            ->assertJsonPath('data.station.code', $this->workstation->code)
            ->assertJsonPath('data.shift.quantity', 600)
            ->assertJsonPath('selected.workstationId', $this->workstation->id);
    }

    public function test_machine_writes_push_a_nudge_to_the_station_channel(): void
    {
        Event::fake([ShiftMonitorChanged::class]);

        // Each write is its own commit here, so each pushes once, tagged with
        // the source that moved.
        $this->state(WorkstationState::RUNNING, 0, null);
        MachineEvent::create([
            'workstation_id' => $this->workstation->id,
            'event_type' => MachineEvent::TYPE_COUNTER,
            'event_timestamp' => $this->at(1),
            'payload' => ['delta' => 10, 'kind' => 'good'],
        ]);
        $this->stop(2, null);

        foreach (['state', 'counter', 'downtime'] as $reason) {
            Event::assertDispatched(
                ShiftMonitorChanged::class,
                fn (ShiftMonitorChanged $e) => $e->reason === $reason
                    && $e->workstationId === $this->workstation->id,
            );
        }
    }

    public function test_one_transition_pushes_a_single_nudge(): void
    {
        Event::fake([ShiftMonitorChanged::class]);

        // A transition closes the old slice, opens a new one and opens a stop —
        // three writes in one transaction that all say the same thing.
        app(\App\Services\Machine\WorkstationStateMachine::class)
            ->transition($this->workstation, WorkstationState::RUNNING);
        Event::assertDispatchedTimes(ShiftMonitorChanged::class, 1);

        app(\App\Services\Machine\WorkstationStateMachine::class)
            ->transition($this->workstation, WorkstationState::STOPPED);
        Event::assertDispatchedTimes(ShiftMonitorChanged::class, 2);
    }

    public function test_telemetry_does_not_nudge_the_monitor(): void
    {
        Event::fake([ShiftMonitorChanged::class]);

        // The highest-frequency signal on the ingest path, and it changes
        // nothing the monitor draws — pushing it would be pure noise.
        MachineEvent::create([
            'workstation_id' => $this->workstation->id,
            'event_type' => MachineEvent::TYPE_TELEMETRY,
            'event_timestamp' => $this->at(1),
            'payload' => ['temperature' => 42],
        ]);

        Event::assertNotDispatched(ShiftMonitorChanged::class);
    }

    public function test_only_admins_and_supervisors_may_subscribe_to_a_station_channel(): void
    {
        // `NullBroadcaster::auth()` is a no-op, so under the suite's default
        // (`BROADCAST_CONNECTION=null` in phpunit.xml) /broadcasting/auth answers
        // 200 to anyone and this test would assert nothing at all — it only ever
        // passed because the dev container sets BROADCAST_CONNECTION=reverb.
        //
        // Channels register on whichever broadcaster is default when
        // routes/channels.php is loaded at boot, so pinning a real driver is not
        // enough on its own: the callbacks would still sit on the null instance
        // and every channel would read as unregistered (403 for everyone,
        // including the supervisor). Re-register them on the pinned driver.
        config(['broadcasting.default' => 'reverb']);
        require base_path('routes/channels.php');

        Role::findOrCreate('Operator', 'web');
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $channel = "private-shift-monitor.{$this->workstation->id}";

        $this->actingAs($this->supervisor())
            ->postJson('/broadcasting/auth', ['channel_name' => $channel, 'socket_id' => '1234.5678'])
            ->assertOk();

        $this->actingAs($operator)
            ->postJson('/broadcasting/auth', ['channel_name' => $channel, 'socket_id' => '1234.5678'])
            ->assertForbidden();

        // A guest is refused with 403 rather than 401 — the broadcasting auth
        // route rejects the channel outright instead of inviting a login.
        $this->postJson('/broadcasting/auth', ['channel_name' => $channel, 'socket_id' => '1234.5678'])
            ->assertForbidden();
    }

    public function test_a_malformed_date_falls_back_to_the_current_shift(): void
    {
        // A hand-edited query string is a bad request, not a broken page.
        $this->actingAs($this->supervisor())
            ->getJson(route('supervisor.shift-monitor.check', [
                'workstation' => $this->workstation->id,
                'date' => 'not-a-date',
            ]))
            ->assertOk()
            ->assertJsonPath('selected.date', $this->window()->start->toDateString());
    }

    public function test_an_overnight_shift_resolves_on_the_day_it_started(): void
    {
        // Mon–Fri nights: the Friday 22:00 occurrence runs into Saturday without
        // becoming a Saturday shift, and Sunday night is not scheduled merely
        // because Monday is.
        $night = Shift::create([
            'name' => 'Night', 'code' => 'S3',
            'start_time' => '22:00:00', 'end_time' => '06:00:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'line_id' => $this->line->id, 'is_active' => true,
        ]);

        // Saturday 02:00 — inside the Friday-night occurrence.
        $saturdayEarly = Carbon::parse('2026-05-30 02:00:00');
        $window = ShiftWindow::at($this->line->id, $saturdayEarly);
        $this->assertNotNull($window, 'the Friday night shift was still running');
        $this->assertSame($night->id, $window->shift->id);
        $this->assertSame('2026-05-29 22:00', $window->start->format('Y-m-d H:i'));

        // Monday 02:00 — the occurrence started Sunday, which is not scheduled.
        $this->assertNull(
            ShiftWindow::at($this->line->id, Carbon::parse('2026-06-01 02:00:00')),
            'Sunday night is not scheduled, so nothing is running',
        );
    }

    public function test_a_counter_pulse_on_the_shift_boundary_belongs_to_one_shift(): void
    {
        $this->state(WorkstationState::RUNNING, 0, 60);
        $this->counters(0, 60, 10);

        // Exactly on the closing edge — the next shift's opening minute.
        MachineEvent::create([
            'workstation_id' => $this->workstation->id,
            'event_type' => MachineEvent::TYPE_COUNTER,
            'event_timestamp' => $this->window()->end,
            'payload' => ['delta' => 999, 'kind' => 'good'],
        ]);

        $snapshot = app(ShiftMonitorService::class)->snapshot($this->workstation, $this->window());

        // The header must equal the sum of the hour rows on the same screen.
        $hourly = array_sum(array_column($snapshot['hours'], 'actual'));
        $this->assertSame($hourly, $snapshot['shift']['quantity']);
        $this->assertSame(600, $snapshot['shift']['quantity'], 'the boundary pulse belongs to the next shift');
    }

    public function test_one_stop_crossing_an_hour_counts_as_one_thing_to_classify(): void
    {
        // 07:40 → 09:10: three segments once split at hour boundaries, but one
        // decision the supervisor owes.
        $this->state(WorkstationState::STOPPED, 100, 190);
        $this->stop(100, 190);

        $snapshot = app(ShiftMonitorService::class)->snapshot($this->workstation, $this->window());

        $downSegments = collect($snapshot['hours'])
            ->flatMap(fn ($h) => $h['segments'])
            ->where('kind', 'down');

        $this->assertGreaterThan(1, $downSegments->count(), 'the stop really is split across hours');
        $this->assertSame(1, $snapshot['attention']['count']);
        $this->assertSame(1, $snapshot['summary']['unclassified']);
        $this->assertSame(90, $snapshot['attention']['first']['minutes'], 'the whole stop, not one slice');
    }

    public function test_performance_tracks_the_rate_not_the_slow_threshold(): void
    {
        // A whole shift held just under the slow threshold: every minute is
        // classified 'slow', but the station still produced 84% of nameplate.
        $this->state(WorkstationState::RUNNING, 0, 60);
        $this->counters(0, 60, 8); // 8 of a nameplate 10 pcs/min

        $snapshot = app(ShiftMonitorService::class)->snapshot($this->workstation, $this->window());
        $performance = collect($snapshot['analysis']['cards'])->firstWhere('key', 'performance');

        $this->assertSame(80.0, $performance['value'], 'performance is output over expected output');
    }

    public function test_escalate_refuses_when_no_work_order_was_running(): void
    {
        // A batch that closed long before the stop must not be escalated against.
        $workOrder = WorkOrder::factory()->create(['line_id' => $this->line->id]);
        Batch::factory()->create([
            'work_order_id' => $workOrder->id,
            'workstation_id' => $this->workstation->id,
            'status' => Batch::STATUS_DONE,
            'started_at' => $this->at(0)->copy()->subDays(14),
            'completed_at' => $this->at(0)->copy()->subDays(13),
        ]);

        $downtime = $this->stop(10, 20);

        $this->actingAs($this->supervisor())
            ->postJson(route('supervisor.shift-monitor.escalate', $downtime), ['note' => 'glue temp'])
            ->assertStatus(422);

        // Scoped to this order: the shared dev database already holds issues.
        $this->assertDatabaseMissing('issues', ['work_order_id' => $workOrder->id]);
    }

    public function test_escalate_files_against_the_order_that_was_running(): void
    {
        $workOrder = WorkOrder::factory()->create(['line_id' => $this->line->id]);
        Batch::factory()->create([
            'work_order_id' => $workOrder->id,
            'workstation_id' => $this->workstation->id,
            'status' => Batch::STATUS_IN_PROGRESS,
            'started_at' => $this->at(0),
            'completed_at' => null,
        ]);
        $this->seed(\Database\Seeders\IssueTypesSeeder::class);

        $downtime = $this->stop(10, 20);

        $this->actingAs($this->supervisor())
            ->postJson(route('supervisor.shift-monitor.escalate', $downtime), ['note' => 'glue temp'])
            ->assertOk();

        $this->assertDatabaseHas('issues', ['work_order_id' => $workOrder->id, 'status' => 'OPEN']);
    }

    public function test_a_rolled_back_write_does_not_mute_the_station(): void
    {
        Event::fake([ShiftMonitorChanged::class]);

        // A failed transaction discards the after-commit callback that would
        // have cleared the dedupe flag; the station must not go silent for the
        // life of the process.
        try {
            \Illuminate\Support\Facades\DB::transaction(function () {
                MachineEvent::create([
                    'workstation_id' => $this->workstation->id,
                    'event_type' => MachineEvent::TYPE_COUNTER,
                    'event_timestamp' => $this->at(1),
                    'payload' => ['delta' => 5, 'kind' => 'good'],
                ]);
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
            // expected
        }

        Event::assertNotDispatched(ShiftMonitorChanged::class);

        MachineEvent::create([
            'workstation_id' => $this->workstation->id,
            'event_type' => MachineEvent::TYPE_COUNTER,
            'event_timestamp' => $this->at(2),
            'payload' => ['delta' => 5, 'kind' => 'good'],
        ]);

        Event::assertDispatchedTimes(ShiftMonitorChanged::class, 1);
    }

    public function test_overlapping_state_rows_cannot_inflate_the_hourly_target(): void
    {
        // A workstation is in one state at a time and the state machine keeps it
        // that way, but a stale open slice from an earlier window would
        // otherwise have its minutes counted alongside the real ones — showing
        // up as an hourly target several times the nameplate rate.
        $this->state(WorkstationState::RUNNING, 0, 60);
        WorkstationState::create([
            'workstation_id' => $this->workstation->id,
            'state' => WorkstationState::RUNNING,
            'started_at' => $this->at(0)->copy()->subDay(),
            'ended_at' => null,
        ]);
        $this->counters(0, 60, 10);

        $snapshot = app(ShiftMonitorService::class)->snapshot($this->workstation, $this->window());

        $this->assertSame(600, $snapshot['hours'][0]['target'], 'one hour at 10 pcs/min, counted once');
    }

    public function test_minutes_the_machine_reported_nothing_for_are_drawn_as_a_gap(): void
    {
        // The collector was down between :20 and :35. Before this, those
        // minutes rendered as bare track — exactly like idle time — so a dead
        // poller read as a quiet machine and nobody went looking.
        $this->state(WorkstationState::RUNNING, 0, 20);
        $this->state(WorkstationState::RUNNING, 35, 60);

        $snapshot = app(ShiftMonitorService::class)->snapshot($this->workstation, $this->window());

        $gaps = collect($snapshot['hours'][0]['segments'])->where('kind', 'none')->values();

        $this->assertCount(1, $gaps);
        $this->assertSame(20, $gaps[0]['from']);
        $this->assertSame(15, $gaps[0]['minutes']);
    }

    public function test_a_gap_is_never_counted_towards_running_stopped_or_planned_time(): void
    {
        $this->state(WorkstationState::RUNNING, 0, 20);
        $this->state(WorkstationState::RUNNING, 35, 60);
        $this->counters(0, 20, 10);
        $this->counters(35, 60, 10);

        $snapshot = app(ShiftMonitorService::class)->snapshot($this->workstation, $this->window());
        $waterfall = collect($snapshot['analysis']['waterfall'])->keyBy('key');

        // Unknown time is unknown: folding it into any bucket would put a guess
        // into the OEE arithmetic. It shows up only as the shortfall between
        // elapsed time and everything accounted for.
        $this->assertSame(0, $waterfall['planned']['minutes']);
        $this->assertSame(0, $waterfall['unplanned']['minutes']);
        $this->assertSame(45, $waterfall['effective']['minutes']);
    }

    public function test_future_minutes_of_the_current_hour_are_not_a_gap(): void
    {
        // "now" is 10:00 in a 06:00 shift, so the shift is 240 minutes in.
        $this->state(WorkstationState::RUNNING, 0, 240);

        $snapshot = app(ShiftMonitorService::class)->snapshot($this->workstation, $this->window());

        $gaps = collect($snapshot['hours'])->flatMap(fn (array $h) => $h['segments'])->where('kind', 'none');

        $this->assertCount(0, $gaps, 'time that has not happened yet is not missing data');
    }

    public function test_a_stop_left_open_days_ago_is_not_attached_to_todays_stops(): void
    {
        // A collector killed mid-fault leaves a downtime with no ended_at. Read
        // literally it is still running, so it overlaps every stop in every
        // later shift — and classifying today's stop would write the cause onto
        // a stop from last week while today's stayed unexplained.
        $stale = ProductionDowntime::create([
            'line_id' => $this->line->id,
            'workstation_id' => $this->workstation->id,
            'downtime_reason_id' => $this->reason('AUTO-STOP', DowntimeKind::Unplanned)->id,
            'needs_reason' => true,
            'started_at' => $this->at(0)->copy()->subDays(3),
            'ended_at' => null,
        ]);

        $this->state(WorkstationState::RUNNING, 0, 20);
        $this->state(WorkstationState::STOPPED, 20, 30);
        $this->state(WorkstationState::RUNNING, 30, 60);
        $todays = $this->stop(20, 30);

        $snapshot = app(ShiftMonitorService::class)->snapshot($this->workstation, $this->window());
        $stops = collect($snapshot['hours'][0]['segments'])->where('kind', 'down')->values();

        $this->assertCount(1, $stops);
        $this->assertSame($todays->id, $stops[0]['downtimeId']);
        $this->assertNotSame($stale->id, $stops[0]['downtimeId']);
    }

    public function test_a_stop_still_running_from_before_the_shift_is_still_matched(): void
    {
        // The mirror case: a machine genuinely down since before the shift
        // opened. Nothing has closed the stop because it has not ended.
        $ongoing = ProductionDowntime::create([
            'line_id' => $this->line->id,
            'workstation_id' => $this->workstation->id,
            'downtime_reason_id' => $this->reason('AUTO-STOP', DowntimeKind::Unplanned)->id,
            'needs_reason' => true,
            'started_at' => $this->at(0)->copy()->subMinutes(30),
            'ended_at' => null,
        ]);

        $this->state(WorkstationState::FAULT, 0, 20);

        $snapshot = app(ShiftMonitorService::class)->snapshot($this->workstation, $this->window());
        $stops = collect($snapshot['hours'][0]['segments'])->where('kind', 'down')->values();

        $this->assertSame($ongoing->id, $stops[0]['downtimeId']);
    }

    public function test_lost_pieces_are_the_shortfall_not_the_whole_expectation(): void
    {
        // 20 min at rate, 20 min at 40% of it, 20 min stopped.
        $this->state(WorkstationState::RUNNING, 0, 40);
        $this->state(WorkstationState::STOPPED, 40, 60);
        $this->counters(0, 20, 10);
        $this->counters(20, 40, 4);
        $this->stop(40, 60);

        $snapshot = app(ShiftMonitorService::class)->snapshot($this->workstation, $this->window());
        $byKind = collect($snapshot['hours'][0]['segments'])->keyBy('kind');

        // Running at rate lost nothing — the drawer used to report its whole
        // expected output as lost.
        $this->assertSame(0, $byKind['run']['lost']);
        $this->assertSame(200, $byKind['run']['produced']);
        // Slow: expected 200, made 80.
        $this->assertSame(120, $byKind['slow']['lost']);
        // Stopped: everything it could have made.
        $this->assertSame(200, $byKind['down']['lost']);
    }

    public function test_scrap_counters_lower_the_quality_factor(): void
    {
        $this->state(WorkstationState::RUNNING, 0, 60);
        $this->counters(0, 60, 9);
        $this->counters(0, 60, 1, 'reject');

        $snapshot = app(ShiftMonitorService::class)->snapshot($this->workstation, $this->window());

        $this->assertSame(600, $snapshot['shift']['quantity']);
        $quality = collect($snapshot['analysis']['cards'])->firstWhere('key', 'quality');
        $this->assertSame(90.0, $quality['value']);
    }
}
