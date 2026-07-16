<?php

namespace Tests\Feature\Api;

use App\Models\Batch;
use App\Models\QualityControlTask;
use App\Models\QualityControlTrigger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QualityControlTaskTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->user = User::factory()->create();
        $this->user->assignRole('Operator');
        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('Supervisor');
    }

    public function test_authenticated_user_can_list_open_quality_tasks(): void
    {
        QualityControlTask::factory()->create(['status' => QualityControlTask::STATUS_DUE]);
        QualityControlTask::factory()->create(['status' => QualityControlTask::STATUS_DONE]); // excluded

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/quality-control-tasks')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'status', 'trigger_name', 'is_blocking', 'due_reason', 'quality_control_trigger_id', 'parameters', 'samples_per_check']],
                'meta' => ['roaming_triggers', 'active_batches', 'pallets'],
            ])
            ->assertJsonCount(1, 'data');
    }

    public function test_guest_cannot_access(): void
    {
        $this->getJson('/api/v1/quality-control-tasks')->assertStatus(401);
    }

    public function test_can_skip_open_task(): void
    {
        $task = QualityControlTask::factory()->create(['status' => QualityControlTask::STATUS_DUE]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/quality-control-tasks/{$task->id}/skip")
            ->assertStatus(200);

        $this->assertEquals(QualityControlTask::STATUS_SKIPPED, $task->fresh()->status);
    }

    public function test_supervisor_can_perform_a_control(): void
    {
        $batch = Batch::factory()->inProgress()->create();
        $task = QualityControlTask::factory()->create([
            'status' => QualityControlTask::STATUS_DUE,
            'batch_id' => $batch->id,
            'work_order_id' => $batch->work_order_id,
        ]);

        $this->actingAs($this->supervisor, 'sanctum')
            ->postJson("/api/v1/quality-control-tasks/{$task->id}/perform", [
                'samples' => [
                    ['sample_number' => 1, 'parameter_name' => 'Result', 'parameter_type' => 'pass_fail', 'is_passed' => true],
                ],
                'notes' => 'Looks good',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', QualityControlTask::STATUS_DONE)
            ->assertJsonPath('data.all_passed', true);

        $this->assertEquals(QualityControlTask::STATUS_DONE, $task->fresh()->status);
    }

    public function test_operator_cannot_perform_a_control(): void
    {
        $batch = Batch::factory()->inProgress()->create();
        $task = QualityControlTask::factory()->create([
            'status' => QualityControlTask::STATUS_DUE,
            'batch_id' => $batch->id,
            'work_order_id' => $batch->work_order_id,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/quality-control-tasks/{$task->id}/perform", [
                'samples' => [
                    ['sample_number' => 1, 'parameter_name' => 'Result', 'parameter_type' => 'pass_fail', 'is_passed' => true],
                ],
            ])
            ->assertStatus(403);
    }

    public function test_perform_requires_samples(): void
    {
        $batch = Batch::factory()->inProgress()->create();
        $task = QualityControlTask::factory()->create([
            'status' => QualityControlTask::STATUS_DUE,
            'batch_id' => $batch->id,
            'work_order_id' => $batch->work_order_id,
        ]);

        $this->actingAs($this->supervisor, 'sanctum')
            ->postJson("/api/v1/quality-control-tasks/{$task->id}/perform", ['notes' => 'no samples'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('samples');
    }

    public function test_supervisor_can_raise_roaming_control(): void
    {
        $trigger = QualityControlTrigger::factory()->create([
            'trigger_type' => QualityControlTrigger::TYPE_ROAMING,
            'is_active' => true,
        ]);
        $batch = Batch::factory()->inProgress()->create();

        $this->actingAs($this->supervisor, 'sanctum')
            ->postJson('/api/v1/quality-control-tasks', [
                'quality_control_trigger_id' => $trigger->id,
                'batch_id' => $batch->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', QualityControlTask::STATUS_DUE);

        $this->assertDatabaseHas('quality_control_tasks', [
            'quality_control_trigger_id' => $trigger->id,
            'batch_id' => $batch->id,
        ]);
    }

    public function test_operator_cannot_raise_roaming_control(): void
    {
        $trigger = QualityControlTrigger::factory()->create([
            'trigger_type' => QualityControlTrigger::TYPE_ROAMING,
            'is_active' => true,
        ]);
        $batch = Batch::factory()->inProgress()->create();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/quality-control-tasks', [
                'quality_control_trigger_id' => $trigger->id,
                'batch_id' => $batch->id,
            ])
            ->assertStatus(403);
    }

    public function test_roaming_rejects_non_roaming_trigger(): void
    {
        $trigger = QualityControlTrigger::factory()->create([
            'trigger_type' => QualityControlTrigger::TYPE_IN_PRODUCTION,
            'is_active' => true,
        ]);
        $batch = Batch::factory()->inProgress()->create();

        $this->actingAs($this->supervisor, 'sanctum')
            ->postJson('/api/v1/quality-control-tasks', [
                'quality_control_trigger_id' => $trigger->id,
                'batch_id' => $batch->id,
            ])
            ->assertStatus(422);
    }

    public function test_roaming_requires_trigger(): void
    {
        $this->actingAs($this->supervisor, 'sanctum')
            ->postJson('/api/v1/quality-control-tasks', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('quality_control_trigger_id');
    }
}
