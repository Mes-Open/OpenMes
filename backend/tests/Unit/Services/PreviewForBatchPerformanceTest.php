<?php

namespace Tests\Unit\Services;

use App\Models\Batch;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\ProductType;
use App\Models\WorkOrder;
use App\Services\Material\MaterialAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PreviewForBatchPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_uses_bulk_load_not_n_plus_1(): void
    {
        $svc = app(MaterialAllocationService::class);

        $type = MaterialType::create(['code' => 'RAW', 'name' => 'Raw']);

        $bom = [];
        for ($i = 0; $i < 5; $i++) {
            $material = Material::create([
                'code' => 'MAT-PERF-'.$i,
                'name' => 'Mat '.$i,
                'material_type_id' => $type->id,
                'unit_of_measure' => 'kg',
                'stock_quantity' => 1000,
            ]);

            $bom[] = [
                'material_id' => $material->id,
                'material_code' => $material->code,
                'material_name' => $material->name,
                'unit_of_measure' => 'kg',
                'quantity_per_unit' => 1.0,
                'scrap_percentage' => 0,
            ];
        }

        $productType = ProductType::factory()->create();
        $workOrder = WorkOrder::factory()->create([
            'product_type_id' => $productType->id,
            'process_snapshot' => ['bom' => $bom],
        ]);
        $batch = Batch::factory()->create([
            'work_order_id' => $workOrder->id,
            'target_qty' => 10,
            'produced_qty' => 0,
            'status' => Batch::STATUS_PENDING,
        ]);

        // Re-fetch so workOrder relation is not yet eager-loaded — we
        // want any work-order query to land in the query log too.
        $batch = Batch::find($batch->id);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $preview = $svc->previewForBatch($batch);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(5, $preview, 'Preview should contain one row per BOM item.');
        $this->assertLessThanOrEqual(
            3,
            count($queries),
            'previewForBatch must bulk-load materials (max 3 queries: workOrder + materials by id + materials by code). Got: '
                .count($queries)."\n".collect($queries)->pluck('query')->implode("\n")
        );

        // Sanity: every preview row should resolve a material.
        foreach ($preview as $row) {
            $this->assertTrue($row['material_exists'], 'Bulk-loaded material should be present in preview.');
        }
    }
}
