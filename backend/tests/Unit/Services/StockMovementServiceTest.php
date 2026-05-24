<?php

namespace Tests\Unit\Services;

use App\Models\Material;
use App\Models\MaterialType;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Material\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMovementServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockMovementService $svc;

    private Material $material;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(StockMovementService::class);
        $this->user = User::factory()->create();
        $type = MaterialType::create(['code' => 'RAW', 'name' => 'Raw']);
        $this->material = Material::create([
            'code' => 'M', 'name' => 'M', 'material_type_id' => $type->id,
            'unit_of_measure' => 'kg', 'stock_quantity' => 100,
        ]);
    }

    public function test_negative_quantity_decrements_stock_and_records_balance(): void
    {
        $mv = $this->svc->record($this->material, StockMovement::TYPE_ALLOCATION, -30, $this->user);

        $this->assertEqualsWithDelta(70.0, (float) $this->material->fresh()->stock_quantity, 0.0001);
        $this->assertEqualsWithDelta(70.0, (float) $mv->balance_after, 0.0001);
        $this->assertEqualsWithDelta(-30.0, (float) $mv->quantity, 0.0001);
        $this->assertSame(StockMovement::TYPE_ALLOCATION, $mv->movement_type);
        $this->assertSame($this->user->id, $mv->performed_by);
    }

    public function test_positive_quantity_increments_stock(): void
    {
        $this->svc->record($this->material, StockMovement::TYPE_RECEIPT, 50, $this->user);

        $this->assertEqualsWithDelta(150.0, (float) $this->material->fresh()->stock_quantity, 0.0001);
    }

    public function test_movement_captures_source_link(): void
    {
        $mv = $this->svc->record(
            $this->material,
            StockMovement::TYPE_ALLOCATION,
            -10,
            $this->user,
            sourceType: StockMovement::SOURCE_BATCH,
            sourceId: 42,
            reason: 'Allocated to batch #42',
        );

        $this->assertSame('batch', $mv->source_type);
        $this->assertSame(42, $mv->source_id);
        $this->assertSame('Allocated to batch #42', $mv->reason);
    }

    public function test_ledger_for_material_returns_chronological_history(): void
    {
        $this->svc->record($this->material, StockMovement::TYPE_RECEIPT, 100, $this->user);
        $this->svc->record($this->material, StockMovement::TYPE_ALLOCATION, -40, $this->user);
        $this->svc->record($this->material, StockMovement::TYPE_RETURN, 10, $this->user);

        $ledger = StockMovement::forMaterial($this->material->id)->get();
        $this->assertCount(3, $ledger);
        // Newest first.
        $this->assertEqualsWithDelta(10.0, (float) $ledger[0]->quantity, 0.0001);
        $this->assertEqualsWithDelta(170.0, (float) $ledger[0]->balance_after, 0.0001); // 100→200→160→170
    }
}
