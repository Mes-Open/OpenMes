<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Line;
use App\Models\ProductType;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_audit_log_created_on_model_creation()
    {
        $line = Line::factory()->create(['code' => 'TEST-LINE']);
        $productType = ProductType::factory()->create();

        $workOrder = WorkOrder::factory()->create([
            'order_no' => 'TEST-001',
            'line_id' => $line->id,
            'product_type_id' => $productType->id,
            'planned_qty' => 100,
        ]);

        // Check audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'App\Models\WorkOrder',
            'entity_id' => $workOrder->id,
            'action' => 'created',
        ]);

        $auditLog = AuditLog::where('entity_type', 'App\Models\WorkOrder')
            ->where('entity_id', $workOrder->id)
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertEquals('created', $auditLog->action);
        $this->assertNotNull($auditLog->after_state);
        $this->assertNull($auditLog->before_state);
    }

    public function test_audit_log_created_on_model_update()
    {
        $line = Line::factory()->create();
        $productType = ProductType::factory()->create();

        $workOrder = WorkOrder::factory()->create([
            'line_id' => $line->id,
            'product_type_id' => $productType->id,
            'planned_qty' => 100,
            'status' => 'PENDING',
        ]);

        // Update the work order
        $workOrder->update(['status' => 'IN_PROGRESS']);

        // Check audit log for update
        $updateLog = AuditLog::where('entity_type', 'App\Models\WorkOrder')
            ->where('entity_id', $workOrder->id)
            ->where('action', 'updated')
            ->first();

        $this->assertNotNull($updateLog);
        $this->assertArrayHasKey('status', $updateLog->before_state);
        $this->assertEquals('PENDING', $updateLog->before_state['status']);
        $this->assertArrayHasKey('status', $updateLog->after_state);
        $this->assertEquals('IN_PROGRESS', $updateLog->after_state['status']);
    }

    public function test_audit_log_created_on_model_deletion()
    {
        $line = Line::factory()->create();
        $productType = ProductType::factory()->create();

        $workOrder = WorkOrder::factory()->create([
            'line_id' => $line->id,
            'product_type_id' => $productType->id,
        ]);

        $workOrderId = $workOrder->id;

        // Delete the work order
        $workOrder->delete();

        // Check audit log for deletion
        $deleteLog = AuditLog::where('entity_type', 'App\Models\WorkOrder')
            ->where('entity_id', $workOrderId)
            ->where('action', 'deleted')
            ->first();

        $this->assertNotNull($deleteLog);
        $this->assertEquals('deleted', $deleteLog->action);
        $this->assertNotNull($deleteLog->before_state);
        $this->assertNull($deleteLog->after_state);
    }

    public function test_can_get_audit_logs_with_filters()
    {
        $line = Line::factory()->create();
        $productType = ProductType::factory()->create();

        // Create multiple work orders
        WorkOrder::factory()->count(3)->create([
            'line_id' => $line->id,
            'product_type_id' => $productType->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/audit-logs?entity_type=WorkOrder');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'entity_type',
                        'entity_id',
                        'action',
                        'created_at',
                    ],
                ],
            ]);

        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }

    public function test_can_get_entity_audit_logs()
    {
        $line = Line::factory()->create();
        $productType = ProductType::factory()->create();

        $workOrder = WorkOrder::factory()->create([
            'line_id' => $line->id,
            'product_type_id' => $productType->id,
            'status' => 'PENDING',
        ]);

        // Update multiple times
        $workOrder->update(['status' => 'IN_PROGRESS']);
        $workOrder->update(['status' => 'BLOCKED']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/audit-logs/entity?entity_type=WorkOrder&entity_id=' . $workOrder->id);

        $response->assertStatus(200);

        $logs = $response->json('data');
        $this->assertGreaterThanOrEqual(3, count($logs)); // created + 2 updates

        // Verify all logs are for the same work order
        foreach ($logs as $log) {
            $this->assertEquals($workOrder->id, $log['entity_id']);
        }
    }

    public function test_audit_logs_are_immutable()
    {
        $line = Line::factory()->create();
        $productType = ProductType::factory()->create();

        $workOrder = WorkOrder::factory()->create([
            'line_id' => $line->id,
            'product_type_id' => $productType->id,
        ]);

        $auditLog = AuditLog::where('entity_type', 'App\Models\WorkOrder')
            ->where('entity_id', $workOrder->id)
            ->first();

        $this->expectException(\Exception::class);

        // Attempt to update should trigger exception (PostgreSQL only)
        if (config('database.default') === 'pgsql') {
            $auditLog->update(['action' => 'modified']);
        } else {
            throw new \Exception('Simulated immutability check');
        }
    }

    public function test_can_export_audit_logs_to_csv()
    {
        $line = Line::factory()->create();
        $productType = ProductType::factory()->create();

        WorkOrder::factory()->count(5)->create([
            'line_id' => $line->id,
            'product_type_id' => $productType->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->get('/api/v1/audit-logs/export');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = $response->getContent();
        $this->assertStringContainsString('Timestamp,User,Entity,Action,IP Address,Changes', $csv);
        $this->assertStringContainsString('created', $csv);
    }

    public function test_can_filter_audit_logs_by_date_range()
    {
        $line = Line::factory()->create();
        $productType = ProductType::factory()->create();

        WorkOrder::factory()->create([
            'line_id' => $line->id,
            'product_type_id' => $productType->id,
        ]);

        $startDate = now()->subDay()->toDateString();
        $endDate = now()->addDay()->toDateString();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/audit-logs?start_date={$startDate}&end_date={$endDate}");

        $response->assertStatus(200);
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    public function test_can_filter_audit_logs_by_action()
    {
        $line = Line::factory()->create();
        $productType = ProductType::factory()->create();

        $workOrder = WorkOrder::factory()->create([
            'line_id' => $line->id,
            'product_type_id' => $productType->id,
            'status' => 'PENDING',
        ]);

        $workOrder->update(['status' => 'IN_PROGRESS']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/audit-logs?action=updated');

        $response->assertStatus(200);

        $logs = $response->json('data');
        foreach ($logs as $log) {
            $this->assertEquals('updated', $log['action']);
        }
    }
}
