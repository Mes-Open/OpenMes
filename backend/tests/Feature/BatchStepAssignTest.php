<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Line;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Models\WorkstationType;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pool dispatch (#52): a supervisor assigns a specific workstation to a pending
 * batch step that carries only an Equipment Class (workstation_type_id).
 */
class BatchStepAssignTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    private WorkstationType $typeA;

    private WorkstationType $typeB;

    private Workstation $stationA;

    private Workstation $stationB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->line = Line::factory()->create();
        $this->typeA = WorkstationType::factory()->create();
        $this->typeB = WorkstationType::factory()->create();
        $this->stationA = Workstation::factory()->create(['line_id' => $this->line->id, 'workstation_type_id' => $this->typeA->id, 'is_active' => true]);
        $this->stationB = Workstation::factory()->create(['line_id' => $this->line->id, 'workstation_type_id' => $this->typeB->id, 'is_active' => true]);
    }

    private function user(string $role): User
    {
        $u = User::factory()->create();
        $u->assignRole($role);

        return $u;
    }

    private function makeStep(int $workstationTypeId, string $status = BatchStep::STATUS_PENDING): BatchStep
    {
        $wo = WorkOrder::factory()->create(['line_id' => $this->line->id, 'status' => WorkOrder::STATUS_IN_PROGRESS]);
        $batch = Batch::create([
            'work_order_id' => $wo->id, 'batch_number' => 1, 'target_qty' => 100,
            'produced_qty' => 0, 'status' => Batch::STATUS_IN_PROGRESS, 'started_at' => now(),
        ]);

        return BatchStep::factory()->create([
            'batch_id' => $batch->id, 'step_number' => 1,
            'workstation_type_id' => $workstationTypeId, 'workstation_id' => null, 'status' => $status,
        ]);
    }

    public function test_guest_cannot_assign(): void
    {
        $step = $this->makeStep($this->typeA->id);

        $this->postJson("/api/v1/batch-steps/{$step->id}/assign", ['workstation_id' => $this->stationA->id])
            ->assertUnauthorized();

        $this->assertNull($step->fresh()->workstation_id);
    }

    public function test_operator_cannot_assign(): void
    {
        $step = $this->makeStep($this->typeA->id);

        $this->actingAs($this->user('Operator'))
            ->postJson("/api/v1/batch-steps/{$step->id}/assign", ['workstation_id' => $this->stationA->id])
            ->assertForbidden();
    }

    public function test_missing_workstation_id_is_rejected(): void
    {
        $step = $this->makeStep($this->typeA->id);

        $this->actingAs($this->user('Supervisor'))
            ->postJson("/api/v1/batch-steps/{$step->id}/assign", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('workstation_id');
    }

    public function test_admin_assigns_a_matching_type_workstation(): void
    {
        $step = $this->makeStep($this->typeA->id);
        $admin = $this->user('Admin');

        $this->actingAs($admin)
            ->postJson("/api/v1/batch-steps/{$step->id}/assign", ['workstation_id' => $this->stationA->id])
            ->assertOk();

        $step->refresh();
        $this->assertSame($this->stationA->id, $step->workstation_id);
        $this->assertSame($admin->id, $step->assigned_by_id);
    }

    public function test_a_ready_step_can_be_assigned(): void
    {
        $step = $this->makeStep($this->typeA->id, BatchStep::STATUS_READY);

        $this->actingAs($this->user('Supervisor'))
            ->postJson("/api/v1/batch-steps/{$step->id}/assign", ['workstation_id' => $this->stationA->id])
            ->assertOk();

        $this->assertSame($this->stationA->id, $step->fresh()->workstation_id);
    }

    public function test_inactive_workstation_is_rejected(): void
    {
        $step = $this->makeStep($this->typeA->id);
        $inactive = Workstation::factory()->create([
            'line_id' => $this->line->id,
            'workstation_type_id' => $this->typeA->id,
            'is_active' => false,
        ]);

        $this->actingAs($this->user('Supervisor'))
            ->postJson("/api/v1/batch-steps/{$step->id}/assign", ['workstation_id' => $inactive->id])
            ->assertStatus(422);

        $this->assertNull($step->fresh()->workstation_id);
    }

    public function test_supervisor_assigns_a_matching_type_workstation(): void
    {
        $step = $this->makeStep($this->typeA->id);
        $supervisor = $this->user('Supervisor');

        $this->actingAs($supervisor)
            ->postJson("/api/v1/batch-steps/{$step->id}/assign", ['workstation_id' => $this->stationA->id])
            ->assertOk();

        $step->refresh();
        $this->assertSame($this->stationA->id, $step->workstation_id);
        $this->assertSame($supervisor->id, $step->assigned_by_id);
        $this->assertNotNull($step->assigned_at);
    }

    public function test_wrong_type_workstation_is_rejected(): void
    {
        $step = $this->makeStep($this->typeA->id);

        $this->actingAs($this->user('Supervisor'))
            ->postJson("/api/v1/batch-steps/{$step->id}/assign", ['workstation_id' => $this->stationB->id])
            ->assertStatus(422);

        $this->assertNull($step->fresh()->workstation_id);
    }

    public function test_a_non_pending_step_cannot_be_assigned(): void
    {
        $step = $this->makeStep($this->typeA->id, BatchStep::STATUS_IN_PROGRESS);

        $this->actingAs($this->user('Supervisor'))
            ->postJson("/api/v1/batch-steps/{$step->id}/assign", ['workstation_id' => $this->stationA->id])
            ->assertStatus(422);
    }
}
