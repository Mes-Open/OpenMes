<?php

namespace Tests\Feature\Supervisor;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Line;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Models\WorkstationState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class WorkOrderMachinesTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('Supervisor');
    }

    public function test_supervisor_sees_order_machines_with_current_statuses(): void
    {
        $line = Line::factory()->create(['name' => 'Assembly']);
        $workOrder = WorkOrder::factory()->create(['line_id' => $line->id]);
        $batch = Batch::factory()->create(['work_order_id' => $workOrder->id]);

        $runningStation = Workstation::factory()->create([
            'line_id' => $line->id,
            'code' => 'CUT-01',
            'name' => 'Cutter 01',
        ]);
        $idleStation = Workstation::factory()->create([
            'line_id' => $line->id,
            'code' => 'PACK-01',
            'name' => 'Packing 01',
        ]);

        BatchStep::factory()->create([
            'batch_id' => $batch->id,
            'step_number' => 1,
            'workstation_id' => $runningStation->id,
            'status' => BatchStep::STATUS_IN_PROGRESS,
        ]);
        BatchStep::factory()->create([
            'batch_id' => $batch->id,
            'step_number' => 2,
            'workstation_id' => $runningStation->id,
            'status' => BatchStep::STATUS_READY,
        ]);
        BatchStep::factory()->create([
            'batch_id' => $batch->id,
            'step_number' => 3,
            'workstation_id' => $idleStation->id,
            'status' => BatchStep::STATUS_PENDING,
        ]);

        WorkstationState::create([
            'workstation_id' => $runningStation->id,
            'state' => WorkstationState::RUNNING,
            'started_at' => now()->subMinutes(12),
            'ended_at' => null,
            'source' => 'machine',
        ]);
        WorkstationState::create([
            'workstation_id' => $idleStation->id,
            'state' => WorkstationState::IDLE,
            'started_at' => now()->subMinutes(5),
            'ended_at' => null,
            'source' => 'manual',
        ]);

        $this->actingAs($this->supervisor)
            ->get(route('supervisor.work-orders.show', $workOrder))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('supervisor/work-orders/Show')
                ->has('workOrder.machines', 2)
                ->where('workOrder.machines.0.code', 'CUT-01')
                ->where('workOrder.machines.0.current_state', WorkstationState::RUNNING)
                ->where('workOrder.machines.0.steps_count', 2)
                ->where('workOrder.machines.0.active_steps_count', 2)
                ->where('workOrder.machines.1.code', 'PACK-01')
                ->where('workOrder.machines.1.current_state', WorkstationState::IDLE)
                ->where('workOrder.machines.1.steps_count', 1)
                ->where('workOrder.machines.1.active_steps_count', 0)
            );
    }

    public function test_supervisor_sees_empty_machine_list_when_order_has_no_assigned_machines(): void
    {
        $workOrder = WorkOrder::factory()->create();
        $batch = Batch::factory()->create(['work_order_id' => $workOrder->id]);

        BatchStep::factory()->create([
            'batch_id' => $batch->id,
            'step_number' => 1,
            'workstation_id' => null,
        ]);

        $this->actingAs($this->supervisor)
            ->get(route('supervisor.work-orders.show', $workOrder))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('supervisor/work-orders/Show')
                ->has('workOrder.machines', 0)
            );
    }

    public function test_supervisor_sees_machine_assigned_to_batch_even_without_step_assignment(): void
    {
        $line = Line::factory()->create();
        $workOrder = WorkOrder::factory()->create(['line_id' => $line->id]);
        $workstation = Workstation::factory()->create([
            'line_id' => $line->id,
            'code' => 'CELL-01',
            'name' => 'Cell 01',
        ]);

        Batch::factory()->create([
            'work_order_id' => $workOrder->id,
            'workstation_id' => $workstation->id,
        ]);

        WorkstationState::create([
            'workstation_id' => $workstation->id,
            'state' => WorkstationState::SETUP,
            'started_at' => now()->subMinute(),
            'ended_at' => null,
            'source' => 'manual',
        ]);

        $this->actingAs($this->supervisor)
            ->get(route('supervisor.work-orders.show', $workOrder))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('supervisor/work-orders/Show')
                ->has('workOrder.machines', 1)
                ->where('workOrder.machines.0.code', 'CELL-01')
                ->where('workOrder.machines.0.current_state', WorkstationState::SETUP)
                ->where('workOrder.machines.0.steps_count', 0)
                ->where('workOrder.machines.0.active_steps_count', 0)
            );
    }
}
