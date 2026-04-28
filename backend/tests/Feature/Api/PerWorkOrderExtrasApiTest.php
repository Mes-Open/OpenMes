<?php

namespace Tests\Feature\Api;

use App\Models\AdditionalCost;
use App\Models\AnomalyReason;
use App\Models\Attachment;
use App\Models\CostSource;
use App\Models\ProductionAnomaly;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PerWorkOrderExtrasApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $supervisor;
    protected User $operator;
    protected string $adminToken;
    protected string $supervisorToken;
    protected string $operatorToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;
        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('Supervisor');
        $this->supervisorToken = $this->supervisor->createToken('test')->plainTextToken;
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
        $this->operatorToken = $this->operator->createToken('test')->plainTextToken;
    }

    private function authAdmin() { return $this->withHeader('Authorization', "Bearer {$this->adminToken}"); }
    private function authSupervisor() { return $this->withHeader('Authorization', "Bearer {$this->supervisorToken}"); }
    private function authOperator() { return $this->withHeader('Authorization', "Bearer {$this->operatorToken}"); }

    // ── Production Anomalies ──────────────────────────────────────────────

    public function test_operator_can_record_anomaly(): void
    {
        $wo = WorkOrder::factory()->create();
        $reason = AnomalyReason::create(['code' => 'SCRAP', 'name' => 'Scrap', 'is_active' => true]);

        $r = $this->authOperator()->postJson("/api/v1/work-orders/{$wo->id}/production-anomalies", [
            'anomaly_reason_id' => $reason->id,
            'planned_qty' => 100,
            'actual_qty' => 95,
            'comment' => 'Material defect',
        ]);
        $r->assertStatus(201)
            ->assertJsonPath('data.created_by_id', $this->operator->id);
        // Deviation auto-computed: (95-100)/100*100 = -5.00
        $this->assertEquals('-5.00', $r->json('data.deviation_pct'));
    }

    public function test_anomaly_filter_by_work_order(): void
    {
        $wo1 = WorkOrder::factory()->create();
        $wo2 = WorkOrder::factory()->create();
        $reason = AnomalyReason::create(['code' => 'X', 'name' => 'X', 'is_active' => true]);
        ProductionAnomaly::create([
            'work_order_id' => $wo1->id, 'anomaly_reason_id' => $reason->id,
            'created_by_id' => $this->operator->id,
            'planned_qty' => 10, 'actual_qty' => 9, 'status' => 'draft',
            'product_name' => 'X',
        ]);
        ProductionAnomaly::create([
            'work_order_id' => $wo2->id, 'anomaly_reason_id' => $reason->id,
            'created_by_id' => $this->operator->id,
            'planned_qty' => 5, 'actual_qty' => 5, 'status' => 'draft',
            'product_name' => 'Y',
        ]);

        $r = $this->authAdmin()->getJson("/api/v1/production-anomalies?work_order_id={$wo1->id}");
        $this->assertCount(1, $r->json('data'));
    }

    public function test_operator_can_update_own_draft(): void
    {
        $wo = WorkOrder::factory()->create();
        $reason = AnomalyReason::create(['code' => 'X', 'name' => 'X', 'is_active' => true]);
        $a = ProductionAnomaly::create([
            'work_order_id' => $wo->id, 'anomaly_reason_id' => $reason->id,
            'created_by_id' => $this->operator->id,
            'planned_qty' => 10, 'actual_qty' => 9, 'status' => 'draft',
            'product_name' => 'X',
        ]);

        $this->authOperator()->patchJson("/api/v1/production-anomalies/{$a->id}", [
            'comment' => 'updated',
        ])->assertStatus(200);
    }

    public function test_operator_cannot_edit_others_anomaly(): void
    {
        $wo = WorkOrder::factory()->create();
        $reason = AnomalyReason::create(['code' => 'X', 'name' => 'X', 'is_active' => true]);
        $a = ProductionAnomaly::create([
            'work_order_id' => $wo->id, 'anomaly_reason_id' => $reason->id,
            'created_by_id' => $this->admin->id, // not operator
            'planned_qty' => 10, 'actual_qty' => 9, 'status' => 'draft',
            'product_name' => 'X',
        ]);
        $this->authOperator()->patchJson("/api/v1/production-anomalies/{$a->id}", [
            'comment' => 'hijack',
        ])->assertStatus(403);
    }

    public function test_supervisor_can_process_anomaly(): void
    {
        $wo = WorkOrder::factory()->create();
        $reason = AnomalyReason::create(['code' => 'X', 'name' => 'X', 'is_active' => true]);
        $a = ProductionAnomaly::create([
            'work_order_id' => $wo->id, 'anomaly_reason_id' => $reason->id,
            'created_by_id' => $this->operator->id,
            'planned_qty' => 10, 'actual_qty' => 9, 'status' => 'draft',
            'product_name' => 'X',
        ]);
        $this->authSupervisor()->postJson("/api/v1/production-anomalies/{$a->id}/process")
            ->assertStatus(200);
        $this->assertEquals('processed', $a->fresh()->status);
    }

    public function test_supervisor_cannot_delete_anomaly(): void
    {
        $wo = WorkOrder::factory()->create();
        $reason = AnomalyReason::create(['code' => 'X', 'name' => 'X', 'is_active' => true]);
        $a = ProductionAnomaly::create([
            'work_order_id' => $wo->id, 'anomaly_reason_id' => $reason->id,
            'created_by_id' => $this->operator->id,
            'planned_qty' => 10, 'actual_qty' => 9, 'status' => 'draft',
            'product_name' => 'X',
        ]);
        $this->authSupervisor()->deleteJson("/api/v1/production-anomalies/{$a->id}")->assertStatus(403);
    }

    public function test_admin_can_delete_anomaly(): void
    {
        $wo = WorkOrder::factory()->create();
        $reason = AnomalyReason::create(['code' => 'X', 'name' => 'X', 'is_active' => true]);
        $a = ProductionAnomaly::create([
            'work_order_id' => $wo->id, 'anomaly_reason_id' => $reason->id,
            'created_by_id' => $this->operator->id,
            'planned_qty' => 10, 'actual_qty' => 9, 'status' => 'draft',
            'product_name' => 'X',
        ]);
        $this->authAdmin()->deleteJson("/api/v1/production-anomalies/{$a->id}")->assertStatus(200);
    }

    // ── Additional Costs ──────────────────────────────────────────────────

    public function test_supervisor_can_add_cost(): void
    {
        $wo = WorkOrder::factory()->create();
        $cs = CostSource::create(['code' => 'ELEC', 'name' => 'Elec', 'is_active' => true]);
        $r = $this->authSupervisor()->postJson("/api/v1/work-orders/{$wo->id}/additional-costs", [
            'cost_source_id' => $cs->id,
            'description' => 'Energy overage',
            'amount' => 12.50,
            'currency' => 'EUR',
        ]);
        $r->assertStatus(201);
    }

    public function test_operator_cannot_add_cost(): void
    {
        $wo = WorkOrder::factory()->create();
        $this->authOperator()->postJson("/api/v1/work-orders/{$wo->id}/additional-costs", [
            'description' => 'X', 'amount' => 1,
        ])->assertStatus(403);
    }

    public function test_costs_listed_per_work_order(): void
    {
        $wo = WorkOrder::factory()->create();
        AdditionalCost::create([
            'work_order_id' => $wo->id, 'created_by_id' => $this->supervisor->id,
            'description' => 'A', 'amount' => 10,
        ]);
        AdditionalCost::create([
            'work_order_id' => $wo->id, 'created_by_id' => $this->supervisor->id,
            'description' => 'B', 'amount' => 20,
        ]);
        $r = $this->authAdmin()->getJson("/api/v1/work-orders/{$wo->id}/additional-costs");
        $this->assertCount(2, $r->json('data'));
    }

    public function test_supervisor_can_delete_cost(): void
    {
        $wo = WorkOrder::factory()->create();
        $c = AdditionalCost::create([
            'work_order_id' => $wo->id, 'created_by_id' => $this->supervisor->id,
            'description' => 'X', 'amount' => 1,
        ]);
        $this->authSupervisor()->deleteJson("/api/v1/additional-costs/{$c->id}")->assertStatus(200);
    }

    // ── Attachments ───────────────────────────────────────────────────────

    public function test_can_upload_attachment(): void
    {
        Storage::fake('local');
        $wo = WorkOrder::factory()->create();

        $r = $this->authOperator()->post('/api/v1/attachments', [
            'entity_type' => 'work_order',
            'entity_id' => $wo->id,
            'file' => UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'),
        ], ['Authorization' => "Bearer {$this->operatorToken}"]);

        $r->assertStatus(201)
            ->assertJsonPath('data.original_name', 'photo.jpg')
            ->assertJsonPath('data.entity_type', 'work_order');
        $this->assertEquals(1, Attachment::count());
    }

    public function test_can_list_attachments_by_entity(): void
    {
        $wo = WorkOrder::factory()->create();
        Attachment::create([
            'entity_type' => 'work_order', 'entity_id' => $wo->id,
            'original_name' => 'a.jpg', 'storage_path' => 'attachments/a.jpg',
            'uploaded_by_id' => $this->operator->id,
        ]);
        Attachment::create([
            'entity_type' => 'work_order', 'entity_id' => $wo->id + 1,
            'original_name' => 'b.jpg', 'storage_path' => 'attachments/b.jpg',
            'uploaded_by_id' => $this->operator->id,
        ]);

        $r = $this->authAdmin()->getJson("/api/v1/attachments?entity_type=work_order&entity_id={$wo->id}");
        $r->assertStatus(200);
        $this->assertCount(1, $r->json('data'));
    }

    public function test_uploader_can_delete_own_attachment(): void
    {
        Storage::fake('local');
        $a = Attachment::create([
            'entity_type' => 'work_order', 'entity_id' => 1,
            'original_name' => 'a.jpg', 'storage_path' => 'attachments/a.jpg',
            'uploaded_by_id' => $this->operator->id,
        ]);
        $this->authOperator()->deleteJson("/api/v1/attachments/{$a->id}")->assertStatus(200);
    }

    public function test_random_operator_cannot_delete_others_attachment(): void
    {
        $other = User::factory()->create();
        $other->assignRole('Operator');
        $otherToken = $other->createToken('test')->plainTextToken;

        Storage::fake('local');
        $a = Attachment::create([
            'entity_type' => 'work_order', 'entity_id' => 1,
            'original_name' => 'a.jpg', 'storage_path' => 'attachments/a.jpg',
            'uploaded_by_id' => $this->operator->id,
        ]);
        $this->withHeader('Authorization', "Bearer {$otherToken}")
            ->deleteJson("/api/v1/attachments/{$a->id}")
            ->assertStatus(403);
    }
}
