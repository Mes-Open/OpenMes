<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Line;
use App\Models\ProductType;
use App\Models\ProcessTemplate;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    protected function authenticatedUser($role = 'Admin')
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    public function test_admin_can_create_work_order(): void
    {
        $user = $this->authenticatedUser('Admin');
        $line = Line::factory()->create();
        $productType = ProductType::factory()->create();
        $processTemplate = ProcessTemplate::factory()
            ->withSteps(3)
            ->create(['product_type_id' => $productType->id]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/work-orders', [
                'order_no' => 'WO-TEST-001',
                'line_id' => $line->id,
                'product_type_id' => $productType->id,
                'planned_qty' => 100,
                'priority' => 1,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'order_no',
                    'process_snapshot',
                    'status',
                ],
            ]);

        $this->assertDatabaseHas('work_orders', [
            'order_no' => 'WO-TEST-001',
            'planned_qty' => 100,
            'status' => WorkOrder::STATUS_PENDING,
        ]);
    }

    public function test_work_order_creation_generates_process_snapshot(): void
    {
        $user = $this->authenticatedUser('Admin');
        $line = Line::factory()->create();
        $productType = ProductType::factory()->create();
        $processTemplate = ProcessTemplate::factory()
            ->withSteps(3)
            ->create(['product_type_id' => $productType->id]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/work-orders', [
                'order_no' => 'WO-TEST-002',
                'line_id' => $line->id,
                'product_type_id' => $productType->id,
                'planned_qty' => 100,
            ]);

        $workOrder = WorkOrder::where('order_no', 'WO-TEST-002')->first();

        $this->assertNotNull($workOrder->process_snapshot);
        $this->assertIsArray($workOrder->process_snapshot);
        $this->assertArrayHasKey('steps', $workOrder->process_snapshot);
        $this->assertCount(3, $workOrder->process_snapshot['steps']);
    }

    public function test_operator_can_only_see_work_orders_for_assigned_lines(): void
    {
        $operator = $this->authenticatedUser('Operator');
        $line1 = Line::factory()->create();
        $line2 = Line::factory()->create();

        // Assign operator to line1 only
        $operator->lines()->attach($line1->id);

        $workOrder1 = WorkOrder::factory()->create(['line_id' => $line1->id]);
        $workOrder2 = WorkOrder::factory()->create(['line_id' => $line2->id]);

        $token = $operator->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/work-orders');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($workOrder1->id, $data[0]['id']);
    }

    public function test_admin_can_see_all_work_orders(): void
    {
        $admin = $this->authenticatedUser('Admin');
        $line1 = Line::factory()->create();
        $line2 = Line::factory()->create();

        $workOrder1 = WorkOrder::factory()->create(['line_id' => $line1->id]);
        $workOrder2 = WorkOrder::factory()->create(['line_id' => $line2->id]);

        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/work-orders');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(2, $data);
    }

    public function test_work_orders_can_be_filtered_by_status(): void
    {
        $user = $this->authenticatedUser('Admin');
        $line = Line::factory()->create();

        WorkOrder::factory()->create(['line_id' => $line->id, 'status' => WorkOrder::STATUS_PENDING]);
        WorkOrder::factory()->inProgress()->create(['line_id' => $line->id]);
        WorkOrder::factory()->done()->create(['line_id' => $line->id]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/work-orders?status=PENDING');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals(WorkOrder::STATUS_PENDING, $data[0]['status']);
    }

    public function test_admin_can_update_work_order(): void
    {
        $user = $this->authenticatedUser('Admin');
        $workOrder = WorkOrder::factory()->create([
            'planned_qty' => 100,
            'priority' => 1,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->patchJson("/api/v1/work-orders/{$workOrder->id}", [
                'planned_qty' => 150,
                'priority' => 5,
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('work_orders', [
            'id' => $workOrder->id,
            'planned_qty' => 150,
            'priority' => 5,
        ]);
    }

    public function test_cannot_update_completed_work_order(): void
    {
        $user = $this->authenticatedUser('Admin');
        $workOrder = WorkOrder::factory()->done()->create();

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->patchJson("/api/v1/work-orders/{$workOrder->id}", [
                'planned_qty' => 150,
            ]);

        $response->assertStatus(500); // Exception thrown
    }

    public function test_admin_can_delete_pending_work_order(): void
    {
        $user = $this->authenticatedUser('Admin');
        $workOrder = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_PENDING]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->deleteJson("/api/v1/work-orders/{$workOrder->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('work_orders', [
            'id' => $workOrder->id,
        ]);
    }

    public function test_cannot_delete_non_pending_work_order(): void
    {
        $user = $this->authenticatedUser('Admin');
        $workOrder = WorkOrder::factory()->inProgress()->create();

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->deleteJson("/api/v1/work-orders/{$workOrder->id}");

        $response->assertStatus(422);

        $this->assertDatabaseHas('work_orders', [
            'id' => $workOrder->id,
        ]);
    }

    public function test_operator_cannot_create_work_order(): void
    {
        $user = $this->authenticatedUser('Operator');
        $line = Line::factory()->create();
        $productType = ProductType::factory()->create();

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/work-orders', [
                'order_no' => 'WO-TEST-003',
                'line_id' => $line->id,
                'product_type_id' => $productType->id,
                'planned_qty' => 100,
            ]);

        $response->assertStatus(403); // Forbidden
    }

    public function test_work_order_requires_unique_order_no(): void
    {
        $user = $this->authenticatedUser('Admin');
        $line = Line::factory()->create();
        $productType = ProductType::factory()->create();
        ProcessTemplate::factory()->withSteps(3)->create(['product_type_id' => $productType->id]);

        WorkOrder::factory()->create(['order_no' => 'WO-DUPLICATE']);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/work-orders', [
                'order_no' => 'WO-DUPLICATE',
                'line_id' => $line->id,
                'product_type_id' => $productType->id,
                'planned_qty' => 100,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['order_no']);
    }

    public function test_work_order_validation_requires_required_fields(): void
    {
        $user = $this->authenticatedUser('Admin');
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/work-orders', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'order_no',
                'line_id',
                'product_type_id',
                'planned_qty',
            ]);
    }
}
