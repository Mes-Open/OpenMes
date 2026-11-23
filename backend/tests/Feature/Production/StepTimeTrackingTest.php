<?php

namespace Tests\Feature\Production;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\BatchStepTimeEntry;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Models\WorkstationState;
use App\Services\Machine\WorkstationStateMachine;
use App\Services\Production\StepTimeService;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Independent operator (labor) and machine time per operation (#400, item 3).
 */
class StepTimeTrackingTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private User $supervisor;

    private WorkOrder $workOrder;

    private Batch $batch;

    private Workstation $machine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->travelTo(Carbon::parse('2026-11-23 08:00:00'));

        $this->workOrder = WorkOrder::factory()->create();
        $this->machine = Workstation::factory()->create(['line_id' => $this->workOrder->line_id, 'name' => 'Lathe 1']);
        $this->batch = app(WorkOrderService::class)->createBatch($this->workOrder, 10);

        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
        $this->operator->lines()->attach($this->workOrder->line_id);

        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('Supervisor');
    }

    private function runningStep(?Batch $batch = null, bool $onMachine = true): BatchStep
    {
        $step = ($batch ?? $this->batch)->steps()->orderBy('step_number')->first();
        $step->update([
            'status' => BatchStep::STATUS_IN_PROGRESS,
            'started_at' => now(),
            'started_by_id' => $this->operator->id,
            'workstation_id' => $onMachine ? $this->machine->id : null,
        ]);

        return $step->fresh();
    }

    private function api(User $user)
    {
        return $this->actingAs($user, 'sanctum');
    }

    public function test_operator_clocks_on_and_off_a_running_step(): void
    {
        $step = $this->runningStep();

        $this->api($this->operator)->postJson("/api/v1/batch-steps/{$step->id}/time-entries/labor/start")
            ->assertCreated()
            ->assertJsonPath('data.kind', 'labor')
            ->assertJsonPath('data.user.id', $this->operator->id)
            ->assertJsonPath('data.ended_at', null);

        $this->travel(25)->minutes();

        $this->api($this->operator)->postJson("/api/v1/batch-steps/{$step->id}/time-entries/labor/stop")
            ->assertOk()
            ->assertJsonPath('data.minutes', 25);

        $this->assertSame(25, $step->fresh()->actual_labor_minutes);
        $this->assertNull($step->fresh()->actual_machine_minutes);
    }

    public function test_clocking_on_twice_is_idempotent(): void
    {
        $step = $this->runningStep();

        $this->api($this->operator)->postJson("/api/v1/batch-steps/{$step->id}/time-entries/labor/start")->assertCreated();
        $this->api($this->operator)->postJson("/api/v1/batch-steps/{$step->id}/time-entries/labor/start")->assertOk();

        $this->assertSame(1, BatchStepTimeEntry::count());
    }

    public function test_clocking_onto_another_step_closes_the_open_one_so_time_is_not_counted_twice(): void
    {
        $first = $this->runningStep();
        $otherOrder = WorkOrder::factory()->create(['line_id' => $this->workOrder->line_id]);
        $second = $this->runningStep(app(WorkOrderService::class)->createBatch($otherOrder, 5), onMachine: false);

        $this->api($this->operator)->postJson("/api/v1/batch-steps/{$first->id}/time-entries/labor/start")->assertCreated();
        $this->travel(10)->minutes();
        $this->api($this->operator)->postJson("/api/v1/batch-steps/{$second->id}/time-entries/labor/start")->assertCreated();
        $this->travel(20)->minutes();
        $this->api($this->operator)->postJson("/api/v1/batch-steps/{$second->id}/time-entries/labor/stop")->assertOk();

        $this->assertSame(10, $first->fresh()->actual_labor_minutes);
        $this->assertSame(20, $second->fresh()->actual_labor_minutes);
        $this->assertSame(0, BatchStepTimeEntry::open()->count());
    }

    public function test_machine_keeps_running_unattended_after_the_operator_clocks_off(): void
    {
        $step = $this->runningStep();

        $this->api($this->operator)->postJson("/api/v1/batch-steps/{$step->id}/time-entries/labor/start")->assertCreated();
        $this->api($this->operator)->postJson("/api/v1/batch-steps/{$step->id}/time-entries/machine/start")
            ->assertCreated()
            ->assertJsonPath('data.workstation.id', $this->machine->id);

        $this->travel(15)->minutes();
        $this->api($this->operator)->postJson("/api/v1/batch-steps/{$step->id}/time-entries/labor/stop")->assertOk();

        $this->travel(45)->minutes();
        $this->api($this->operator)->postJson("/api/v1/batch-steps/{$step->id}/time-entries/machine/stop")->assertOk();

        $step->refresh();
        $this->assertSame(15, $step->actual_labor_minutes);
        $this->assertSame(60, $step->actual_machine_minutes);
    }

    public function test_a_machine_cannot_run_for_two_steps_at_once(): void
    {
        $first = $this->runningStep();
        $otherOrder = WorkOrder::factory()->create(['line_id' => $this->workOrder->line_id]);
        $second = $this->runningStep(app(WorkOrderService::class)->createBatch($otherOrder, 5));

        $this->api($this->operator)->postJson("/api/v1/batch-steps/{$first->id}/time-entries/machine/start")->assertCreated();
        $this->api($this->operator)->postJson("/api/v1/batch-steps/{$second->id}/time-entries/machine/start")
            ->assertStatus(422)
            ->assertJsonPath('errors.time.0', 'Machine Lathe 1 is already running on another step.');
    }

    public function test_machine_time_needs_a_workstation(): void
    {
        $step = $this->runningStep(onMachine: false);

        $this->api($this->operator)->postJson("/api/v1/batch-steps/{$step->id}/time-entries/machine/start")
            ->assertStatus(422)
            ->assertJsonPath('errors.time.0', 'This step has no workstation. Choose the machine that runs it.');

        $this->api($this->operator)->postJson("/api/v1/batch-steps/{$step->id}/time-entries/machine/start", ['workstation_id' => $this->machine->id])
            ->assertCreated();
    }

    public function test_time_is_only_recorded_on_a_step_in_progress(): void
    {
        $step = $this->batch->steps()->orderBy('step_number')->first();

        $this->api($this->operator)->postJson("/api/v1/batch-steps/{$step->id}/time-entries/labor/start")
            ->assertStatus(422)
            ->assertJsonPath('errors.time.0', 'Time can only be recorded on a step in progress.');
        $this->api($this->operator)->postJson("/api/v1/batch-steps/{$step->id}/time-entries/labor/stop")
            ->assertStatus(422);
    }

    public function test_completing_the_step_closes_open_time(): void
    {
        $step = $this->runningStep();
        $this->api($this->operator)->postJson("/api/v1/batch-steps/{$step->id}/time-entries/labor/start");
        $this->api($this->operator)->postJson("/api/v1/batch-steps/{$step->id}/time-entries/machine/start");

        $this->travel(30)->minutes();
        $this->api($this->operator)->postJson("/api/v1/batch-steps/{$step->id}/complete")->assertOk();

        $step->refresh();
        $this->assertSame(BatchStep::STATUS_DONE, $step->status);
        $this->assertSame(0, BatchStepTimeEntry::open()->count());
        $this->assertSame(30, $step->actual_labor_minutes);
        $this->assertSame(30, $step->actual_machine_minutes);
        // The existing time model is untouched.
        $this->assertSame(30, $step->duration_minutes);
        $this->assertNull($step->actual_elapsed_minutes);
    }

    public function test_steps_without_time_entries_keep_their_existing_behaviour(): void
    {
        $step = $this->runningStep();
        $this->travel(5)->minutes();

        $this->api($this->operator)->postJson("/api/v1/batch-steps/{$step->id}/complete")->assertOk();

        $step->refresh();
        $this->assertNull($step->actual_labor_minutes);
        $this->assertNull($step->actual_machine_minutes);
        $this->assertSame(0, BatchStepTimeEntry::count());
    }

    public function test_supervisor_enters_time_afterwards(): void
    {
        $step = $this->runningStep();

        $this->api($this->supervisor)->postJson("/api/v1/batch-steps/{$step->id}/time-entries", [
            'kind' => 'labor',
            'user_id' => $this->operator->id,
            'started_at' => '2026-11-23 06:00:00',
            'ended_at' => '2026-11-23 07:30:00',
            'note' => 'Forgot to clock on',
        ])->assertCreated()->assertJsonPath('data.source', 'manual')->assertJsonPath('data.minutes', 90);

        $this->api($this->supervisor)->postJson("/api/v1/batch-steps/{$step->id}/time-entries", [
            'kind' => 'machine',
            'workstation_id' => $this->machine->id,
            'started_at' => '2026-11-23 06:00:00',
            'ended_at' => '2026-11-23 06:40:00',
        ])->assertCreated();

        $step->refresh();
        $this->assertSame(90, $step->actual_labor_minutes);
        $this->assertSame(40, $step->actual_machine_minutes);
    }

    public function test_time_entered_afterwards_may_not_overlap_recorded_time(): void
    {
        $step = $this->runningStep();
        $payload = ['kind' => 'labor', 'user_id' => $this->operator->id, 'started_at' => '2026-11-23 06:00:00', 'ended_at' => '2026-11-23 07:00:00'];

        $this->api($this->supervisor)->postJson("/api/v1/batch-steps/{$step->id}/time-entries", $payload)->assertCreated();
        $this->api($this->supervisor)->postJson("/api/v1/batch-steps/{$step->id}/time-entries", [
            ...$payload, 'started_at' => '2026-11-23 06:30:00', 'ended_at' => '2026-11-23 07:30:00',
        ])->assertStatus(422)->assertJsonPath('errors.time.0', 'This operator already has time recorded in that period.');

        // Touching end to start is not an overlap.
        $this->api($this->supervisor)->postJson("/api/v1/batch-steps/{$step->id}/time-entries", [
            ...$payload, 'started_at' => '2026-11-23 07:00:00', 'ended_at' => '2026-11-23 07:15:00',
        ])->assertCreated();

        $this->assertSame(75, $step->fresh()->actual_labor_minutes);
    }

    public function test_manual_time_is_validated(): void
    {
        $step = $this->runningStep();

        $this->api($this->supervisor)->postJson("/api/v1/batch-steps/{$step->id}/time-entries", [])
            ->assertStatus(422)->assertJsonValidationErrors(['kind', 'started_at', 'ended_at']);

        $this->api($this->supervisor)->postJson("/api/v1/batch-steps/{$step->id}/time-entries", [
            'kind' => 'labor', 'started_at' => '2026-11-23 07:00:00', 'ended_at' => '2026-11-23 06:00:00',
        ])->assertStatus(422)->assertJsonValidationErrors(['user_id', 'ended_at']);

        $this->api($this->supervisor)->postJson("/api/v1/batch-steps/{$step->id}/time-entries", [
            'kind' => 'machine', 'started_at' => '2026-11-23 06:00:00', 'ended_at' => '2026-11-23 09:00:00',
        ])->assertStatus(422)->assertJsonValidationErrors(['workstation_id', 'ended_at']);

        $this->api($this->supervisor)->postJson("/api/v1/batch-steps/{$step->id}/time-entries", [
            'kind' => 'coffee', 'started_at' => '2026-11-23 06:00:00', 'ended_at' => '2026-11-23 07:00:00',
        ])->assertStatus(422)->assertJsonValidationErrors(['kind']);
    }

    public function test_only_supervisors_enter_or_delete_time(): void
    {
        $step = $this->runningStep();
        $entry = app(StepTimeService::class)->startLabor($step, $this->operator);

        $this->api($this->operator)->postJson("/api/v1/batch-steps/{$step->id}/time-entries", [
            'kind' => 'labor', 'user_id' => $this->operator->id, 'started_at' => '2026-11-23 06:00:00', 'ended_at' => '2026-11-23 07:00:00',
        ])->assertForbidden();
        $this->api($this->operator)->deleteJson("/api/v1/batch-step-time-entries/{$entry->id}")->assertForbidden();
    }

    public function test_guests_are_rejected(): void
    {
        $step = $this->runningStep();

        $this->getJson("/api/v1/batch-steps/{$step->id}/time-entries")->assertUnauthorized();
        $this->postJson("/api/v1/batch-steps/{$step->id}/time-entries/labor/start")->assertUnauthorized();
        $this->getJson("/api/v1/work-orders/{$this->workOrder->id}/operation-times")->assertUnauthorized();
    }

    public function test_operator_of_another_line_cannot_record_time(): void
    {
        $step = $this->runningStep();
        $stranger = User::factory()->create();
        $stranger->assignRole('Operator');

        $this->api($stranger)->postJson("/api/v1/batch-steps/{$step->id}/time-entries/labor/start")->assertForbidden();
        $this->api($stranger)->getJson("/api/v1/batch-steps/{$step->id}/time-entries")->assertForbidden();
    }

    public function test_deleting_an_entry_soft_deletes_it_and_updates_the_totals(): void
    {
        $step = $this->runningStep();
        $service = app(StepTimeService::class);
        $keep = $service->addManual($step, ['kind' => 'labor', 'user_id' => $this->operator->id, 'started_at' => '2026-11-23 05:00:00', 'ended_at' => '2026-11-23 05:20:00'], $this->supervisor);
        $wrong = $service->addManual($step, ['kind' => 'labor', 'user_id' => $this->operator->id, 'started_at' => '2026-11-23 06:00:00', 'ended_at' => '2026-11-23 07:00:00'], $this->supervisor);
        $this->assertSame(80, $step->fresh()->actual_labor_minutes);

        $this->api($this->supervisor)->deleteJson("/api/v1/batch-step-time-entries/{$wrong->id}")->assertOk();

        $this->assertSoftDeleted('batch_step_time_entries', ['id' => $wrong->id, 'deleted_by_id' => $this->supervisor->id]);
        $this->assertNotSoftDeleted('batch_step_time_entries', ['id' => $keep->id]);
        $this->assertSame(20, $step->fresh()->actual_labor_minutes);
    }

    public function test_overlapping_rows_of_one_operator_are_counted_once(): void
    {
        $step = $this->runningStep();
        // Rows written around the service (an import, a race): the totals still merge them.
        foreach ([['06:00', '07:00'], ['06:30', '07:30'], ['09:00', '09:10']] as [$from, $to]) {
            BatchStepTimeEntry::create([
                'batch_step_id' => $step->id, 'kind' => 'labor', 'user_id' => $this->operator->id,
                'started_at' => "2026-11-23 {$from}:00", 'ended_at' => "2026-11-23 {$to}:00", 'source' => 'manual',
            ]);
        }
        // A second operator's time on the same step is their own labor: added, not merged.
        BatchStepTimeEntry::create([
            'batch_step_id' => $step->id, 'kind' => 'labor', 'user_id' => $this->supervisor->id,
            'started_at' => '2026-11-23 06:00:00', 'ended_at' => '2026-11-23 06:30:00', 'source' => 'manual',
        ]);

        app(StepTimeService::class)->recalculate($step);

        $this->assertSame(90 + 10 + 30, $step->fresh()->actual_labor_minutes);
    }

    public function test_index_lists_entries_with_live_totals(): void
    {
        $step = $this->runningStep();
        $this->api($this->operator)->postJson("/api/v1/batch-steps/{$step->id}/time-entries/machine/start");
        $this->travel(12)->minutes();

        $this->api($this->operator)->getJson("/api/v1/batch-steps/{$step->id}/time-entries")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.kind', 'machine')
            ->assertJsonPath('totals.machine_minutes', 12)
            ->assertJsonPath('totals.recorded_machine_minutes', null);
    }

    public function test_machine_running_signal_records_machine_time_on_the_step_in_progress(): void
    {
        $step = $this->runningStep();
        $states = app(WorkstationStateMachine::class);

        $states->transition($this->machine, WorkstationState::RUNNING);
        $this->travel(20)->minutes();
        $states->transition($this->machine, WorkstationState::IDLE);
        $this->travel(5)->minutes();
        $states->transition($this->machine, WorkstationState::RUNNING);
        $this->travel(10)->minutes();
        $states->transition($this->machine, WorkstationState::FAULT);

        $entries = $step->timeEntries()->get();
        $this->assertCount(2, $entries);
        $this->assertTrue($entries->every(fn ($e) => $e->source === 'machine' && $e->ended_at !== null));
        $this->assertSame(30, $step->fresh()->actual_machine_minutes);
    }

    public function test_machine_signal_is_ignored_without_a_single_step_in_progress(): void
    {
        app(WorkstationStateMachine::class)->transition($this->machine, WorkstationState::RUNNING);

        $this->assertSame(0, BatchStepTimeEntry::count());
    }

    public function test_work_order_operation_times_expose_every_time_per_step(): void
    {
        $step = $this->runningStep();
        app(StepTimeService::class)->addManual($step, ['kind' => 'labor', 'user_id' => $this->operator->id, 'started_at' => '2026-11-23 06:00:00', 'ended_at' => '2026-11-23 06:45:00'], $this->supervisor);
        app(StepTimeService::class)->addManual($step, ['kind' => 'machine', 'workstation_id' => $this->machine->id, 'started_at' => '2026-11-23 06:00:00', 'ended_at' => '2026-11-23 07:00:00'], $this->supervisor);

        $response = $this->api($this->operator)->getJson("/api/v1/work-orders/{$this->workOrder->id}/operation-times")->assertOk();

        $row = collect($response->json('data'))->firstWhere('batch_step_id', $step->id);
        $this->assertSame(45, $row['actual_labor_minutes']);
        $this->assertSame(60, $row['actual_machine_minutes']);
        $this->assertSame($this->machine->id, $row['workstation']['id']);
        $this->assertArrayHasKey('actual_setup_minutes', $row);
        $response->assertJsonPath('totals.labor_minutes', 45)->assertJsonPath('totals.machine_minutes', 60);
    }

    public function test_operator_terminal_clocks_on_and_shows_the_time_in_the_panel(): void
    {
        $step = $this->runningStep();
        $session = ['selected_line_id' => $this->workOrder->line_id];

        $this->actingAs($this->operator)->withSession($session)
            ->post("/operator/batch-step/{$step->id}/time/labor/start")
            ->assertRedirect()
            ->assertSessionHas('success', 'Clocked on.');
        $this->actingAs($this->operator)->withSession($session)
            ->post("/operator/batch-step/{$step->id}/time/machine/start")
            ->assertSessionHas('success', 'Machine time started.');

        $this->travel(8)->minutes();

        $panel = $this->actingAs($this->operator)->withSession($session)
            ->getJson("/operator/work-order/{$this->workOrder->id}/panel")
            ->assertOk();
        $time = collect($panel->json('steps'))->firstWhere('id', $step->id)['time'];
        $this->assertSame(['labor_minutes' => 8, 'machine_minutes' => 8, 'clocked_on' => true, 'operators_on' => 1, 'machine_running' => true], $time);

        $this->actingAs($this->operator)->withSession($session)
            ->post("/operator/batch-step/{$step->id}/time/labor/stop")
            ->assertSessionHas('success', 'Clocked off.');
        $this->assertSame(1, BatchStepTimeEntry::open()->count());
    }

    public function test_operator_terminal_refuses_a_step_of_another_line(): void
    {
        $step = $this->runningStep();

        $this->actingAs($this->operator)->withSession(['selected_line_id' => 999999])
            ->post("/operator/batch-step/{$step->id}/time/labor/start")
            ->assertSessionHas('error');
        $this->assertSame(0, BatchStepTimeEntry::count());
    }
}
