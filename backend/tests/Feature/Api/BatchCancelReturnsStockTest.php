<?php

namespace Tests\Feature\Api;

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

class BatchCancelReturnsStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancel_returns_allocated_stock(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $token = $admin->createToken('test')->plainTextToken;

        $type = MaterialType::create(['code' => 'RAW', 'name' => 'Raw']);
        $material = Material::create([
            'code' => 'M1',
            'name' => 'Material 1',
            'material_type_id' => $type->id,
            'unit_of_measure' => 'kg',
            'stock_quantity' => 500,
        ]);

        $productType = ProductType::factory()->create();
        $wo = WorkOrder::factory()->create([
            'product_type_id' => $productType->id,
            'process_snapshot' => [
                'bom' => [[
                    'material_id' => $material->id,
                    'material_code' => $material->code,
                    'material_name' => $material->name,
                    'unit_of_measure' => 'kg',
                    'quantity_per_unit' => 1.0,
                    'scrap_percentage' => 0,
                ]],
            ],
        ]);
        $batch = Batch::factory()->create([
            'work_order_id' => $wo->id,
            'target_qty' => 100,
            'produced_qty' => 0,
            'status' => Batch::STATUS_PENDING,
        ]);

        app(MaterialAllocationService::class)->allocateForBatch($batch, $admin);
        $this->assertEqualsWithDelta(400.0, (float) $material->fresh()->stock_quantity, 0.0001);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/batches/{$batch->id}/cancel");

        $response->assertStatus(200);
        $this->assertSame(Batch::STATUS_CANCELLED, $batch->fresh()->status);
        $this->assertEqualsWithDelta(500.0, (float) $material->fresh()->stock_quantity, 0.0001);
        $this->assertSame(
            MaterialAllocation::STATUS_RETURNED,
            MaterialAllocation::firstWhere('batch_id', $batch->id)->status,
        );
    }

    public function test_cancel_without_allocations_still_works(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $token = $admin->createToken('test')->plainTextToken;

        // WO with no BOM in snapshot.
        $wo = WorkOrder::factory()->create(['process_snapshot' => ['bom' => []]]);
        $batch = Batch::factory()->create([
            'work_order_id' => $wo->id,
            'target_qty' => 100,
            'produced_qty' => 0,
            'status' => Batch::STATUS_PENDING,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/batches/{$batch->id}/cancel");

        $response->assertStatus(200);
        $this->assertSame(Batch::STATUS_CANCELLED, $batch->fresh()->status);
    }
}
