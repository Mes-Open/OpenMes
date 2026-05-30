<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Line;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Services\WorkOrder\BatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkstationRoutingTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    private Workstation $stationA;

    private Workstation $stationB;

    private User $operatorA;   // bound to station A

    private User $operatorB;   // bound to station B

    private User $admin;

    private User $lineOperator; // no workstation assigned

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Supervisor', 'guard_name' => 'web']);
        Role::create(['name' => 'Operator', 'guard_name' => 'web']);

        $this->line = Line::factory()->create();
        $this->stationA = Workstation::factory()->create(['line_id' => $this->line->id, 'name' => 'Station A']);
        $this->stationB = Workstation::factory()->create(['line_id' => $this->line->id, 'name' => 'Station B']);

        $this->operatorA = User::factory()->create([
            'account_type' => 'workstation',
            'workstation_id' => $this->stationA->id,
        ]);
        $this->operatorA->assignRole('Operator');

        $this->operatorB = User::factory()->create([
            'account_type' => 'workstation',
            'workstation_id' => $this->stationB->id,
        ]);
        $this->operatorB->assignRole('Operator');

        $this->lineOperator = User::factory()->create(['account_type' => 'operator', 'workstation_id' => null]);
        $this->lineOperator->assignRole('Operator');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    private function setRouting(bool $enabled): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'workstation_routing_enabled'],
            ['value' => json_encode($enabled)]
        );
        // Sequential enforcement off so single steps can start independently
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'force_sequential_steps'],
            ['value' => json_encode(false)]
        );
        config(['openmmes.force_sequential_steps' => false]);
    }

    private function makeStep(int $workstationId, string $status = BatchStep::STATUS_PENDING): BatchStep
    {
        $wo = WorkOrder::factory()->create([
            'line_id' => $this->line->id,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
        ]);
        $batch = Batch::create([
            'work_order_id' => $wo->id,
            'batch_number' => 1,
            'target_qty' => 100,
            'produced_qty' => 0,
            'status' => Batch::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);

        return BatchStep::factory()->create([
            'batch_id' => $batch->id,
            'step_number' => 1,
            'workstation_id' => $workstationId,
            'status' => $status,
            'started_at' => $status === BatchStep::STATUS_IN_PROGRESS ? now() : null,
        ]);
    }

    public function test_operator_can_start_step_at_own_workstation(): void
    {
        $this->setRouting(true);
        $step = $this->makeStep($this->stationA->id);

        $result = app(BatchService::class)->startStep($step, $this->operatorA);

        $this->assertEquals(BatchStep::STATUS_IN_PROGRESS, $result->status);
    }

    public function test_operator_cannot_start_step_at_other_workstation(): void
    {
        $this->setRouting(true);
        $step = $this->makeStep($this->stationA->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Station A');

        app(BatchService::class)->startStep($step, $this->operatorB);
    }

    public function test_operator_cannot_complete_step_at_other_workstation(): void
    {
        $this->setRouting(true);
        $step = $this->makeStep($this->stationA->id, BatchStep::STATUS_IN_PROGRESS);

        $this->expectException(\Exception::class);

        app(BatchService::class)->completeStep($step, $this->operatorB);
    }

    public function test_admin_bypasses_routing(): void
    {
        $this->setRouting(true);
        $step = $this->makeStep($this->stationA->id);

        $result = app(BatchService::class)->startStep($step, $this->admin);

        $this->assertEquals(BatchStep::STATUS_IN_PROGRESS, $result->status);
    }

    public function test_line_operator_without_workstation_is_not_restricted(): void
    {
        $this->setRouting(true);
        $step = $this->makeStep($this->stationA->id);

        $result = app(BatchService::class)->startStep($step, $this->lineOperator);

        $this->assertEquals(BatchStep::STATUS_IN_PROGRESS, $result->status);
    }

    public function test_routing_disabled_allows_any_workstation(): void
    {
        $this->setRouting(false);
        $step = $this->makeStep($this->stationA->id);

        // Operator B (station B) may operate station A's step when routing is off
        $result = app(BatchService::class)->startStep($step, $this->operatorB);

        $this->assertEquals(BatchStep::STATUS_IN_PROGRESS, $result->status);
    }

    public function test_step_without_workstation_is_open_to_anyone(): void
    {
        $this->setRouting(true);
        $step = $this->makeStep($this->stationA->id);
        $step->update(['workstation_id' => null]);

        $result = app(BatchService::class)->startStep($step->fresh(), $this->operatorB);

        $this->assertEquals(BatchStep::STATUS_IN_PROGRESS, $result->status);
    }
}
