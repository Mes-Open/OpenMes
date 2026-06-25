<?php

namespace Tests\Feature\WorkOrder;

use App\Models\BomItem;
use App\Models\Line;
use App\Models\Material;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression: material type is optional (#129), so a BOM component may have no
 * material type. Creating a work order (which snapshots the BOM) must not crash
 * on the null materialType — found building a computer-production config where
 * components had no type.
 */
class BomSnapshotNullTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_work_order_snapshot_handles_a_bom_material_without_a_type(): void
    {
        $line = Line::factory()->create();
        $productType = ProductType::factory()->create();
        $template = ProcessTemplate::factory()->create([
            'product_type_id' => $productType->id,
            'is_active' => true,
            'version' => 1,
        ]);
        $material = Material::factory()->create(['material_type_id' => null]);
        BomItem::factory()->create([
            'process_template_id' => $template->id,
            'material_id' => $material->id,
            'quantity_per_unit' => 2,
        ]);

        $wo = app(WorkOrderService::class)->createWorkOrder([
            'order_no' => 'WO-NULLTYPE-1',
            'product_type_id' => $productType->id,
            'line_id' => $line->id,
            'planned_qty' => 10,
        ]);

        $this->assertNotNull($wo->id);
        $bom = $wo->process_snapshot['bom'] ?? [];
        $this->assertCount(1, $bom);
        $this->assertNull($bom[0]['material_type']);
        $this->assertSame($material->id, $bom[0]['material_id']);
    }
}
