<?php

namespace Tests\Feature\WorkOrder;

use App\Models\Batch;
use App\Models\BomItem;
use App\Models\Material;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Material\BomService;
use App\Services\Material\MaterialAllocationService;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end coverage for associating a work order with multiple BOMs: the
 * admin create/edit flow, the resulting summed requirements, live consumption,
 * and the guarantee that single-BOM orders keep working unchanged.
 */
class MultiBomOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    /**
     * A product type with two BOMs (process templates), each carrying one item.
     *
     * @return array{0: ProductType, 1: ProcessTemplate, 2: ProcessTemplate, 3: Material, 4: Material}
     */
    private function productWithTwoBoms(): array
    {
        $productType = ProductType::factory()->create();
        $bomA = ProcessTemplate::factory()->withSteps(1)->create(['product_type_id' => $productType->id, 'name' => 'BOM A', 'version' => 1]);
        $bomB = ProcessTemplate::factory()->withSteps(1)->create(['product_type_id' => $productType->id, 'name' => 'BOM B', 'version' => 2]);
        $steel = Material::factory()->create(['stock_quantity' => 100000]);
        $paint = Material::factory()->create(['stock_quantity' => 100000]);
        BomItem::factory()->create(['process_template_id' => $bomA->id, 'material_id' => $steel->id, 'quantity_per_unit' => 2]);
        BomItem::factory()->create(['process_template_id' => $bomB->id, 'material_id' => $paint->id, 'quantity_per_unit' => 3]);

        return [$productType, $bomA, $bomB, $steel, $paint];
    }

    public function test_create_order_with_multiple_boms_links_them_and_merges_the_snapshot(): void
    {
        [$productType, $bomA, $bomB, $steel, $paint] = $this->productWithTwoBoms();

        $response = $this->actingAs($this->admin)->post('/admin/work-orders', [
            'order_no' => 'WO-MULTI-1',
            'product_type_id' => $productType->id,
            'bom_template_ids' => [$bomA->id, $bomB->id],
            'planned_qty' => 10,
        ]);

        $response->assertRedirect();
        $workOrder = WorkOrder::where('order_no', 'WO-MULTI-1')->firstOrFail();

        // Both BOMs recorded on the pivot, both active.
        $this->assertDatabaseHas('work_order_boms', ['work_order_id' => $workOrder->id, 'process_template_id' => $bomA->id, 'is_active' => true]);
        $this->assertDatabaseHas('work_order_boms', ['work_order_id' => $workOrder->id, 'process_template_id' => $bomB->id, 'is_active' => true]);

        // Snapshot carries the union of both BOMs' materials.
        $materialIds = array_column($workOrder->process_snapshot['bom'], 'material_id');
        $this->assertEqualsCanonicalizing([$steel->id, $paint->id], $materialIds);
        $this->assertEqualsCanonicalizing([$bomA->id, $bomB->id], $workOrder->process_snapshot['bom_template_ids']);
    }

    public function test_requirements_sum_across_selected_boms(): void
    {
        $productType = ProductType::factory()->create();
        [$bomA, $bomB] = ProcessTemplate::factory()->count(2)
            ->sequence(['version' => 1], ['version' => 2])
            ->create(['product_type_id' => $productType->id]);
        $glue = Material::factory()->create();
        // Same material in both BOMs, same scrap → requirement must sum.
        BomItem::factory()->create(['process_template_id' => $bomA->id, 'material_id' => $glue->id, 'quantity_per_unit' => 2]);
        BomItem::factory()->create(['process_template_id' => $bomB->id, 'material_id' => $glue->id, 'quantity_per_unit' => 3]);

        $workOrder = app(WorkOrderService::class)->createWorkOrder([
            'order_no' => 'WO-SUM-1',
            'product_type_id' => $productType->id,
            'bom_template_ids' => [$bomA->id, $bomB->id],
            'planned_qty' => 10,
        ]);

        $requirements = app(BomService::class)->calculateFromSnapshot($workOrder->process_snapshot, 10);

        $this->assertCount(1, $requirements);
        // (2 + 3) per unit * 10 units = 50.
        $this->assertEqualsWithDelta(50.0, $requirements[0]['required_qty'], 0.0001);
    }

    public function test_consumption_allocates_the_summed_requirement(): void
    {
        $productType = ProductType::factory()->create();
        [$bomA, $bomB] = ProcessTemplate::factory()->count(2)
            ->sequence(['version' => 1], ['version' => 2])
            ->create(['product_type_id' => $productType->id]);
        $glue = Material::factory()->create(['stock_quantity' => 100000]);
        BomItem::factory()->create(['process_template_id' => $bomA->id, 'material_id' => $glue->id, 'quantity_per_unit' => 2, 'consumed_at' => 'start']);
        BomItem::factory()->create(['process_template_id' => $bomB->id, 'material_id' => $glue->id, 'quantity_per_unit' => 4, 'consumed_at' => 'start']);

        $workOrder = app(WorkOrderService::class)->createWorkOrder([
            'order_no' => 'WO-CONSUME-1',
            'product_type_id' => $productType->id,
            'bom_template_ids' => [$bomA->id, $bomB->id],
            'planned_qty' => 50,
        ]);

        $batch = Batch::factory()->create([
            'work_order_id' => $workOrder->id,
            'target_qty' => 50,
            'produced_qty' => 0,
            'status' => Batch::STATUS_PENDING,
        ]);

        $allocations = app(MaterialAllocationService::class)->allocateForBatch($batch, $this->admin);

        // One line for the shared material, allocated at the summed rate: (2+4) * 50 = 300.
        $this->assertCount(1, $allocations);
        $this->assertEqualsWithDelta(300.0, (float) $allocations->first()->allocated_qty, 0.0001);
    }

    public function test_switching_boms_rebuilds_the_snapshot(): void
    {
        [$productType, $bomA, $bomB, $steel, $paint] = $this->productWithTwoBoms();

        $workOrder = app(WorkOrderService::class)->createWorkOrder([
            'order_no' => 'WO-SWITCH-1',
            'product_type_id' => $productType->id,
            'bom_template_ids' => [$bomA->id],
            'planned_qty' => 10,
        ]);
        $this->assertCount(1, $workOrder->process_snapshot['bom']);

        $response = $this->actingAs($this->admin)->put("/admin/work-orders/{$workOrder->id}", [
            'order_no' => 'WO-SWITCH-1',
            'product_type_id' => $productType->id,
            'bom_template_ids' => [$bomA->id, $bomB->id],
            'planned_qty' => 10,
            'status' => 'PENDING',
        ]);

        $response->assertRedirect();
        $workOrder->refresh();
        $this->assertCount(2, $workOrder->process_snapshot['bom']);
        $this->assertDatabaseHas('work_order_boms', ['work_order_id' => $workOrder->id, 'process_template_id' => $bomB->id]);
    }

    public function test_cannot_switch_boms_after_production_started(): void
    {
        [$productType, $bomA, $bomB] = $this->productWithTwoBoms();

        $workOrder = app(WorkOrderService::class)->createWorkOrder([
            'order_no' => 'WO-LOCK-1',
            'product_type_id' => $productType->id,
            'bom_template_ids' => [$bomA->id],
            'planned_qty' => 10,
        ]);
        Batch::factory()->create(['work_order_id' => $workOrder->id]);
        $before = $workOrder->process_snapshot;

        $response = $this->actingAs($this->admin)->put("/admin/work-orders/{$workOrder->id}", [
            'order_no' => 'WO-LOCK-1',
            'product_type_id' => $productType->id,
            'bom_template_ids' => [$bomA->id, $bomB->id],
            'planned_qty' => 999, // also changed — must NOT persist when the BOM change is rejected
            'status' => 'PENDING',
        ]);

        $response->assertSessionHas('error');
        $fresh = $workOrder->fresh();
        $this->assertEquals($before, $fresh->process_snapshot);
        // The rejected BOM change must roll back the field edits too (no partial save).
        $this->assertEqualsWithDelta(10.0, (float) $fresh->planned_qty, 0.0001);
    }

    public function test_validation_rejects_bom_from_a_different_product_type(): void
    {
        $productType = ProductType::factory()->create();
        $other = ProductType::factory()->create();
        $foreignBom = ProcessTemplate::factory()->create(['product_type_id' => $other->id]);

        $response = $this->actingAs($this->admin)->post('/admin/work-orders', [
            'order_no' => 'WO-XPT',
            'product_type_id' => $productType->id,
            'bom_template_ids' => [$foreignBom->id],
            'planned_qty' => 10,
        ]);

        $response->assertSessionHasErrors('bom_template_ids.0');
    }

    public function test_single_bom_order_without_selection_is_unaffected(): void
    {
        $productType = ProductType::factory()->create();
        $template = ProcessTemplate::factory()->withSteps(2)->create(['product_type_id' => $productType->id]);
        $material = Material::factory()->create();
        BomItem::factory()->create(['process_template_id' => $template->id, 'material_id' => $material->id, 'quantity_per_unit' => 5, 'scrap_percentage' => 10]);

        $workOrder = app(WorkOrderService::class)->createWorkOrder([
            'order_no' => 'WO-SINGLE-1',
            'product_type_id' => $productType->id,
            'planned_qty' => 10,
        ]);

        // Requirements from the snapshot match the template's own single BOM.
        $fromSnapshot = app(BomService::class)->calculateFromSnapshot($workOrder->process_snapshot, 10);
        $fromTemplate = app(BomService::class)->calculateRequirements($template, 10);

        $this->assertCount(1, $fromSnapshot);
        $this->assertEqualsWithDelta($fromTemplate[0]['required_qty'], $fromSnapshot[0]['required_qty'], 0.0001);
        // 5 * 10 * (1 + 10%) = 55.
        $this->assertEqualsWithDelta(55.0, $fromSnapshot[0]['required_qty'], 0.0001);
    }

    public function test_validation_rejects_nonexistent_bom(): void
    {
        $productType = ProductType::factory()->create();

        $response = $this->actingAs($this->admin)->post('/admin/work-orders', [
            'order_no' => 'WO-BAD-BOM',
            'product_type_id' => $productType->id,
            'bom_template_ids' => [999999],
            'planned_qty' => 10,
        ]);

        $response->assertSessionHasErrors('bom_template_ids.0');
    }
}
