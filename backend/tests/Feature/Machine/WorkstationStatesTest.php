<?php

namespace Tests\Feature\Machine;

use App\Enums\DowntimeKind;
use App\Models\Line;
use App\Models\ProductionDowntime;
use App\Models\Workstation;
use App\Models\WorkstationState;
use App\Services\Machine\WorkstationStateMachine;
use App\Services\Production\DowntimeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Additional machine states (#87): waiting / cleaning / maintenance. Verifies
 * the state transitions, the downtime each opens (and its availability
 * treatment), and that existing states/data are unaffected.
 */
class WorkstationStatesTest extends TestCase
{
    use RefreshDatabase;

    private WorkstationStateMachine $machine;

    private Line $line;

    private Workstation $workstation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->machine = app(WorkstationStateMachine::class);
        $this->line = Line::factory()->create();
        $this->workstation = Workstation::factory()->create(['line_id' => $this->line->id]);
    }

    private function makeWorkstation(): Workstation
    {
        return Workstation::factory()->create(['line_id' => $this->line->id]);
    }

    public function test_new_states_are_recognised(): void
    {
        foreach ([WorkstationState::WAITING, WorkstationState::CLEANING, WorkstationState::MAINTENANCE] as $state) {
            $this->assertContains($state, WorkstationState::STATES);
        }
    }

    public function test_transition_to_each_new_state_is_recorded_in_history(): void
    {
        foreach ([WorkstationState::WAITING, WorkstationState::CLEANING, WorkstationState::MAINTENANCE] as $state) {
            $ws = $this->makeWorkstation();
            $this->machine->transition($ws, $state);

            $this->assertDatabaseHas('workstation_states', [
                'workstation_id' => $ws->id,
                'state' => $state,
                'ended_at' => null,
            ]);
        }
    }

    public function test_waiting_opens_an_unplanned_downtime(): void
    {
        $this->machine->transition($this->workstation, WorkstationState::WAITING);

        $downtime = ProductionDowntime::where('workstation_id', $this->workstation->id)->whereNull('ended_at')->first();
        $this->assertNotNull($downtime);
        $this->assertSame(DowntimeKind::Unplanned->value, $downtime->reason->kind->value);
    }

    public function test_cleaning_and_maintenance_open_planned_downtime(): void
    {
        foreach ([WorkstationState::CLEANING, WorkstationState::MAINTENANCE] as $state) {
            $ws = $this->makeWorkstation();
            $this->machine->transition($ws, $state);

            $downtime = ProductionDowntime::where('workstation_id', $ws->id)->whereNull('ended_at')->first();
            $this->assertNotNull($downtime, "$state should open a downtime");
            $this->assertSame(DowntimeKind::Planned->value, $downtime->reason->kind->value, "$state should be planned");
        }
    }

    public function test_leaving_a_downtime_state_closes_the_downtime_with_duration(): void
    {
        $t0 = Carbon::parse('2026-06-22 10:00:00');
        $this->machine->transition($this->workstation, WorkstationState::MAINTENANCE, [], $t0);
        $this->machine->transition($this->workstation, WorkstationState::RUNNING, [], $t0->copy()->addMinutes(30));

        $downtime = ProductionDowntime::where('workstation_id', $this->workstation->id)->first();
        $this->assertNotNull($downtime->ended_at);
        $this->assertSame(30, $downtime->duration_minutes);
    }

    public function test_existing_states_keep_their_behaviour(): void
    {
        // STOPPED still opens an unplanned downtime.
        $stopped = $this->makeWorkstation();
        $this->machine->transition($stopped, WorkstationState::STOPPED);
        $this->assertSame(
            DowntimeKind::Unplanned->value,
            ProductionDowntime::where('workstation_id', $stopped->id)->first()->reason->kind->value,
        );

        // IDLE and SETUP open no downtime (unchanged).
        foreach ([WorkstationState::IDLE, WorkstationState::SETUP] as $state) {
            $ws = $this->makeWorkstation();
            $this->machine->transition($ws, $state);
            $this->assertDatabaseMissing('production_downtimes', ['workstation_id' => $ws->id]);
        }
    }

    public function test_manual_source_is_recorded(): void
    {
        $this->machine->transition($this->workstation, WorkstationState::CLEANING, [], null, 'manual');

        $this->assertDatabaseHas('workstation_states', [
            'workstation_id' => $this->workstation->id,
            'state' => WorkstationState::CLEANING,
            'source' => 'manual',
        ]);
    }

    public function test_oee_roll_up_treats_maintenance_as_planned_and_waiting_as_loss(): void
    {
        $downtimeService = app(DowntimeService::class);
        $date = Carbon::parse('2026-06-22');
        $line = Line::factory()->create();

        // Maintenance (planned) on one workstation: 40 min.
        $wsMaint = Workstation::factory()->create(['line_id' => $line->id]);
        $this->machine->transition($wsMaint, WorkstationState::MAINTENANCE, [], $date->copy()->setTime(8, 0));
        $this->machine->transition($wsMaint, WorkstationState::RUNNING, [], $date->copy()->setTime(8, 40));

        // Waiting (unplanned loss) on another: 20 min.
        $wsWait = Workstation::factory()->create(['line_id' => $line->id]);
        $this->machine->transition($wsWait, WorkstationState::WAITING, [], $date->copy()->setTime(9, 0));
        $this->machine->transition($wsWait, WorkstationState::RUNNING, [], $date->copy()->setTime(9, 20));

        // Planned bucket has the maintenance time, loss bucket has the waiting time.
        $this->assertSame(40, $downtimeService->getPlannedMinutes($line->id, $date));
        $this->assertSame(20, $downtimeService->getLossMinutes($line->id, $date));
    }
}
