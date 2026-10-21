<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\MaterialAllocation;
use App\Models\MaterialType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Production\ProductionCostService;
use App\Support\CurrencyRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * One currency per installation: a material price nobody labelled is in the
 * installation currency; a price recorded in another currency stays as recorded
 * (no FX conversion) and the cost report flags it.
 */
class MaterialCurrencyDefaultTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CurrencyRegistry::forget();
    }

    public function test_a_material_created_in_the_ui_takes_the_installation_currency(): void
    {
        CurrencyRegistry::save('EUR');
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $type = MaterialType::create(['code' => 'raw_material', 'name' => 'Raw Material']);

        $this->actingAs($admin)->post(route('admin.materials.store'), [
            'code' => 'STEEL-1',
            'name' => 'Steel sheet',
            'material_type_id' => $type->id,
            'unit_price' => 3.5,
        ])->assertRedirect();

        $this->assertSame('EUR', Material::where('code', 'STEEL-1')->value('price_currency'));
    }

    public function test_an_explicit_currency_is_kept(): void
    {
        CurrencyRegistry::save('EUR');

        $material = Material::factory()->create(['price_currency' => 'USD']);

        $this->assertSame('USD', $material->fresh()->price_currency);
    }

    public function test_a_line_recorded_in_another_currency_keeps_it_and_is_flagged(): void
    {
        CurrencyRegistry::save('EUR');
        $wo = WorkOrder::factory()->done()->create(['planned_qty' => 10, 'produced_qty' => 10]);
        $material = Material::factory()->create(['unit_price' => 2, 'price_currency' => 'PLN']);
        MaterialAllocation::factory()->consumed(5, snapshotPrice: 2)->create([
            'work_order_id' => $wo->id,
            'material_id' => $material->id,
        ]);

        $breakdown = app(ProductionCostService::class)->breakdown($wo->fresh());

        $this->assertSame('EUR', $breakdown['currency']);
        $this->assertSame('PLN', $breakdown['materials']['items'][0]['currency']);
        $this->assertTrue($breakdown['mixed_currency']);
    }
}
