<?php

namespace Tests\Feature\Web;

use App\Models\DashboardWidget;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\MaterialType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Verifies that the admin dashboard widgets never leak data across tenants.
 *
 * The DashboardController uses raw Eloquent queries on Material / MaterialLot
 * which rely entirely on the HasTenant global scope for isolation. These tests
 * guard that contract end-to-end (HTTP -> rendered HTML).
 */
class DashboardTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $adminA;

    private User $adminB;

    private MaterialType $typeA;

    private MaterialType $typeB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->tenantA = Tenant::create(['name' => 'Tenant A']);
        $this->tenantB = Tenant::create(['name' => 'Tenant B']);

        $this->adminA = User::factory()->create(['tenant_id' => $this->tenantA->id]);
        $this->adminA->assignRole('Admin');

        $this->adminB = User::factory()->create(['tenant_id' => $this->tenantB->id]);
        $this->adminB->assignRole('Admin');

        // MaterialType doesn't use HasTenant but has a (code, tenant_id) unique
        // index — give each tenant its own type row.
        $this->typeA = MaterialType::create([
            'code' => 'RAW',
            'name' => 'Raw',
            'tenant_id' => $this->tenantA->id,
        ]);
        $this->typeB = MaterialType::create([
            'code' => 'RAW',
            'name' => 'Raw',
            'tenant_id' => $this->tenantB->id,
        ]);
    }

    public function test_admin_dashboard_only_shows_own_tenant_materials(): void
    {
        Material::create([
            'code' => 'MAT-A-LOW',
            'name' => 'Tenant A low-stock widget',
            'material_type_id' => $this->typeA->id,
            'unit_of_measure' => 'kg',
            'stock_quantity' => 0,
            'min_stock_level' => 10,
            'is_active' => true,
            'tenant_id' => $this->tenantA->id,
        ]);

        Material::create([
            'code' => 'MAT-B-LOW',
            'name' => 'Tenant B low-stock widget',
            'material_type_id' => $this->typeB->id,
            'unit_of_measure' => 'kg',
            'stock_quantity' => 0,
            'min_stock_level' => 10,
            'is_active' => true,
            'tenant_id' => $this->tenantB->id,
        ]);

        // Both tenants have exactly one low-stock material. Tenant A's
        // dashboard stats must only count its own — low_stock_count == 1, not 2.
        // The materialsStats aggregate is computed through the HasTenant global
        // scope, so a value of 2 here would mean cross-tenant leakage.
        $this->actingAs($this->adminA)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/Dashboard')
                ->where('materialsStats.low_stock_count', 1)
            );

        // Sanity: from Tenant B's side the same query also yields exactly 1,
        // proving each side sees only its own row (not the combined 2).
        $this->actingAs($this->adminB)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/Dashboard')
                ->where('materialsStats.low_stock_count', 1)
            );
    }

    public function test_admin_dashboard_lots_scoped_to_tenant(): void
    {
        $matA = Material::create([
            'code' => 'MAT-A-EXP',
            'name' => 'Tenant A expiring material',
            'material_type_id' => $this->typeA->id,
            'unit_of_measure' => 'l',
            'stock_quantity' => 100,
            'tenant_id' => $this->tenantA->id,
        ]);
        MaterialLot::create([
            'material_id' => $matA->id,
            'lot_number' => 'LOT-A-EXP',
            'unit_of_measure' => 'kg',
            'quantity_received' => 50,
            'quantity_available' => 50,
            'received_at' => now()->subDays(5),
            'expiry_date' => now()->addDays(10)->toDateString(),
            'status' => MaterialLot::STATUS_RELEASED,
            'tenant_id' => $this->tenantA->id,
        ]);

        $matB = Material::create([
            'code' => 'MAT-B-EXP',
            'name' => 'Tenant B expiring material',
            'material_type_id' => $this->typeB->id,
            'unit_of_measure' => 'l',
            'stock_quantity' => 100,
            'tenant_id' => $this->tenantB->id,
        ]);
        MaterialLot::create([
            'material_id' => $matB->id,
            'lot_number' => 'LOT-B-EXP',
            'unit_of_measure' => 'kg',
            'quantity_received' => 50,
            'quantity_available' => 50,
            'received_at' => now()->subDays(5),
            'expiry_date' => now()->addDays(10)->toDateString(),
            'status' => MaterialLot::STATUS_RELEASED,
            'tenant_id' => $this->tenantB->id,
        ]);

        // Each tenant owns exactly one released, soon-expiring lot. Tenant A's
        // lot aggregates (expiring within 30d / released lots total) must count
        // only its own lot — a value of 2 would mean MaterialLot's tenant scope
        // leaked Tenant B's row.
        $this->actingAs($this->adminA)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/Dashboard')
                ->where('materialsStats.expiring_count', 1)
                ->where('materialsStats.lots_total', 1)
            );

        // And Tenant B independently sees its single lot, never the sum.
        $this->actingAs($this->adminB)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/Dashboard')
                ->where('materialsStats.expiring_count', 1)
                ->where('materialsStats.lots_total', 1)
            );
    }

    public function test_admin_dashboard_reserved_total_only_counts_own_tenant(): void
    {
        Material::create([
            'code' => 'MAT-A-RSV',
            'name' => 'A reserved',
            'material_type_id' => $this->typeA->id,
            'unit_of_measure' => 'pcs',
            'stock_quantity' => 1000,
            'reserved_quantity' => 250,
            'tenant_id' => $this->tenantA->id,
        ]);

        Material::create([
            'code' => 'MAT-B-RSV',
            'name' => 'B reserved',
            'material_type_id' => $this->typeB->id,
            'unit_of_measure' => 'pcs',
            'stock_quantity' => 1000,
            'reserved_quantity' => 999,
            'tenant_id' => $this->tenantB->id,
        ]);

        // Make sure the widget is enabled (it is by default via the seeder
        // migration, but assert explicitly to keep the test self-contained).
        $widget = DashboardWidget::firstWhere('widget_id', 'materials_overview');
        $this->assertNotNull($widget);
        $this->assertTrue($widget->enabled);

        // Tenant A reserved 250, Tenant B reserved 999. Logged in as A, the
        // reserved_total stat must equal 250 — NOT the cross-tenant sum (1249).
        $this->actingAs($this->adminA)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/Dashboard')
                ->where(
                    'materialsStats.reserved_total',
                    fn ($total) => abs((float) $total - 250.0) < 0.001
                )
            );

        // Logged in as Tenant B should see 999, not 1249.
        $this->actingAs($this->adminB)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/Dashboard')
                ->where(
                    'materialsStats.reserved_total',
                    fn ($total) => abs((float) $total - 999.0) < 0.001
                )
            );
    }
}
