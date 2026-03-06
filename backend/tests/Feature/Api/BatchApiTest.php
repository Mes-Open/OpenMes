<?php

namespace Tests\Feature\Api;

use App\Models\Batch;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $operator;
    protected WorkOrder $workOrder;
    protected string $adminToken;
    protected string $operatorToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;

        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
        $this->operatorToken = $this->operator->createToken('test')->plainTextToken;

        $this->workOrder = WorkOrder::factory()->create();
        $this->operator->lines()->attach($this->workOrder->line_id);
    }

    // ── GET /api/v1/work-orders/{workOrder}/batches ───────────────────────────

    public function test_can_list_batches_for_work_order(): void
    {
        $service = app(WorkOrderService::class);
        $service->createBatch($this->workOrder, 25);
        $service->createBatch($this->workOrder, 25);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson("/api/v1/work-orders/{$this->workOrder->id}/batches");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'batch_number', 'target_qty', 'produced_qty', 'status'],
                ],
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_batches_are_returned_in_batch_number_order(): void
    {
        $service = app(WorkOrderService::class);
        $service->createBatch($this->workOrder, 10);
        $service->createBatch($this->workOrder, 20);
        $service->createBatch($this->workOrder, 30);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson("/api/v1/work-orders/{$this->workOrder->id}/batches");

        $numbers = array_column($response->json('data'), 'batch_number');
        $this->assertEquals([1, 2, 3], $numbers);
    }

    public function test_empty_batches_returns_empty_array(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson("/api/v1/work-orders/{$this->workOrder->id}/batches");

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    // ── POST /api/v1/work-orders/{workOrder}/batches ──────────────────────────

    public function test_admin_can_create_batch(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/work-orders/{$this->workOrder->id}/batches", [
                'target_qty' => 50,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'batch_number', 'target_qty', 'status'],
            ]);

        $this->assertDatabaseHas('batches', [
            'work_order_id' => $this->workOrder->id,
            'target_qty'    => 50,
            'status'        => Batch::STATUS_PENDING,
        ]);
    }

    public function test_batch_target_qty_required(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/work-orders/{$this->workOrder->id}/batches", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['target_qty']);
    }

    public function test_batch_is_created_with_steps_from_work_order_snapshot(): void
    {
        $snapshot = $this->workOrder->process_snapshot;
        $stepCount = count($snapshot['steps'] ?? []);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/work-orders/{$this->workOrder->id}/batches", [
                'target_qty' => 25,
            ]);

        $batchId = $response->json('data.id');
        $batch = Batch::find($batchId);

        $this->assertCount($stepCount, $batch->steps);
    }

    public function test_batch_numbers_auto_increment(): void
    {
        $service = app(WorkOrderService::class);
        $b1 = $service->createBatch($this->workOrder, 10);
        $b2 = $service->createBatch($this->workOrder, 10);

        $this->assertEquals(1, $b1->batch_number);
        $this->assertEquals(2, $b2->batch_number);
    }

    public function test_cannot_create_batch_for_nonexistent_work_order(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/work-orders/999999/batches', [
                'target_qty' => 10,
            ]);

        $response->assertStatus(404);
    }

    // ── GET /api/v1/batches/{batch} ──────────────────────────────────────────

    public function test_can_get_single_batch(): void
    {
        $service = app(WorkOrderService::class);
        $batch = $service->createBatch($this->workOrder, 30);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson("/api/v1/batches/{$batch->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['id', 'batch_number', 'target_qty', 'produced_qty', 'status', 'steps'],
            ]);

        $this->assertEquals($batch->id, $response->json('data.id'));
    }

    public function test_get_nonexistent_batch_returns_404(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/batches/999999');

        $response->assertStatus(404);
    }

    public function test_batch_show_includes_steps(): void
    {
        $service = app(WorkOrderService::class);
        $batch = $service->createBatch($this->workOrder, 20);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson("/api/v1/batches/{$batch->id}");

        $steps = $response->json('data.steps');
        $this->assertIsArray($steps);
        $this->assertNotEmpty($steps);
    }
}
