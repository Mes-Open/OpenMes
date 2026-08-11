<?php

namespace Tests\Feature\Api\V1;

use App\Models\Batch;
use App\Models\Material;
use App\Models\MaterialAllocation;
use App\Models\MaterialType;
use App\Models\ProductType;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Material\MaterialAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Returning unused material to stock mid-batch (#99), and the double-return
 * guard: a standalone return must shrink allocated_qty so the completion
 * reconciler never returns the same quantity twice.
 */
class ReturnAllocationTest extends TestCase
{
    use RefreshDatabase;

    private Material $material;

    private Batch $batch;

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

        $wo = WorkOrder::factory()->create([
            'product_type_id' => ProductType::factory()->create()->id,
            'process_snapshot' => ['bom' => [[
                'material_id' => $this->material->id, 'material_code' => 'M1', 'material_name' => 'Material 1',
                'unit_of_measure' => 'kg', 'quantity_per_unit' => 1.0, 'scrap_percentage' => 0,
            ]]],
        ]);
        $this->batch = Batch::factory()->create([
            'work_order_id' => $wo->id, 'target_qty' => 100, 'produced_qty' => 0, 'status' => Batch::STATUS_PENDING,
        ]);

        app(MaterialAllocationService::class)->allocateForBatch($this->batch, $this->admin());
        $this->allocation = MaterialAllocation::firstWhere('batch_id', $this->batch->id);
        // Allocation pulled 100 from 500 and reserved 100.
        $this->assertEqualsWithDelta(400.0, (float) $this->material->fresh()->stock_quantity, 0.0001);
        $this->assertEqualsWithDelta(100.0, (float) $this->material->fresh()->reserved_quantity, 0.0001);
    }

    private function admin(): User
    {
        return once(fn () => tap(User::factory()->create(), fn ($u) => $u->assignRole('Admin')));
    }

    private function submit(string $path, array $body = [])
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->admin()->createToken('t')->plainTextToken)
            ->postJson($path, $body);
    }

    public function test_partial_return_adjusts_stock_reserved_allocated_and_ledger(): void
    {
        $this->submit("/api/v1/material-allocations/{$this->allocation->id}/return", ['qty' => 30])
            ->assertOk();

        $this->assertEqualsWithDelta(430.0, (float) $this->material->fresh()->stock_quantity, 0.0001);
        $this->assertEqualsWithDelta(70.0, (float) $this->material->fresh()->reserved_quantity, 0.0001);

        $this->allocation->refresh();
        $this->assertEqualsWithDelta(70.0, (float) $this->allocation->allocated_qty, 0.0001);
        $this->assertEqualsWithDelta(30.0, (float) $this->allocation->returned_qty, 0.0001);
        $this->assertSame(MaterialAllocation::STATUS_ALLOCATED, $this->allocation->status);

        $returns = StockMovement::forMaterial($this->material->id)
            ->where('movement_type', StockMovement::TYPE_RETURN)->get();
        $this->assertCount(1, $returns);
        $this->assertEqualsWithDelta(30.0, (float) $returns->first()->quantity, 0.0001);
    }

    public function test_return_then_completion_does_not_double_return(): void
    {
        // Consume 50, then explicitly return the 50 leftover before completion.
        $svc = app(MaterialAllocationService::class);
        $svc->recordConsumption($this->allocation, 50);
        $this->submit("/api/v1/material-allocations/{$this->allocation->id}/return", ['qty' => 50])->assertOk();

        // After the return: stock 400→450, allocated 100→50, reserved 100→50.
        $this->assertEqualsWithDelta(450.0, (float) $this->material->fresh()->stock_quantity, 0.0001);

        // Completion must NOT return the 50 again (allocated is now 50 == consumed).
        $svc->consumeForBatch($this->batch);

        $this->assertEqualsWithDelta(450.0, (float) $this->material->fresh()->stock_quantity, 0.0001);
        $this->assertEqualsWithDelta(0.0, (float) $this->material->fresh()->reserved_quantity, 0.0001);

        // Exactly one return of 50 across both events — no double count, reserved never negative.
        $totalReturned = (float) StockMovement::forMaterial($this->material->id)
            ->where('movement_type', StockMovement::TYPE_RETURN)->sum('quantity');
        $this->assertEqualsWithDelta(50.0, $totalReturned, 0.0001);
    }

    public function test_full_return_leaves_no_leftover_at_completion(): void
    {
        $this->submit("/api/v1/material-allocations/{$this->allocation->id}/return", ['qty' => 100])->assertOk();
        $this->assertEqualsWithDelta(0.0, (float) $this->allocation->fresh()->allocated_qty, 0.0001);

        app(MaterialAllocationService::class)->consumeForBatch($this->batch);
        // Exactly one return movement (the explicit 100); completion adds none.
        $this->assertCount(1, StockMovement::forMaterial($this->material->id)
            ->where('movement_type', StockMovement::TYPE_RETURN)->get());
        $this->assertEqualsWithDelta(500.0, (float) $this->material->fresh()->stock_quantity, 0.0001);
    }

    public function test_returning_more_than_unconsumed_is_rejected(): void
    {
        $this->submit("/api/v1/material-allocations/{$this->allocation->id}/return", ['qty' => 150])
            ->assertStatus(422)
            ->assertJsonValidationErrors('qty');
        $this->assertEqualsWithDelta(400.0, (float) $this->material->fresh()->stock_quantity, 0.0001);
    }

    public function test_zero_quantity_is_rejected(): void
    {
        $this->submit("/api/v1/material-allocations/{$this->allocation->id}/return", ['qty' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors('qty');
    }

    public function test_guest_cannot_return(): void
    {
        $this->postJson("/api/v1/material-allocations/{$this->allocation->id}/return", ['qty' => 10])
            ->assertUnauthorized();
    }
}
