<?php

namespace Tests\Feature\Web\Admin;

use App\Models\StockDocument;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WarehouseControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Admin', 'web');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    public function test_admin_can_list_warehouses(): void
    {
        Warehouse::factory()->create(['code' => 'RAW-1']);

        // Rows live-sync via the `warehouses` shape rather than being
        // server-rendered, so we only assert the page loads for an admin.
        $this->actingAs($this->admin)
            ->get(route('admin.warehouses.index'))
            ->assertOk();
    }

    public function test_admin_can_create_a_warehouse(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.warehouses.store'), [
            'code' => 'RAW-1',
            'name' => 'Raw material store',
            'kind' => Warehouse::KIND_RAW_MATERIAL,
            'erp_code' => '0100',
            'is_default' => '1',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.warehouses.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('warehouses', [
            'code' => 'RAW-1',
            'kind' => Warehouse::KIND_RAW_MATERIAL,
            'erp_code' => '0100',
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    public function test_kind_must_be_a_known_value(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.warehouses.store'), [
                'code' => 'X-1',
                'name' => 'Nonsense',
                'kind' => 'cupboard',
            ])
            ->assertSessionHasErrors('kind');
    }

    public function test_code_must_be_unique_among_live_warehouses(): void
    {
        Warehouse::factory()->create(['code' => 'RAW-1']);

        $this->actingAs($this->admin)
            ->post(route('admin.warehouses.store'), ['code' => 'RAW-1', 'name' => 'Duplicate'])
            ->assertSessionHasErrors('code');
    }

    public function test_a_soft_deleted_warehouse_frees_its_code(): void
    {
        Warehouse::factory()->create(['code' => 'RAW-1'])->delete();

        $this->actingAs($this->admin)
            ->post(route('admin.warehouses.store'), ['code' => 'RAW-1', 'name' => 'Reused code'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Warehouse::where('code', 'RAW-1')->count());
    }

    public function test_making_a_warehouse_default_clears_the_previous_one_of_that_kind(): void
    {
        $first = Warehouse::factory()->rawMaterial()->isDefault()->create();
        $second = Warehouse::factory()->rawMaterial()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.warehouses.set-default', $second))
            ->assertRedirect(route('admin.warehouses.index'));

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
    }

    public function test_admin_can_update_and_toggle_a_warehouse(): void
    {
        $warehouse = Warehouse::factory()->create(['code' => 'RAW-1', 'name' => 'Old name']);

        $this->actingAs($this->admin)
            ->put(route('admin.warehouses.update', $warehouse), [
                'code' => 'RAW-1',
                'name' => 'New name',
                'kind' => Warehouse::KIND_MIXED,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.warehouses.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('New name', $warehouse->fresh()->name);

        $this->actingAs($this->admin)->post(route('admin.warehouses.toggle-active', $warehouse));

        $this->assertFalse($warehouse->fresh()->is_active);
    }

    public function test_deleting_a_warehouse_soft_deletes_it(): void
    {
        $warehouse = Warehouse::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.warehouses.destroy', $warehouse))
            ->assertRedirect(route('admin.warehouses.index'));

        $this->assertSoftDeleted('warehouses', ['id' => $warehouse->id]);
    }

    public function test_a_warehouse_holding_stock_cannot_be_deleted(): void
    {
        $warehouse = Warehouse::factory()->create();
        WarehouseStock::factory()->create(['warehouse_id' => $warehouse->id, 'quantity' => 5]);

        $this->actingAs($this->admin)
            ->delete(route('admin.warehouses.destroy', $warehouse))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted('warehouses', ['id' => $warehouse->id]);
    }

    public function test_a_warehouse_with_documents_cannot_be_deleted(): void
    {
        $warehouse = Warehouse::factory()->rawMaterial()->create();
        StockDocument::factory()->create(['warehouse_id' => $warehouse->id]);

        $this->actingAs($this->admin)
            ->delete(route('admin.warehouses.destroy', $warehouse))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted('warehouses', ['id' => $warehouse->id]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('admin.warehouses.index'))->assertRedirect(route('login'));
        $this->post(route('admin.warehouses.store'), ['code' => 'X', 'name' => 'X'])
            ->assertRedirect(route('login'));
    }

    public function test_a_non_admin_cannot_reach_warehouse_admin(): void
    {
        Role::findOrCreate('Operator', 'web');
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator)
            ->get(route('admin.warehouses.index'))
            ->assertForbidden();
    }

    public function test_stock_overview_loads(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.warehouse-stock.index'))
            ->assertOk();
    }
}
