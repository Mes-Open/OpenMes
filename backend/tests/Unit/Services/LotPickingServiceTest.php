<?php

namespace Tests\Unit\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\AllocationLotPick;
use App\Models\Batch;
use App\Models\Material;
use App\Models\MaterialAllocation;
use App\Models\MaterialLot;
use App\Models\MaterialType;
use App\Models\ProductType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Material\LotPickingService;
use App\Services\Material\MaterialAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LotPickingServiceTest extends TestCase
{
    use RefreshDatabase;

    private LotPickingService $svc;

    private Material $material;

    private MaterialAllocation $allocation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(LotPickingService::class);

        $type = MaterialType::create(['code' => 'RAW', 'name' => 'Raw']);
        $this->material = Material::create([
            'code' => 'M', 'name' => 'M',
            'material_type_id' => $type->id,
            'unit_of_measure' => 'kg',
            'stock_quantity' => 1000,
        ]);

        $productType = ProductType::factory()->create();
        $wo = WorkOrder::factory()->create(['product_type_id' => $productType->id]);
        $batch = Batch::factory()->create([
            'work_order_id' => $wo->id,
            'target_qty' => 100,
            'produced_qty' => 0,
            'status' => Batch::STATUS_PENDING,
        ]);

        $this->allocation = MaterialAllocation::create([
            'batch_id' => $batch->id,
            'material_id' => $this->material->id,
            'work_order_id' => $wo->id,
            'allocated_qty' => 100,
            'expected_qty' => 100,
            'status' => MaterialAllocation::STATUS_ALLOCATED,
            'allocated_by' => User::factory()->create()->id,
            'allocated_at' => now(),
        ]);
    }

    private function makeLot(string $number, float $qty, ?string $expiry = null, ?string $received = null): MaterialLot
    {
        return MaterialLot::create([
            'material_id' => $this->material->id,
            'lot_number' => $number,
            'unit_of_measure' => 'kg',
            'quantity_received' => $qty,
            'quantity_available' => $qty,
            'received_at' => $received ?? now(),
            'expiry_date' => $expiry,
            'status' => MaterialLot::STATUS_RELEASED,
        ]);
    }

    public function test_fefo_picks_earliest_expiry_first(): void
    {
        $lotLate = $this->makeLot('LOT-LATE', 50, expiry: '2027-12-31');
        $lotEarly = $this->makeLot('LOT-EARLY', 50, expiry: '2026-08-01');
        $lotNoExpiry = $this->makeLot('LOT-NO-EXP', 50);

        $picks = $this->svc->pickForAllocation($this->allocation, $this->material, 80, 'fefo');

        $this->assertCount(2, $picks);
        $this->assertSame($lotEarly->id, $picks[0]->material_lot_id);
        $this->assertEqualsWithDelta(50.0, (float) $picks[0]->picked_qty, 0.0001);
        $this->assertSame($lotLate->id, $picks[1]->material_lot_id);
        $this->assertEqualsWithDelta(30.0, (float) $picks[1]->picked_qty, 0.0001);
        $this->assertEqualsWithDelta(50.0, (float) $lotNoExpiry->fresh()->quantity_available, 0.0001);
    }

    public function test_fifo_picks_oldest_received_first(): void
    {
        $newer = $this->makeLot('LOT-NEW', 60, received: now()->subDay()->toIso8601String());
        $older = $this->makeLot('LOT-OLD', 60, received: now()->subDays(10)->toIso8601String());

        $picks = $this->svc->pickForAllocation($this->allocation, $this->material, 70, 'fifo');

        $this->assertSame($older->id, $picks[0]->material_lot_id);
        $this->assertEqualsWithDelta(60.0, (float) $picks[0]->picked_qty, 0.0001);
        $this->assertSame($newer->id, $picks[1]->material_lot_id);
        $this->assertEqualsWithDelta(10.0, (float) $picks[1]->picked_qty, 0.0001);
    }

    public function test_depletes_lot_when_fully_picked(): void
    {
        $lot = $this->makeLot('LOT-A', 100);

        $this->svc->pickForAllocation($this->allocation, $this->material, 100, 'fefo');

        $this->assertEqualsWithDelta(0.0, (float) $lot->fresh()->quantity_available, 0.0001);
        $this->assertSame(MaterialLot::STATUS_CONSUMED, $lot->fresh()->status);
    }

    public function test_skips_quarantined_lots(): void
    {
        $lot = $this->makeLot('LOT-A', 100);
        $lot->update(['status' => MaterialLot::STATUS_QUARANTINE]);
        $available = $this->makeLot('LOT-B', 100);

        $picks = $this->svc->pickForAllocation($this->allocation, $this->material, 50, 'fefo');

        $this->assertCount(1, $picks);
        $this->assertSame($available->id, $picks[0]->material_lot_id);
    }

    public function test_throws_when_total_lot_qty_insufficient(): void
    {
        $this->makeLot('LOT-A', 20);
        $this->makeLot('LOT-B', 30);

        $this->expectException(InsufficientStockException::class);
        $this->svc->pickForAllocation($this->allocation, $this->material, 100, 'fefo');
    }

    public function test_return_picks_restores_lot_availability_and_status(): void
    {
        $lot = $this->makeLot('LOT-A', 100);
        $this->svc->pickForAllocation($this->allocation, $this->material, 100, 'fefo');
        $this->assertSame(MaterialLot::STATUS_CONSUMED, $lot->fresh()->status);

        $this->svc->returnPicksForAllocation($this->allocation);

        $this->assertEqualsWithDelta(100.0, (float) $lot->fresh()->quantity_available, 0.0001);
        $this->assertSame(MaterialLot::STATUS_RELEASED, $lot->fresh()->status);
        $this->assertSame(0, AllocationLotPick::count());
    }

    public function test_setting_toggles_lot_tracking_for_allocator(): void
    {
        $this->makeLot('LOT-A', 200);
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'lot_tracking_enabled'],
            ['value' => json_encode(true)],
        );

        $wo = WorkOrder::factory()->create([
            'product_type_id' => ProductType::factory()->create()->id,
            'process_snapshot' => [
                'bom' => [[
                    'material_id' => $this->material->id,
                    'material_code' => $this->material->code,
                    'unit_of_measure' => 'kg',
                    'quantity_per_unit' => 1.0,
                    'scrap_percentage' => 0,
                ]],
            ],
        ]);
        $batch = Batch::factory()->create([
            'work_order_id' => $wo->id,
            'target_qty' => 60,
            'produced_qty' => 0,
            'status' => Batch::STATUS_PENDING,
        ]);

        app(MaterialAllocationService::class)->allocateForBatch($batch, User::factory()->create());

        $this->assertSame(1, AllocationLotPick::count());
        $this->assertEqualsWithDelta(60.0, (float) AllocationLotPick::first()->picked_qty, 0.0001);
    }
}
