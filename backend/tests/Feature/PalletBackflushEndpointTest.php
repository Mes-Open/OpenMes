<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\Pallet;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Milestone backflush at the packaging station: creating a pallet declares and
 * deducts the BOM consumption for the produced quantity when the configurable
 * trigger is enabled, and is a no-op when it is off (the default).
 */
class PalletBackflushEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    private function enableBackflush(): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'backflush_on_pallet_creation'],
            ['value' => json_encode(true)],
        );
    }

    private function workOrderConsuming(Material $material): WorkOrder
    {
        return WorkOrder::factory()->create([
            'process_snapshot' => [
                'bom' => [
                    ['material_id' => $material->id, 'quantity_per_unit' => 2.0, 'scrap_percentage' => 5.0],
                ],
            ],
        ]);
    }

    public function test_creating_a_pallet_backflushes_when_enabled(): void
    {
        $this->enableBackflush();
        $material = Material::factory()->create(['stock_quantity' => 1000]);
        $wo = $this->workOrderConsuming($material);

        $this->actingAs($this->admin)
            ->postJson(route('packaging.pallets.create'), ['work_order_id' => $wo->id, 'produced_qty' => 100])
            ->assertCreated();

        // 100 * 2.0 * 1.05 = 210 deducted.
        $this->assertEqualsWithDelta(790.0, (float) $material->fresh()->stock_quantity, 0.0001);

        $pallet = Pallet::where('work_order_id', $wo->id)->firstOrFail();
        $this->assertDatabaseHas('stock_movements', [
            'source_type' => StockMovement::SOURCE_PALLET,
            'source_id' => $pallet->id,
            'movement_type' => StockMovement::TYPE_CONSUME,
        ]);
        $this->assertTrue($pallet->stockMovements()->exists());
    }

    public function test_creating_a_pallet_does_not_backflush_when_disabled(): void
    {
        // Setting left at its default (off).
        $material = Material::factory()->create(['stock_quantity' => 1000]);
        $wo = $this->workOrderConsuming($material);

        $this->actingAs($this->admin)
            ->postJson(route('packaging.pallets.create'), ['work_order_id' => $wo->id, 'produced_qty' => 100])
            ->assertCreated();

        $this->assertEqualsWithDelta(1000.0, (float) $material->fresh()->stock_quantity, 0.0001);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_pallet_with_no_bom_creates_no_consumption(): void
    {
        $this->enableBackflush();
        $wo = WorkOrder::factory()->create(['process_snapshot' => ['bom' => []]]);

        $this->actingAs($this->admin)
            ->postJson(route('packaging.pallets.create'), ['work_order_id' => $wo->id, 'produced_qty' => 100])
            ->assertCreated();

        $this->assertSame(0, StockMovement::count());
    }
}
