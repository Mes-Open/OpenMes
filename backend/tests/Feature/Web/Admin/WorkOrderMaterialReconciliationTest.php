<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Batch;
use App\Models\Material;
use App\Models\MaterialAllocation;
use App\Models\MaterialType;
use App\Models\ProductType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Material\MaterialAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Materials reconciliation on the admin work-order page (#99): consume, return
 * and reclassify actions redirect back with a flash and move stock correctly.
 */
class WorkOrderMaterialReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private Material $material;

    private WorkOrder $workOrder;

    private MaterialAllocation $allocation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $type = MaterialType::create(['code' => 'RAW', 'name' => 'Raw']);
        $this->material = Material::create([
            'code' => 'M1', 'name' => 'Material 1', 'material_type_id' => $type->id,
            'unit_of_measure' => 'kg', 'stock_quantity' => 500,
        ]);

        $this->workOrder = WorkOrder::factory()->create([
            'product_type_id' => ProductType::factory()->create()->id,
            'process_snapshot' => ['bom' => [[
                'material_id' => $this->material->id, 'material_code' => 'M1', 'material_name' => 'Material 1',
                'unit_of_measure' => 'kg', 'quantity_per_unit' => 1.0, 'scrap_percentage' => 0,
            ]]],
        ]);
        $batch = Batch::factory()->create([
            'work_order_id' => $this->workOrder->id, 'target_qty' => 100, 'produced_qty' => 0, 'status' => Batch::STATUS_PENDING,
        ]);

        app(MaterialAllocationService::class)->allocateForBatch($batch, $this->admin());
        $this->allocation = MaterialAllocation::firstWhere('batch_id', $batch->id);
    }

    private function admin(): User
    {
        return once(fn () => tap(User::factory()->create(), fn ($u) => $u->assignRole('Admin')));
    }

    private function base(): string
    {
        return "/admin/work-orders/{$this->workOrder->id}";
    }

    public function test_admin_records_consumption(): void
    {
        $this->actingAs($this->admin())
            ->post($this->base()."/allocations/{$this->allocation->id}/consume", ['consumed_qty' => 70])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertEqualsWithDelta(70.0, (float) $this->allocation->fresh()->consumed_qty, 0.0001);
    }

    public function test_admin_returns_unused_material(): void
    {
        $this->actingAs($this->admin())
            ->post($this->base()."/allocations/{$this->allocation->id}/return", ['qty' => 25])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertEqualsWithDelta(425.0, (float) $this->material->fresh()->stock_quantity, 0.0001);
        $this->assertEqualsWithDelta(75.0, (float) $this->allocation->fresh()->allocated_qty, 0.0001);
    }

    public function test_admin_reclassifies_to_another_class(): void
    {
        $target = Material::create([
            'code' => 'M2', 'name' => 'Material 2',
            'material_type_id' => MaterialType::create(['code' => 'ALT', 'name' => 'Alt'])->id,
            'unit_of_measure' => 'kg', 'stock_quantity' => 0,
        ]);

        $this->actingAs($this->admin())
            ->post($this->base().'/reclassify', [
                'source_material_id' => $this->material->id,
                'target_material_id' => $target->id,
                'qty' => 30,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        // Source already at 400 after allocation; reclassify moves 30 more out.
        $this->assertEqualsWithDelta(370.0, (float) $this->material->fresh()->stock_quantity, 0.0001);
        $this->assertEqualsWithDelta(30.0, (float) $target->fresh()->stock_quantity, 0.0001);
    }

    public function test_guest_cannot_reconcile(): void
    {
        $this->post($this->base()."/allocations/{$this->allocation->id}/return", ['qty' => 25])
            ->assertRedirect('/login');
    }

    public function test_allocation_from_another_work_order_is_404(): void
    {
        $otherWo = WorkOrder::factory()->create(['process_snapshot' => ['bom' => []]]);

        $this->actingAs($this->admin())
            ->post("/admin/work-orders/{$otherWo->id}/allocations/{$this->allocation->id}/return", ['qty' => 10])
            ->assertNotFound();
    }
}
