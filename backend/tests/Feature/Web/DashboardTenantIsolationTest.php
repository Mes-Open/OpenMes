<?php

namespace Tests\Feature\Web;

use App\Models\DashboardWidget;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\MaterialType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $response = $this->actingAs($this->adminA)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Tenant A low-stock widget');
        $response->assertDontSee('Tenant B low-stock widget');
        $response->assertDontSee('MAT-B-LOW');
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
            'received_qty' => 50,
            'available_qty' => 50,
            'received_at' => now()->subDays(5),
            'expiry_date' => now()->addDays(10)->toDateString(),
            'status' => MaterialLot::STATUS_AVAILABLE,
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
            'received_qty' => 50,
            'available_qty' => 50,
            'received_at' => now()->subDays(5),
            'expiry_date' => now()->addDays(10)->toDateString(),
            'status' => MaterialLot::STATUS_AVAILABLE,
            'tenant_id' => $this->tenantB->id,
        ]);

        $response = $this->actingAs($this->adminA)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('LOT-A-EXP');
        $response->assertDontSee('LOT-B-EXP');
        // Material name from foreign tenant must also not appear via the
        // eager-loaded relation.
        $response->assertDontSee('Tenant B expiring material');
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

        $response = $this->actingAs($this->adminA)->get(route('admin.dashboard'));
        $response->assertOk();

        $materialsStats = $response->viewData('materialsStats');
        $this->assertIsArray($materialsStats);
        $this->assertEqualsWithDelta(
            250.0,
            (float) $materialsStats['reserved_total'],
            0.001,
            'Reserved total must only sum the current tenant\'s reserved_quantity'
        );

        // Logged in as Tenant B should see 999, not 1249.
        $response = $this->actingAs($this->adminB)->get(route('admin.dashboard'));
        $response->assertOk();
        $materialsStats = $response->viewData('materialsStats');
        $this->assertEqualsWithDelta(999.0, (float) $materialsStats['reserved_total'], 0.001);
    }
}
