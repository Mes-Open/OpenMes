<?php

namespace Tests\Feature\Api\V1;

use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\MaterialReclassification;
use App\Models\MaterialType;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reclassifying a lot's status (#99): released ↔ quarantine (no stock delta) and
 * → rejected (scraps remaining quantity out of stock). Every change writes an
 * audit row.
 */
class ReclassifyLotStatusTest extends TestCase
{
    use RefreshDatabase;

    private Material $material;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $type = MaterialType::create(['code' => 'RAW', 'name' => 'Raw']);
        $this->material = Material::create([
            'code' => 'M1', 'name' => 'Material 1', 'material_type_id' => $type->id,
            'unit_of_measure' => 'kg', 'stock_quantity' => 500,
        ]);
    }

    private function lot(string $status = MaterialLot::STATUS_RELEASED, float $available = 100): MaterialLot
    {
        return MaterialLot::factory()->create([
            'material_id' => $this->material->id, 'status' => $status,
            'quantity_received' => 100, 'quantity_available' => $available,
        ]);
    }

    private function submit(MaterialLot $lot, array $body, string $role = 'Supervisor')
    {
        $u = tap(User::factory()->create(), fn ($x) => $x->assignRole($role));

        return $this->withHeader('Authorization', 'Bearer '.$u->createToken('t')->plainTextToken)
            ->postJson("/api/v1/material-lots/{$lot->id}/reclassify-status", $body);
    }

    public function test_released_to_quarantine_holds_without_stock_delta(): void
    {
        $lot = $this->lot();

        $this->submit($lot, ['to_status' => MaterialLot::STATUS_QUARANTINE, 'reason' => 'Suspect batch'])
            ->assertOk();

        $lot->refresh();
        $this->assertSame(MaterialLot::STATUS_QUARANTINE, $lot->status);
        $this->assertNotNull($lot->held_at);
        $this->assertEqualsWithDelta(500.0, (float) $this->material->fresh()->stock_quantity, 0.0001);
        $this->assertSame(0, StockMovement::forMaterial($this->material->id)->count());

        $record = MaterialReclassification::firstWhere('type', MaterialReclassification::TYPE_STATUS);
        $this->assertSame(MaterialLot::STATUS_RELEASED, $record->from_status);
        $this->assertSame(MaterialLot::STATUS_QUARANTINE, $record->to_status);
    }

    public function test_quarantine_to_released(): void
    {
        $lot = $this->lot(MaterialLot::STATUS_QUARANTINE);

        $this->submit($lot, ['to_status' => MaterialLot::STATUS_RELEASED])->assertOk();

        $this->assertSame(MaterialLot::STATUS_RELEASED, $lot->fresh()->status);
        $this->assertSame(0, StockMovement::forMaterial($this->material->id)->count());
    }

    public function test_reject_scraps_remaining_quantity_from_stock(): void
    {
        $lot = $this->lot(MaterialLot::STATUS_RELEASED, 100);

        $this->submit($lot, ['to_status' => MaterialLot::STATUS_REJECTED, 'reason' => 'Failed inspection'])
            ->assertOk();

        $lot->refresh();
        $this->assertSame(MaterialLot::STATUS_REJECTED, $lot->status);
        $this->assertEqualsWithDelta(0.0, (float) $lot->quantity_available, 0.0001);
        // The 100 remaining left stock as scrap.
        $this->assertEqualsWithDelta(400.0, (float) $this->material->fresh()->stock_quantity, 0.0001);

        $scrap = StockMovement::forMaterial($this->material->id)
            ->where('movement_type', StockMovement::TYPE_SCRAP)->first();
        $this->assertEqualsWithDelta(-100.0, (float) $scrap->quantity, 0.0001);
        $this->assertSame(StockMovement::SOURCE_RECLASSIFICATION, $scrap->source_type);
    }

    public function test_rejecting_a_consumed_lot_is_rejected(): void
    {
        $lot = $this->lot(MaterialLot::STATUS_CONSUMED, 0);

        $this->submit($lot, ['to_status' => MaterialLot::STATUS_REJECTED, 'reason' => 'x'])
            ->assertStatus(422);
    }

    public function test_missing_reason_for_quarantine_is_rejected(): void
    {
        $lot = $this->lot();

        $this->submit($lot, ['to_status' => MaterialLot::STATUS_QUARANTINE])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }

    public function test_operator_cannot_change_lot_status(): void
    {
        $lot = $this->lot();

        $this->submit($lot, ['to_status' => MaterialLot::STATUS_QUARANTINE, 'reason' => 'x'], 'Operator')
            ->assertForbidden();
    }

    public function test_guest_cannot_change_lot_status(): void
    {
        $lot = $this->lot();

        $this->postJson("/api/v1/material-lots/{$lot->id}/reclassify-status", [
            'to_status' => MaterialLot::STATUS_QUARANTINE, 'reason' => 'x',
        ])->assertUnauthorized();
    }
}
