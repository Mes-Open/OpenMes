<?php

namespace Tests\Unit\Services;

use App\Models\Batch;
use App\Models\Material;
use App\Models\Pallet;
use App\Models\StockMovement;
use App\Models\WorkOrder;
use App\Services\Production\PalletBackflushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalletBackflushServiceTest extends TestCase
{
    use RefreshDatabase;

    private PalletBackflushService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PalletBackflushService::class);
    }

    /** Work order whose BOM snapshot consumes $material at qty_per_unit + scrap. */
    private function palletForBom(Material $material, float $perUnit, float $scrapPct): Pallet
    {
        $workOrder = WorkOrder::factory()->create([
            'process_snapshot' => [
                'bom' => [
                    ['material_id' => $material->id, 'quantity_per_unit' => $perUnit, 'scrap_percentage' => $scrapPct],
                ],
            ],
        ]);

        return Pallet::factory()->create(['work_order_id' => $workOrder->id]);
    }

    public function test_backflush_deducts_computed_consumption_and_links_to_pallet(): void
    {
        $material = Material::factory()->create(['stock_quantity' => 1000]);
        $pallet = $this->palletForBom($material, 2.0, 5.0);

        // 100 * 2.0 * (1 + 5%) = 210
        $movements = $this->service->backflush($pallet, 100);

        $this->assertCount(1, $movements);
        $this->assertEqualsWithDelta(790.0, (float) $material->fresh()->stock_quantity, 0.0001);

        $movement = $movements->first();
        $this->assertSame(StockMovement::TYPE_CONSUME, $movement->movement_type);
        $this->assertEqualsWithDelta(-210.0, (float) $movement->quantity, 0.0001);
        $this->assertSame(StockMovement::SOURCE_PALLET, $movement->source_type);
        $this->assertSame($pallet->id, $movement->source_id);

        // The movement is reachable from the pallet (linked + auditable).
        $this->assertTrue($pallet->stockMovements()->whereKey($movement->id)->exists());
    }

    public function test_backflush_for_pallet_books_a_batch_only_once(): void
    {
        // A batch split across several pallets (no explicit produced_qty, as the
        // packaging station sends none) must consume its BOM once, not per pallet.
        $material = Material::factory()->create(['stock_quantity' => 1000]);
        $wo = WorkOrder::factory()->create([
            'process_snapshot' => ['bom' => [
                ['material_id' => $material->id, 'quantity_per_unit' => 2.0, 'scrap_percentage' => 0],
            ]],
        ]);
        $batch = Batch::factory()->create(['work_order_id' => $wo->id, 'produced_qty' => 100]);
        $p1 = Pallet::factory()->create(['work_order_id' => $wo->id, 'batch_id' => $batch->id]);
        $p2 = Pallet::factory()->create(['work_order_id' => $wo->id, 'batch_id' => $batch->id]);

        $this->service->backflushForPallet($p1, null);
        $this->service->backflushForPallet($p2, null);

        // 100 * 2.0 deducted exactly once.
        $this->assertEqualsWithDelta(800.0, (float) $material->fresh()->stock_quantity, 0.0001);
        $this->assertSame(1, StockMovement::where('source_type', StockMovement::SOURCE_PALLET)->count());
    }

    public function test_pallet_with_no_bom_consumes_nothing(): void
    {
        $material = Material::factory()->create(['stock_quantity' => 500]);
        $workOrder = WorkOrder::factory()->create(['process_snapshot' => ['bom' => []]]);
        $pallet = Pallet::factory()->create(['work_order_id' => $workOrder->id]);

        $movements = $this->service->backflush($pallet, 100);

        $this->assertCount(0, $movements);
        $this->assertEqualsWithDelta(500.0, (float) $material->fresh()->stock_quantity, 0.0001);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_zero_quantity_consumes_nothing(): void
    {
        $material = Material::factory()->create(['stock_quantity' => 500]);
        $pallet = $this->palletForBom($material, 2.0, 0.0);

        $this->assertCount(0, $this->service->backflush($pallet, 0));
        $this->assertEqualsWithDelta(500.0, (float) $material->fresh()->stock_quantity, 0.0001);
    }

    public function test_resolve_quantity_prefers_explicit_then_batch(): void
    {
        $wo = WorkOrder::factory()->create();
        $batch = Batch::factory()->create(['work_order_id' => $wo->id, 'produced_qty' => 42, 'target_qty' => 99]);
        $pallet = Pallet::factory()->create(['work_order_id' => $wo->id, 'batch_id' => $batch->id]);

        $this->assertSame(7.0, $this->service->resolveQuantity($pallet, 7.0)); // explicit wins
        $this->assertSame(42.0, $this->service->resolveQuantity($pallet, null)); // batch produced_qty
    }

    public function test_resolve_quantity_falls_back_to_target_then_zero(): void
    {
        $wo = WorkOrder::factory()->create();
        $batch = Batch::factory()->create(['work_order_id' => $wo->id, 'produced_qty' => 0, 'target_qty' => 30]);
        $pallet = Pallet::factory()->create(['work_order_id' => $wo->id, 'batch_id' => $batch->id]);
        $this->assertSame(30.0, $this->service->resolveQuantity($pallet, null));

        $bare = Pallet::factory()->create(['work_order_id' => $wo->id, 'batch_id' => null]);
        $this->assertSame(0.0, $this->service->resolveQuantity($bare, null));
    }
}
