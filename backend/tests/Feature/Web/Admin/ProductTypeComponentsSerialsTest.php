<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\BatchStepLotConsumption;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\ProductType;
use App\Models\SerialUnit;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductTypeComponentsSerialsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Admin', 'web');
        Role::findOrCreate('Operator', 'web');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
    }

    /**
     * Consume $qty of $material (via a fresh lot) at a step of a finished batch
     * belonging to $productType. Returns the material lot used.
     */
    private function consume(ProductType $productType, Material $material, float $qty): MaterialLot
    {
        $lot = MaterialLot::factory()->create(['material_id' => $material->id]);

        $wo = WorkOrder::factory()->create([
            'product_type_id' => $productType->id,
            'status' => WorkOrder::STATUS_DONE,
        ]);
        $batch = Batch::factory()->create(['work_order_id' => $wo->id, 'status' => Batch::STATUS_DONE]);
        $step = BatchStep::factory()->create(['batch_id' => $batch->id, 'step_number' => 1]);

        BatchStepLotConsumption::create([
            'batch_step_id' => $step->id,
            'material_lot_id' => $lot->id,
            'quantity_consumed' => $qty,
            'consumed_at' => now(),
            'recorded_by_id' => $this->operator->id,
        ]);

        return $lot;
    }

    public function test_show_requires_admin(): void
    {
        $productType = ProductType::factory()->create();

        $this->actingAs($this->operator)
            ->get(route('admin.product-types.show', $productType))
            ->assertForbidden();
    }

    public function test_components_are_aggregated_by_material_across_work_orders(): void
    {
        $productType = ProductType::factory()->create();
        $steel = Material::factory()->create(['name' => 'Steel Sheet', 'code' => 'STL', 'unit_of_measure' => 'kg']);

        // Same material consumed twice (two lots, two work orders) → one row, summed.
        $this->consume($productType, $steel, 5);
        $this->consume($productType, $steel, 3.5);

        $this->actingAs($this->admin)
            ->get(route('admin.product-types.show', $productType))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/product-types/Show')
                ->has('componentsUsed', 1)
                ->where('componentsUsed.0.code', 'STL')
                ->where('componentsUsed.0.total_consumed', 8.5)
                ->where('componentsUsed.0.lot_count', 2)
            );
    }

    public function test_components_exclude_other_product_types(): void
    {
        $productType = ProductType::factory()->create();
        $other = ProductType::factory()->create();

        $this->consume($productType, Material::factory()->create(['code' => 'MINE']), 2);
        $this->consume($other, Material::factory()->create(['code' => 'THEIRS']), 9);

        $this->actingAs($this->admin)
            ->get(route('admin.product-types.show', $productType))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('componentsUsed', 1)
                ->where('componentsUsed.0.code', 'MINE')
            );
    }

    public function test_components_exclude_soft_deleted_production(): void
    {
        $this->actingAs($this->admin);

        $productType = ProductType::factory()->create();
        $material = Material::factory()->create(['code' => 'STL']);
        $lot = $this->consume($productType, $material, 5);

        // Soft-delete the consuming batch step's batch → consumption no longer counts.
        $consumption = BatchStepLotConsumption::where('material_lot_id', $lot->id)->firstOrFail();
        BatchStep::findOrFail($consumption->batch_step_id)->batch->delete();

        $this->get(route('admin.product-types.show', $productType))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('componentsUsed', 0));
    }

    public function test_serials_are_listed_with_status_breakdown(): void
    {
        $productType = ProductType::factory()->create();
        $wo = WorkOrder::factory()->create(['product_type_id' => $productType->id]);

        SerialUnit::factory()->create(['work_order_id' => $wo->id, 'status' => SerialUnit::STATUS_COMPLETED]);
        SerialUnit::factory()->create(['work_order_id' => $wo->id, 'status' => SerialUnit::STATUS_COMPLETED]);
        SerialUnit::factory()->create(['work_order_id' => $wo->id, 'status' => SerialUnit::STATUS_SCRAPPED]);

        // A serial under a different product type must not leak in.
        $otherWo = WorkOrder::factory()->create();
        SerialUnit::factory()->create(['work_order_id' => $otherWo->id]);

        $this->actingAs($this->admin)
            ->get(route('admin.product-types.show', $productType))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('serials.total', 3)
                ->where('serials.status_counts.completed', 2)
                ->where('serials.status_counts.scrapped', 1)
                ->has('serials.recent', 3)
            );
    }

    public function test_show_renders_with_no_production_yet(): void
    {
        $productType = ProductType::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.product-types.show', $productType))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('componentsUsed', 0)
                ->where('serials.total', 0)
                ->has('serials.recent', 0)
            );
    }
}
