<?php

namespace Tests\Feature\Warehouse;

use App\Models\Line;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Assigning a line the stock location its consumption comes off.
 */
class LineStockLocationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    /** The picker must offer material-holding locations, and only those. */
    public function test_the_line_form_offers_raw_material_locations(): void
    {
        $raw = Warehouse::factory()->rawMaterial()->create(['code' => 'RAW-1', 'name' => 'Raw store']);
        $mixed = Warehouse::factory()->create(['code' => 'MIX-1', 'name' => 'Mixed store', 'kind' => Warehouse::KIND_MIXED]);
        Warehouse::factory()->create(['code' => 'FG-1', 'name' => 'Finished goods', 'kind' => Warehouse::KIND_FINISHED_GOODS]);

        $this->actingAs($this->admin)
            ->get('/admin/lines/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/lines/Create')
                ->has('warehouses', 2)
                ->etc()
            );

        $codes = collect($this->actingAs($this->admin)->get('/admin/lines/create')->inertiaProps()['warehouses'] ?? [])
            ->pluck('id')
            ->all();

        $this->assertEqualsCanonicalizing([$raw->id, $mixed->id], $codes);
    }

    public function test_a_line_can_be_created_with_a_stock_location(): void
    {
        $warehouse = Warehouse::factory()->rawMaterial()->create();

        $this->actingAs($this->admin)
            ->post('/admin/lines', [
                'code' => 'L-1',
                'name' => 'Assembly 1',
                'warehouse_id' => $warehouse->id,
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertSame($warehouse->id, Line::where('code', 'L-1')->value('warehouse_id'));
    }

    public function test_a_line_can_be_repointed_at_another_location(): void
    {
        $first = Warehouse::factory()->rawMaterial()->create();
        $second = Warehouse::factory()->rawMaterial()->create();
        $line = Line::factory()->create(['warehouse_id' => $first->id]);

        $this->actingAs($this->admin)
            ->put("/admin/lines/{$line->id}", [
                'code' => $line->code,
                'name' => $line->name,
                'warehouse_id' => $second->id,
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertSame($second->id, $line->fresh()->warehouse_id);
    }

    public function test_an_unknown_location_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/lines', [
                'code' => 'L-2',
                'name' => 'Assembly 2',
                'warehouse_id' => 999999,
            ])
            ->assertSessionHasErrors('warehouse_id');
    }

    /** Stock location stays optional — a plant that doesn't track it is unaffected. */
    public function test_a_line_without_a_stock_location_is_still_valid(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/lines', ['code' => 'L-3', 'name' => 'Assembly 3'])
            ->assertRedirect();

        $this->assertDatabaseHas('lines', ['code' => 'L-3', 'warehouse_id' => null]);
    }
}
