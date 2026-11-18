<?php

namespace Tests\Feature\Warehouse;

use App\Models\Batch;
use App\Models\BomItem;
use App\Models\Line;
use App\Models\Material;
use App\Models\MaterialAllocation;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\StockDocument;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\WorkOrder;
use App\Services\Material\MaterialAllocationService;
use App\Services\Warehouse\StockDocumentService;
use App\Services\Warehouse\WorkOrderStockDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The material release generated when a work order is completed must not take
 * off stock a second time what the shop floor already consumed.
 *
 * Allocation moves the plant-wide quantity and the lot, consumption moves the
 * location balance. Posting the release used to apply every line again, so a
 * lot emptied by production went negative and the warehouse total dropped by
 * the order's consumption twice.
 */
class ProductionMaterialIssueTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $raw;

    protected function setUp(): void
    {
        parent::setUp();

        $this->raw = Warehouse::factory()->rawMaterial()->isDefault()->create(['code' => 'RAW']);
    }

    /** A work order for 100 pieces, all produced, with one BOM line per material (1 per unit). */
    private function workOrderUsing(Material ...$materials): WorkOrder
    {
        $product = ProductType::factory()->create(['unit_of_measure' => 'pcs']);
        $template = ProcessTemplate::factory()->create(['product_type_id' => $product->id, 'is_active' => true]);

        foreach ($materials as $material) {
            BomItem::create([
                'process_template_id' => $template->id,
                'material_id' => $material->id,
                'quantity_per_unit' => 1,
                'scrap_percentage' => 0,
            ]);
        }

        return WorkOrder::factory()->create([
            'line_id' => Line::factory()->create(['warehouse_id' => $this->raw->id]),
            'product_type_id' => $product->id,
            'planned_qty' => 100,
            'produced_qty' => 100,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
        ]);
    }

    private function materialWithStock(float $qty): Material
    {
        $material = Material::factory()->create(['stock_quantity' => $qty, 'unit_of_measure' => 'pcs']);

        WarehouseStock::factory()->create([
            'warehouse_id' => $this->raw->id,
            'material_id' => $material->id,
            'material_lot_id' => null,
            'quantity' => $qty,
        ]);

        return $material;
    }

    /** The shop floor allocates and consumes the material for the order's batch. */
    private function consumeOnTheShopFloor(WorkOrder $workOrder, Material $material, float $qty): void
    {
        $allocation = MaterialAllocation::factory()->create([
            'material_id' => $material->id,
            'work_order_id' => $workOrder->id,
            'batch_id' => Batch::factory()->create(['work_order_id' => $workOrder->id])->id,
            'allocated_qty' => $qty,
        ]);

        app(MaterialAllocationService::class)->recordConsumption($allocation, $qty);
    }

    private function balance(Material $material): float
    {
        return (float) WarehouseStock::where([
            'warehouse_id' => $this->raw->id,
            'material_id' => $material->id,
            'material_lot_id' => null,
        ])->value('quantity');
    }

    private function release(WorkOrder $workOrder): StockDocument
    {
        return app(WorkOrderStockDocumentService::class)->generateMaterialIssue($workOrder)->load('lines');
    }

    private function documentMovements(StockDocument $document): int
    {
        return StockMovement::where('source_type', StockMovement::SOURCE_STOCK_DOCUMENT)
            ->where('source_id', $document->id)
            ->count();
    }

    public function test_posting_the_release_of_consumed_material_does_not_take_it_off_stock_again(): void
    {
        $bags = $this->materialWithStock(500);
        $workOrder = $this->workOrderUsing($bags);
        $this->consumeOnTheShopFloor($workOrder, $bags, 100);

        $afterProduction = $this->balance($bags);
        $globalAfterProduction = (float) $bags->fresh()->stock_quantity;
        $this->assertEquals(400.0, $afterProduction);

        $release = $this->release($workOrder);
        $this->assertTrue($release->lines->first()->booked_at_source);

        $posted = app(StockDocumentService::class)->post($release);

        $this->assertTrue($posted->isPosted());
        $this->assertEquals($afterProduction, $this->balance($bags));
        $this->assertEquals($globalAfterProduction, (float) $bags->fresh()->stock_quantity);
        $this->assertSame(0, $this->documentMovements($release));
    }

    public function test_a_release_for_material_nobody_allocated_still_moves_stock_when_posted(): void
    {
        $flour = $this->materialWithStock(500);
        $workOrder = $this->workOrderUsing($flour);

        $release = $this->release($workOrder);
        $this->assertFalse($release->lines->first()->booked_at_source);

        app(StockDocumentService::class)->post($release);

        $this->assertEquals(400.0, $this->balance($flour));
        $this->assertSame(1, $this->documentMovements($release));
    }

    public function test_only_the_lines_the_shop_floor_consumed_are_left_alone(): void
    {
        $bags = $this->materialWithStock(500);
        $film = $this->materialWithStock(500);
        $workOrder = $this->workOrderUsing($bags, $film);
        $this->consumeOnTheShopFloor($workOrder, $bags, 100);

        $release = $this->release($workOrder);
        $flags = $release->lines->mapWithKeys(fn ($line) => [$line->material_id => $line->booked_at_source]);
        $this->assertTrue($flags[$bags->id]);
        $this->assertFalse($flags[$film->id]);

        app(StockDocumentService::class)->post($release);

        $this->assertEquals(400.0, $this->balance($bags));
        $this->assertEquals(400.0, $this->balance($film));
    }

    public function test_cancelling_a_posted_release_of_consumed_material_gives_nothing_back(): void
    {
        $bags = $this->materialWithStock(500);
        $workOrder = $this->workOrderUsing($bags);
        $this->consumeOnTheShopFloor($workOrder, $bags, 100);

        $documents = app(StockDocumentService::class);
        $release = $documents->post($this->release($workOrder));
        $documents->cancel($release, null, 'test');

        // Giving material back is a consumption correction on the shop floor.
        $this->assertEquals(400.0, $this->balance($bags));
    }

    public function test_a_manual_issue_cannot_be_flagged_as_booked_at_source_by_mistake(): void
    {
        $bags = $this->materialWithStock(500);

        $issue = app(StockDocumentService::class)->createDraft([
            'type' => StockDocument::TYPE_MATERIAL_ISSUE,
            'lines' => [['material_id' => $bags->id, 'quantity' => 10]],
        ]);

        $this->assertFalse($issue->lines->first()->booked_at_source);
    }
}
