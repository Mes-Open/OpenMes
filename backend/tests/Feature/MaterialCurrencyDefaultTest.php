<?php

namespace Tests\Feature;

use App\Models\AdditionalCost;
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

    public function test_an_additional_cost_without_a_currency_takes_the_installation_currency(): void
    {
        CurrencyRegistry::save('EUR');

        $cost = AdditionalCost::factory()->create(['currency' => null]);

        $this->assertSame('EUR', $cost->fresh()->currency);
    }

    /**
     * Switching the installation currency in Settings → System relabels what was
     * recorded in the old one, so the report does not read PLN lines under an
     * EUR total. Prices in any other currency stay as recorded.
     */
    public function test_switching_the_currency_relabels_amounts_recorded_in_the_old_one(): void
    {
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $wo = WorkOrder::factory()->done()->create(['planned_qty' => 10, 'produced_qty' => 10]);
        $pln = Material::factory()->create(['unit_price' => 2, 'price_currency' => 'PLN']);
        $usd = Material::factory()->create(['unit_price' => 3, 'price_currency' => 'USD']);
        $allocation = MaterialAllocation::factory()->consumed(5, snapshotPrice: 2)->create([
            'work_order_id' => $wo->id,
            'material_id' => $pln->id,
        ]);
        $cost = AdditionalCost::factory()->create(['work_order_id' => $wo->id, 'amount' => 12, 'currency' => 'PLN']);

        $this->actingAs($admin)->post('/settings/system', [
            'production_period' => 'none',
            'workflow_mode' => 'status',
            'schedule_view_mode' => 'weekly',
            'schedule_shifts_per_day' => 1,
            'schedule_horizon_weeks' => 6,
            'realtime_mode' => 'polling',
            'production_tracking_mode' => 'per_operation',
            'production_qty_edit_policy' => 'none',
            'scanner_mode' => 'hid',
            'default_currency' => 'EUR',
        ])->assertSessionHasNoErrors();

        $this->assertSame('EUR', $pln->fresh()->price_currency);
        $this->assertSame('EUR', $allocation->fresh()->price_currency_snapshot);
        $this->assertSame('EUR', $cost->fresh()->currency);
        $this->assertSame('USD', $usd->fresh()->price_currency);
        // Relabel only — the numbers are not converted.
        $this->assertEquals(2, $pln->fresh()->unit_price);

        $breakdown = app(ProductionCostService::class)->breakdown($wo->fresh());
        $this->assertSame('EUR', $breakdown['currency']);
        $this->assertSame('EUR', $breakdown['materials']['items'][0]['currency']);
        $this->assertSame('EUR', $breakdown['additional']['items'][0]['currency']);
        $this->assertFalse($breakdown['mixed_currency']);
    }

    public function test_saving_the_same_currency_changes_nothing(): void
    {
        $usd = Material::factory()->create(['price_currency' => 'USD']);

        CurrencyRegistry::change('PLN');

        $this->assertSame('PLN', CurrencyRegistry::current());
        $this->assertSame('USD', $usd->fresh()->price_currency);
    }
}
