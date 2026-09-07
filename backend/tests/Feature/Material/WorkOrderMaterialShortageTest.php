<?php

namespace Tests\Feature\Material;

use App\Models\Material;
use App\Models\MaterialType;
use App\Models\WorkOrder;
use App\Services\Material\MaterialAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "This order cannot be built from stock" — the check behind the planner tile,
 * the operator's banner and the batch preview.
 *
 * It reads the order's own snapshot BOM, so a manufactured subassembly is
 * reported as itself rather than resolved into the parts it would be made from.
 * That case used to be invisible until an operator tried to start.
 */
class WorkOrderMaterialShortageTest extends TestCase
{
    use RefreshDatabase;

    private MaterialAllocationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MaterialAllocationService::class);
    }

    private function material(string $code, float $stock, bool $manufactured = false): Material
    {
        return Material::factory()->create([
            'code' => $code,
            'material_type_id' => MaterialType::factory()->create()->id,
            'unit_of_measure' => 'pcs',
            'is_manufactured' => $manufactured,
            'stock_quantity' => $stock,
            'reserved_quantity' => 0,
        ]);
    }

    /** A work order whose snapshot names the given components. */
    private function order(array $bom, float $plannedQty = 100): WorkOrder
    {
        return WorkOrder::factory()->create([
            'planned_qty' => $plannedQty,
            'status' => WorkOrder::STATUS_PENDING,
            'process_snapshot' => ['bom' => $bom],
        ]);
    }

    private function line(Material $material, float $perUnit, float $scrap = 0): array
    {
        return [
            'material_id' => $material->id,
            'material_code' => $material->code,
            'material_name' => $material->name,
            'unit_of_measure' => $material->unit_of_measure,
            'quantity_per_unit' => $perUnit,
            'scrap_percentage' => $scrap,
            'consumed_at' => 'start',
        ];
    }

    public function test_an_order_covered_by_stock_reports_no_shortage(): void
    {
        $part = $this->material('PART', 500);
        $order = $this->order([$this->line($part, 2)]);

        $this->assertSame([], $this->service->shortagesForWorkOrders(collect([$order])));
    }

    public function test_a_subassembly_that_runs_out_is_reported_by_name(): void
    {
        // 100 units × 1 pack, 2% scrap = 102 needed, 40 on the shelf.
        $pack = $this->material('PLEATPACK', 40, manufactured: true);
        $order = $this->order([$this->line($pack, 1, 2)]);

        $short = $this->service->shortagesForWorkOrders(collect([$order]))[$order->id] ?? null;

        $this->assertNotNull($short, 'The order should be flagged.');
        $this->assertCount(1, $short);
        $this->assertSame('PLEATPACK', $short[0]['material_code']);
        $this->assertSame(102.0, $short[0]['required_qty']);
        $this->assertSame(40.0, $short[0]['available_qty']);
        $this->assertSame(62.0, $short[0]['missing_qty']);
    }

    public function test_reserved_stock_does_not_count_as_available(): void
    {
        $part = $this->material('PART', 150);
        $part->update(['reserved_quantity' => 100]);

        $order = $this->order([$this->line($part, 1)]);

        $short = $this->service->shortagesForWorkOrders(collect([$order]))[$order->id] ?? null;

        // 150 on hand, 100 already spoken for → only 50 free against 100 needed.
        $this->assertNotNull($short);
        $this->assertSame(50.0, $short[0]['available_qty']);
        $this->assertSame(50.0, $short[0]['missing_qty']);
    }

    public function test_a_bom_line_whose_material_is_gone_is_flagged_not_skipped(): void
    {
        $order = $this->order([[
            'material_code' => 'DELETED-PART',
            'material_name' => 'Deleted part',
            'quantity_per_unit' => 1,
            'scrap_percentage' => 0,
            'consumed_at' => 'start',
        ]]);

        $short = $this->service->shortagesForWorkOrders(collect([$order]))[$order->id] ?? null;

        $this->assertNotNull($short);
        $this->assertFalse($short[0]['material_exists']);
    }

    public function test_orders_are_measured_independently_not_netted_against_each_other(): void
    {
        // Enough for either order alone, not for both — each is still "can run".
        $part = $this->material('PART', 100);
        $a = $this->order([$this->line($part, 1)], 100);
        $b = $this->order([$this->line($part, 1)], 100);

        $this->assertSame([], $this->service->shortagesForWorkOrders(collect([$a, $b])));
    }

    public function test_an_order_without_a_snapshot_bom_is_not_flagged(): void
    {
        $order = WorkOrder::factory()->create([
            'planned_qty' => 10,
            'status' => WorkOrder::STATUS_PENDING,
            'process_snapshot' => ['steps' => []],
        ]);

        $this->assertSame([], $this->service->shortagesForWorkOrders(collect([$order])));
    }
}
