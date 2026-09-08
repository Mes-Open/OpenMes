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

    public function test_a_finished_goods_location_is_rejected(): void
    {
        $finishedGoods = Warehouse::factory()->finishedGoods()->create();

        // A line draws components, never finished product — the picker does not offer
        // this warehouse, and a hand-made request must not get past that either.
        $this->actingAs($this->admin)
            ->post('/admin/lines', [
                'code' => 'L-FG',
                'name' => 'Assembly FG',
                'warehouse_id' => $finishedGoods->id,
            ])
            ->assertSessionHasErrors('warehouse_id');

        $this->assertDatabaseMissing('lines', ['code' => 'L-FG']);
    }

    public function test_an_archived_location_is_rejected(): void
    {
        $archived = Warehouse::factory()->rawMaterial()->create(['is_active' => false]);

        $this->actingAs($this->admin)
            ->post('/admin/lines', [
                'code' => 'L-OFF',
                'name' => 'Assembly Off',
                'warehouse_id' => $archived->id,
            ])
            ->assertSessionHasErrors('warehouse_id');
    }

    /**
     * `Rule::exists` queries the table directly and so bypasses the model's global
     * TenantScope — without the tenant clause, one tenant could name another's
     * warehouse by id even though the picker never offers it.
     */
    public function test_another_tenants_location_is_rejected(): void
    {
        $ours = \App\Models\Tenant::factory()->create();
        $theirs = \App\Models\Tenant::factory()->create();

        $this->admin->update(['tenant_id' => $ours->id]);

        $foreign = Warehouse::factory()->rawMaterial()->create(['tenant_id' => $theirs->id]);
        $own = Warehouse::factory()->rawMaterial()->create(['tenant_id' => $ours->id]);

        $this->actingAs($this->admin)
            ->post('/admin/lines', [
                'code' => 'L-TEN',
                'name' => 'Assembly Tenant',
                'warehouse_id' => $foreign->id,
            ])
            ->assertSessionHasErrors('warehouse_id');

        $this->assertDatabaseMissing('lines', ['code' => 'L-TEN']);

        // The tenant's own store is still accepted.
        $this->actingAs($this->admin)
            ->post('/admin/lines', [
                'code' => 'L-TEN-OK',
                'name' => 'Assembly Tenant OK',
                'warehouse_id' => $own->id,
            ])
            ->assertRedirect();
    }

    /** The API shape of the same rejection: a JSON client gets 422, not a redirect. */
    public function test_the_rejection_is_a_422_for_a_json_client(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/admin/lines', [
                'code' => 'L-4',
                'name' => 'Assembly 4',
                'warehouse_id' => 999999,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('warehouse_id');
    }

    public function test_a_guest_cannot_point_a_line_at_a_location(): void
    {
        $warehouse = Warehouse::factory()->rawMaterial()->create();

        $this->post('/admin/lines', [
            'code' => 'L-GUEST',
            'name' => 'Assembly Guest',
            'warehouse_id' => $warehouse->id,
        ])->assertRedirect('/login');

        $this->assertDatabaseMissing('lines', ['code' => 'L-GUEST']);
    }

    public function test_an_operator_cannot_point_a_line_at_a_location(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');
        $warehouse = Warehouse::factory()->rawMaterial()->create();
        $line = Line::factory()->create(['warehouse_id' => null]);

        $this->actingAs($operator)
            ->put("/admin/lines/{$line->id}", [
                'code' => $line->code,
                'name' => $line->name,
                'warehouse_id' => $warehouse->id,
            ])
            ->assertForbidden();

        $this->assertNull($line->fresh()->warehouse_id);
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
