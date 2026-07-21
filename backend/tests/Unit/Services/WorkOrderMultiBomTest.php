<?php

namespace Tests\Unit\Services;

use App\Models\BomItem;
use App\Models\Material;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\TemplateStep;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit coverage for the multi-BOM snapshot builder — how several selected BOMs
 * (process templates) collapse into the single flat `bom` list the consumption
 * and requirements engines read.
 */
class WorkOrderMultiBomTest extends TestCase
{
    use RefreshDatabase;

    private WorkOrderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WorkOrderService::class);
    }

    /** Find the merged BOM row for a material by id. */
    private function row(array $snapshot, int $materialId): ?array
    {
        foreach ($snapshot['bom'] as $r) {
            if ($r['material_id'] === $materialId) {
                return $r;
            }
        }

        return null;
    }

    public function test_build_snapshot_throws_on_unknown_template_id(): void
    {
        $productType = ProductType::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->service->buildProcessSnapshot($productType->id, [999999]);
    }

    public function test_build_snapshot_throws_on_cross_product_template(): void
    {
        $productType = ProductType::factory()->create();
        $other = ProductType::factory()->create();
        $foreign = ProcessTemplate::factory()->create(['product_type_id' => $other->id]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->buildProcessSnapshot($productType->id, [$foreign->id]);
    }

    public function test_no_template_yields_null_snapshot(): void
    {
        $productType = ProductType::factory()->create();

        $this->assertNull($this->service->buildProcessSnapshot($productType->id, []));
        $this->assertNull($this->service->buildProcessSnapshot(null, []));
    }

    public function test_empty_selection_falls_back_to_single_active_template(): void
    {
        $productType = ProductType::factory()->create();
        ProcessTemplate::factory()->inactive()->create(['product_type_id' => $productType->id, 'version' => 1]);
        $active = ProcessTemplate::factory()->withSteps(2)->create(['product_type_id' => $productType->id, 'version' => 2]);
        $material = Material::factory()->create();
        BomItem::factory()->create(['process_template_id' => $active->id, 'material_id' => $material->id, 'quantity_per_unit' => 4]);

        $snapshot = $this->service->buildProcessSnapshot($productType->id, []);

        $this->assertSame([$active->id], $snapshot['bom_template_ids']);
        $this->assertCount(1, $snapshot['bom']);
        $this->assertSame(4.0, $snapshot['bom'][0]['quantity_per_unit']);
        // Structure comes from the active template.
        $this->assertCount(2, $snapshot['steps']);
        $this->assertSame(2, $snapshot['template_version']);
    }

    public function test_distinct_materials_from_two_boms_are_all_kept(): void
    {
        $productType = ProductType::factory()->create();
        [$a, $b] = ProcessTemplate::factory()->count(2)
            ->sequence(['version' => 1], ['version' => 2])
            ->create(['product_type_id' => $productType->id]);
        $steel = Material::factory()->create();
        $paint = Material::factory()->create();
        BomItem::factory()->create(['process_template_id' => $a->id, 'material_id' => $steel->id, 'quantity_per_unit' => 2]);
        BomItem::factory()->create(['process_template_id' => $b->id, 'material_id' => $paint->id, 'quantity_per_unit' => 3]);

        $snapshot = $this->service->buildProcessSnapshot($productType->id, [$a->id, $b->id]);

        $this->assertCount(2, $snapshot['bom']);
        $this->assertSame([$a->id, $b->id], $snapshot['bom_template_ids']);
        $this->assertSame(2.0, $this->row($snapshot, $steel->id)['quantity_per_unit']);
        $this->assertSame(3.0, $this->row($snapshot, $paint->id)['quantity_per_unit']);
    }

    public function test_shared_material_same_scrap_is_summed_into_one_row(): void
    {
        $productType = ProductType::factory()->create();
        [$a, $b] = ProcessTemplate::factory()->count(2)
            ->sequence(['version' => 1], ['version' => 2])
            ->create(['product_type_id' => $productType->id]);
        $glue = Material::factory()->create();
        BomItem::factory()->create(['process_template_id' => $a->id, 'material_id' => $glue->id, 'quantity_per_unit' => 2, 'scrap_percentage' => 10]);
        BomItem::factory()->create(['process_template_id' => $b->id, 'material_id' => $glue->id, 'quantity_per_unit' => 3, 'scrap_percentage' => 10]);

        $snapshot = $this->service->buildProcessSnapshot($productType->id, [$a->id, $b->id]);

        $this->assertCount(1, $snapshot['bom']);
        $row = $this->row($snapshot, $glue->id);
        $this->assertSame(5.0, $row['quantity_per_unit']);
        $this->assertSame(10.0, $row['scrap_percentage']);
    }

    public function test_shared_material_differing_scrap_folds_into_effective_quantity(): void
    {
        $productType = ProductType::factory()->create();
        [$a, $b] = ProcessTemplate::factory()->count(2)
            ->sequence(['version' => 1], ['version' => 2])
            ->create(['product_type_id' => $productType->id]);
        $glue = Material::factory()->create();
        BomItem::factory()->create(['process_template_id' => $a->id, 'material_id' => $glue->id, 'quantity_per_unit' => 10, 'scrap_percentage' => 0]);
        BomItem::factory()->create(['process_template_id' => $b->id, 'material_id' => $glue->id, 'quantity_per_unit' => 10, 'scrap_percentage' => 50]);

        $snapshot = $this->service->buildProcessSnapshot($productType->id, [$a->id, $b->id]);

        $row = $this->row($snapshot, $glue->id);
        // 10*(1+0) + 10*(1+0.5) = 25, scrap folded to 0 so the total required stays exact.
        $this->assertSame(25.0, $row['quantity_per_unit']);
        $this->assertSame(0.0, $row['scrap_percentage']);
    }

    public function test_shared_material_collapses_to_earliest_consumption_timing(): void
    {
        $productType = ProductType::factory()->create();
        [$a, $b] = ProcessTemplate::factory()->count(2)
            ->sequence(['version' => 1], ['version' => 2])
            ->create(['product_type_id' => $productType->id]);
        $glue = Material::factory()->create();
        BomItem::factory()->create(['process_template_id' => $a->id, 'material_id' => $glue->id, 'quantity_per_unit' => 1, 'consumed_at' => 'end']);
        BomItem::factory()->create(['process_template_id' => $b->id, 'material_id' => $glue->id, 'quantity_per_unit' => 1, 'consumed_at' => 'start']);

        $snapshot = $this->service->buildProcessSnapshot($productType->id, [$a->id, $b->id]);

        $this->assertSame('start', $this->row($snapshot, $glue->id)['consumed_at']);
    }

    public function test_orphan_during_item_from_secondary_bom_degrades_to_start(): void
    {
        $productType = ProductType::factory()->create();
        // Primary BOM drives the flow: steps 1 and 2.
        $primary = ProcessTemplate::factory()->withSteps(2)->create(['product_type_id' => $productType->id, 'version' => 1]);
        $secondary = ProcessTemplate::factory()->create(['product_type_id' => $productType->id, 'version' => 2]);

        // A `during` item on the secondary BOM tied to step 5 — a step that isn't
        // in the primary flow, so it could never be allocated as-is.
        $orphanStep = TemplateStep::factory()->create(['process_template_id' => $secondary->id, 'step_number' => 5]);
        $material = Material::factory()->create();
        BomItem::factory()->create([
            'process_template_id' => $secondary->id,
            'material_id' => $material->id,
            'quantity_per_unit' => 1,
            'consumed_at' => 'during',
            'template_step_id' => $orphanStep->id,
        ]);

        $snapshot = $this->service->buildProcessSnapshot($productType->id, [$primary->id, $secondary->id]);

        // Degraded to `start` so the material is still consumed.
        $this->assertSame('start', $this->row($snapshot, $material->id)['consumed_at']);
    }

    public function test_during_item_on_primary_bom_keeps_its_timing(): void
    {
        $productType = ProductType::factory()->create();
        $primary = ProcessTemplate::factory()->withSteps(2)->create(['product_type_id' => $productType->id]);
        $step2 = $primary->steps()->where('step_number', 2)->firstOrFail();
        $material = Material::factory()->create();
        BomItem::factory()->create([
            'process_template_id' => $primary->id,
            'material_id' => $material->id,
            'quantity_per_unit' => 1,
            'consumed_at' => 'during',
            'template_step_id' => $step2->id,
        ]);

        $snapshot = $this->service->buildProcessSnapshot($productType->id, [$primary->id]);

        // Step 2 exists in the flow — timing is preserved.
        $row = $this->row($snapshot, $material->id);
        $this->assertSame('during', $row['consumed_at']);
        $this->assertSame(2, $row['step_number']);
    }

    public function test_shared_during_material_uses_the_earliest_valid_step(): void
    {
        $productType = ProductType::factory()->create();
        // Primary flow has steps 1, 2, 3.
        $primary = ProcessTemplate::factory()->withSteps(3)->create(['product_type_id' => $productType->id, 'version' => 1]);
        $secondary = ProcessTemplate::factory()->create(['product_type_id' => $productType->id, 'version' => 2]);

        $glue = Material::factory()->create();
        // Same material consumed `during` two different valid steps: step 3 (primary)
        // and step 2 (secondary). The merged row must pick the earlier step.
        $primStep3 = $primary->steps()->where('step_number', 3)->firstOrFail();
        BomItem::factory()->create([
            'process_template_id' => $primary->id, 'material_id' => $glue->id,
            'quantity_per_unit' => 1, 'consumed_at' => 'during', 'template_step_id' => $primStep3->id,
        ]);
        $secStep2 = TemplateStep::factory()->create(['process_template_id' => $secondary->id, 'step_number' => 2]);
        BomItem::factory()->create([
            'process_template_id' => $secondary->id, 'material_id' => $glue->id,
            'quantity_per_unit' => 1, 'consumed_at' => 'during', 'template_step_id' => $secStep2->id,
        ]);

        $snapshot = $this->service->buildProcessSnapshot($productType->id, [$primary->id, $secondary->id]);

        $row = $this->row($snapshot, $glue->id);
        $this->assertSame('during', $row['consumed_at']);
        // Step 2 (earliest) - and it exists in the primary flow, so it isn't degraded.
        $this->assertSame(2, $row['step_number']);
    }

    public function test_selection_order_is_preserved_and_deduplicated(): void
    {
        $productType = ProductType::factory()->create();
        [$a, $b] = ProcessTemplate::factory()->count(2)
            ->sequence(['version' => 1], ['version' => 2])
            ->create(['product_type_id' => $productType->id]);

        $snapshot = $this->service->buildProcessSnapshot($productType->id, [$b->id, $a->id, $b->id]);

        $this->assertSame([$b->id, $a->id], $snapshot['bom_template_ids']);
    }
}
