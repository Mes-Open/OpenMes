<?php

namespace Tests\Feature;

use App\Models\AnomalyReason;
use App\Models\Line;
use App\Models\ProcessTemplate;
use App\Models\ProductionAnomaly;
use App\Models\ProductType;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductionAnomalyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private WorkOrder $workOrder;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'guard_name' => 'sanctum']);
        Role::create(['name' => 'Supervisor', 'guard_name' => 'sanctum']);
        Role::create(['name' => 'Operator', 'guard_name' => 'sanctum']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        $this->workOrder = $this->createWorkOrder();
    }

    /**
     * Build a minimal WorkOrder through the factory chain so all required
     * relationships (Line, ProductType, ProcessTemplate, TemplateSteps) exist.
     */
    private function createWorkOrder(): WorkOrder
    {
        return WorkOrder::factory()->create();
    }

    /**
     * Create an AnomalyReason for use in tests.
     */
    private function createAnomalyReason(string $code = 'AR001', string $name = 'Overproduction'): AnomalyReason
    {
        return AnomalyReason::create([
            'code'      => $code,
            'name'      => $name,
            'is_active' => true,
        ]);
    }

    /**
     * Create a minimal draft ProductionAnomaly.
     */
    private function createAnomaly(array $overrides = []): ProductionAnomaly
    {
        return ProductionAnomaly::create(array_merge([
            'work_order_id' => $this->workOrder->id,
            'created_by_id' => $this->admin->id,
            'product_name'  => 'Widget A',
            'planned_qty'   => 100,
            'actual_qty'    => 110,
            'status'        => ProductionAnomaly::STATUS_DRAFT,
        ], $overrides));
    }

    public function test_admin_can_list_anomalies(): void
    {
        $this->createAnomaly(['product_name' => 'Widget Alpha']);
        $this->createAnomaly(['product_name' => 'Widget Beta', 'actual_qty' => 50]);

        $response = $this->actingAs($this->admin)->get(route('admin.production-anomalies.index'));

        $response->assertStatus(200);
        $response->assertSee('Widget Alpha');
        $response->assertSee('Widget Beta');
    }

    public function test_admin_can_create_anomaly(): void
    {
        $reason = $this->createAnomalyReason();

        $response = $this->actingAs($this->admin)->post(route('admin.production-anomalies.store'), [
            'work_order_id'     => $this->workOrder->id,
            'anomaly_reason_id' => $reason->id,
            'product_name'      => 'Widget A',
            'planned_qty'       => 100,
            'actual_qty'        => 115,
            'comment'           => 'Produced 15 extra units.',
        ]);

        // Controller redirects back (uses redirect()->back()), so any redirect is valid.
        $response->assertRedirect();
        $this->assertDatabaseHas('production_anomalies', [
            'work_order_id'     => $this->workOrder->id,
            'anomaly_reason_id' => $reason->id,
            'product_name'      => 'Widget A',
            'planned_qty'       => 100,
            'actual_qty'        => 115,
            'status'            => ProductionAnomaly::STATUS_DRAFT,
        ]);
    }

    public function test_create_always_sets_status_to_draft(): void
    {
        $this->actingAs($this->admin)->post(route('admin.production-anomalies.store'), [
            'work_order_id' => $this->workOrder->id,
            'product_name'  => 'Widget B',
            'planned_qty'   => 50,
            'actual_qty'    => 40,
        ]);

        $this->assertDatabaseHas('production_anomalies', [
            'product_name' => 'Widget B',
            'status'       => ProductionAnomaly::STATUS_DRAFT,
        ]);
    }

    public function test_create_records_creator(): void
    {
        $this->actingAs($this->admin)->post(route('admin.production-anomalies.store'), [
            'work_order_id' => $this->workOrder->id,
            'product_name'  => 'Widget C',
            'planned_qty'   => 50,
            'actual_qty'    => 45,
        ]);

        $this->assertDatabaseHas('production_anomalies', [
            'product_name'  => 'Widget C',
            'created_by_id' => $this->admin->id,
        ]);
    }

    public function test_work_order_is_required_to_create_anomaly(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.production-anomalies.store'), [
            'product_name' => 'Widget X',
            'planned_qty'  => 50,
            'actual_qty'   => 45,
        ]);

        $response->assertSessionHasErrors('work_order_id');
    }

    public function test_work_order_must_exist_when_creating_anomaly(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.production-anomalies.store'), [
            'work_order_id' => 99999,
            'product_name'  => 'Widget X',
            'planned_qty'   => 50,
            'actual_qty'    => 45,
        ]);

        $response->assertSessionHasErrors('work_order_id');
    }

    public function test_admin_can_process_anomaly(): void
    {
        $anomaly = $this->createAnomaly();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.production-anomalies.process', $anomaly));

        // Controller redirects back from the process action.
        $response->assertRedirect();
        $this->assertDatabaseHas('production_anomalies', [
            'id'     => $anomaly->id,
            'status' => ProductionAnomaly::STATUS_PROCESSED,
        ]);
    }

    public function test_cannot_process_an_already_processed_anomaly(): void
    {
        $anomaly = $this->createAnomaly(['status' => ProductionAnomaly::STATUS_PROCESSED]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.production-anomalies.process', $anomaly));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        // Status must remain processed.
        $this->assertDatabaseHas('production_anomalies', [
            'id'     => $anomaly->id,
            'status' => ProductionAnomaly::STATUS_PROCESSED,
        ]);
    }

    public function test_admin_can_delete_draft_anomaly(): void
    {
        $anomaly = $this->createAnomaly(['status' => ProductionAnomaly::STATUS_DRAFT]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.production-anomalies.destroy', $anomaly));

        $response->assertRedirect(route('admin.production-anomalies.index'));
        $this->assertDatabaseMissing('production_anomalies', ['id' => $anomaly->id]);
    }

    public function test_cannot_delete_processed_anomaly(): void
    {
        $anomaly = $this->createAnomaly(['status' => ProductionAnomaly::STATUS_PROCESSED]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.production-anomalies.destroy', $anomaly));

        $response->assertRedirect(route('admin.production-anomalies.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('production_anomalies', ['id' => $anomaly->id]);
    }

    public function test_admin_can_filter_anomalies_by_status(): void
    {
        $this->createAnomaly(['product_name' => 'Draft Widget', 'status' => ProductionAnomaly::STATUS_DRAFT]);
        $this->createAnomaly(['product_name' => 'Processed Widget', 'status' => ProductionAnomaly::STATUS_PROCESSED]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.production-anomalies.index', ['status' => 'draft']));

        $response->assertStatus(200);
        $response->assertSee('Draft Widget');
        $response->assertDontSee('Processed Widget');
    }

    public function test_guest_cannot_access_anomalies(): void
    {
        $response = $this->get(route('admin.production-anomalies.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_guest_cannot_create_anomaly(): void
    {
        $response = $this->post(route('admin.production-anomalies.store'), [
            'work_order_id' => $this->workOrder->id,
            'product_name'  => 'Ghost Anomaly',
            'planned_qty'   => 50,
            'actual_qty'    => 40,
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseMissing('production_anomalies', ['product_name' => 'Ghost Anomaly']);
    }

    public function test_guest_cannot_process_anomaly(): void
    {
        $anomaly = $this->createAnomaly();

        $response = $this->post(route('admin.production-anomalies.process', $anomaly));
        $response->assertRedirect(route('login'));

        // Status must remain draft.
        $this->assertDatabaseHas('production_anomalies', [
            'id'     => $anomaly->id,
            'status' => ProductionAnomaly::STATUS_DRAFT,
        ]);
    }

    public function test_guest_cannot_delete_anomaly(): void
    {
        $anomaly = $this->createAnomaly();

        $response = $this->delete(route('admin.production-anomalies.destroy', $anomaly));
        $response->assertRedirect(route('login'));
        $this->assertDatabaseHas('production_anomalies', ['id' => $anomaly->id]);
    }
}
