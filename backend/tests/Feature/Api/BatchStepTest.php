<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Batch;
use App\Models\BatchStep;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchStepTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    protected function authenticatedUser($role = 'Operator')
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    protected function createWorkOrderWithBatch()
    {
        $workOrder = WorkOrder::factory()->create();
        $workOrderService = app(WorkOrderService::class);
        $batch = $workOrderService->createBatch($workOrder, 50);

        return [$workOrder, $batch];
    }

    public function test_operator_can_start_first_step(): void
    {
        $user = $this->authenticatedUser('Operator');
        [$workOrder, $batch] = $this->createWorkOrderWithBatch();

        // Assign user to line
        $user->lines()->attach($workOrder->line_id);

        $firstStep = $batch->steps()->orderBy('step_number')->first();
        $this->assertEquals(BatchStep::STATUS_PENDING, $firstStep->status);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/v1/batch-steps/{$firstStep->id}/start");

        $response->assertStatus(200);

        $firstStep->refresh();
        $this->assertEquals(BatchStep::STATUS_IN_PROGRESS, $firstStep->status);
        $this->assertNotNull($firstStep->started_at);
        $this->assertEquals($user->id, $firstStep->started_by_id);
    }

    public function test_cannot_start_step_if_previous_step_not_complete(): void
    {
        $user = $this->authenticatedUser('Operator');
        [$workOrder, $batch] = $this->createWorkOrderWithBatch();

        $user->lines()->attach($workOrder->line_id);

        $secondStep = $batch->steps()->where('step_number', 2)->first();

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/v1/batch-steps/{$secondStep->id}/start");

        $response->assertStatus(422)
            ->assertJsonFragment(['must be completed before']);
    }

    public function test_can_start_step_after_previous_step_completed(): void
    {
        $user = $this->authenticatedUser('Operator');
        [$workOrder, $batch] = $this->createWorkOrderWithBatch();

        $user->lines()->attach($workOrder->line_id);

        // Start and complete first step
        $firstStep = $batch->steps()->orderBy('step_number')->first();
        $firstStep->update([
            'status' => BatchStep::STATUS_DONE,
            'started_at' => now()->subMinutes(30),
            'completed_at' => now(),
        ]);

        // Now try to start second step
        $secondStep = $batch->steps()->where('step_number', 2)->first();

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/v1/batch-steps/{$secondStep->id}/start");

        $response->assertStatus(200);

        $secondStep->refresh();
        $this->assertEquals(BatchStep::STATUS_IN_PROGRESS, $secondStep->status);
    }

    public function test_operator_can_complete_in_progress_step(): void
    {
        $user = $this->authenticatedUser('Operator');
        [$workOrder, $batch] = $this->createWorkOrderWithBatch();

        $user->lines()->attach($workOrder->line_id);

        $firstStep = $batch->steps()->orderBy('step_number')->first();
        $firstStep->update([
            'status' => BatchStep::STATUS_IN_PROGRESS,
            'started_at' => now()->subMinutes(30),
            'started_by_id' => $user->id,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/v1/batch-steps/{$firstStep->id}/complete");

        $response->assertStatus(200);

        $firstStep->refresh();
        $this->assertEquals(BatchStep::STATUS_DONE, $firstStep->status);
        $this->assertNotNull($firstStep->completed_at);
        $this->assertEquals($user->id, $firstStep->completed_by_id);
        $this->assertNotNull($firstStep->duration_minutes);
    }

    public function test_cannot_complete_step_that_is_not_in_progress(): void
    {
        $user = $this->authenticatedUser('Operator');
        [$workOrder, $batch] = $this->createWorkOrderWithBatch();

        $user->lines()->attach($workOrder->line_id);

        $firstStep = $batch->steps()->orderBy('step_number')->first();
        $this->assertEquals(BatchStep::STATUS_PENDING, $firstStep->status);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/v1/batch-steps/{$firstStep->id}/complete");

        $response->assertStatus(422);
    }

    public function test_completing_all_steps_marks_batch_as_done(): void
    {
        $user = $this->authenticatedUser('Operator');
        [$workOrder, $batch] = $this->createWorkOrderWithBatch();

        $user->lines()->attach($workOrder->line_id);

        $token = $user->createToken('test')->plainTextToken;

        // Mark all steps except last as done
        $steps = $batch->steps()->orderBy('step_number')->get();
        foreach ($steps as $index => $step) {
            if ($index < $steps->count() - 1) {
                $step->update([
                    'status' => BatchStep::STATUS_DONE,
                    'started_at' => now()->subHour(),
                    'completed_at' => now()->subMinutes(30),
                ]);
            }
        }

        // Start and complete last step
        $lastStep = $steps->last();
        $lastStep->update([
            'status' => BatchStep::STATUS_IN_PROGRESS,
            'started_at' => now()->subMinutes(10),
        ]);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/v1/batch-steps/{$lastStep->id}/complete", [
                'produced_qty' => 50,
            ]);

        $response->assertStatus(200);

        $batch->refresh();
        $this->assertEquals(Batch::STATUS_DONE, $batch->status);
        $this->assertNotNull($batch->completed_at);
    }

    public function test_completing_batch_updates_work_order_produced_qty(): void
    {
        $user = $this->authenticatedUser('Operator');
        [$workOrder, $batch] = $this->createWorkOrderWithBatch();

        $user->lines()->attach($workOrder->line_id);

        // Complete all steps
        $steps = $batch->steps()->orderBy('step_number')->get();
        foreach ($steps as $index => $step) {
            if ($index < $steps->count() - 1) {
                $step->update([
                    'status' => BatchStep::STATUS_DONE,
                    'started_at' => now()->subHour(),
                    'completed_at' => now()->subMinutes(30),
                ]);
            }
        }

        // Complete last step
        $lastStep = $steps->last();
        $lastStep->update([
            'status' => BatchStep::STATUS_IN_PROGRESS,
            'started_at' => now()->subMinutes(10),
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $this->postJson("/api/v1/batch-steps/{$lastStep->id}/complete", [
            'produced_qty' => 50,
        ], ['Authorization' => "Bearer $token"]);

        $workOrder->refresh();
        $this->assertEquals(50, $workOrder->produced_qty);
    }

    public function test_step_status_updates_cascade_to_batch_and_work_order(): void
    {
        $user = $this->authenticatedUser('Operator');
        [$workOrder, $batch] = $this->createWorkOrderWithBatch();

        $user->lines()->attach($workOrder->line_id);

        $this->assertEquals(WorkOrder::STATUS_PENDING, $workOrder->status);
        $this->assertEquals(Batch::STATUS_PENDING, $batch->status);

        $firstStep = $batch->steps()->orderBy('step_number')->first();

        $token = $user->createToken('test')->plainTextToken;

        // Start first step
        $this->postJson("/api/v1/batch-steps/{$firstStep->id}/start", [], [
            'Authorization' => "Bearer $token"
        ]);

        $batch->refresh();
        $workOrder->refresh();

        $this->assertEquals(Batch::STATUS_IN_PROGRESS, $batch->status);
        $this->assertEquals(WorkOrder::STATUS_IN_PROGRESS, $workOrder->status);
    }

    public function test_duration_is_calculated_automatically(): void
    {
        $user = $this->authenticatedUser('Operator');
        [$workOrder, $batch] = $this->createWorkOrderWithBatch();

        $user->lines()->attach($workOrder->line_id);

        $firstStep = $batch->steps()->orderBy('step_number')->first();

        // Start step 30 minutes ago
        $startedAt = now()->subMinutes(30);
        $firstStep->update([
            'status' => BatchStep::STATUS_IN_PROGRESS,
            'started_at' => $startedAt,
            'started_by_id' => $user->id,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $this->postJson("/api/v1/batch-steps/{$firstStep->id}/complete", [], [
            'Authorization' => "Bearer $token"
        ]);

        $firstStep->refresh();
        $this->assertGreaterThanOrEqual(29, $firstStep->duration_minutes);
        $this->assertLessThanOrEqual(31, $firstStep->duration_minutes);
    }

    public function test_operator_from_different_line_cannot_access_step(): void
    {
        $user = $this->authenticatedUser('Operator');
        [$workOrder, $batch] = $this->createWorkOrderWithBatch();

        // Do NOT assign user to this work order's line

        $firstStep = $batch->steps()->orderBy('step_number')->first();

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/v1/batch-steps/{$firstStep->id}/start");

        $response->assertStatus(403); // Forbidden
    }
}
