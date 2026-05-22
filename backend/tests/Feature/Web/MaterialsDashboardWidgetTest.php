<?php

namespace Tests\Feature\Web;

use App\Models\DashboardWidget;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\MaterialType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaterialsDashboardWidgetTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private MaterialType $type;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        $this->type = MaterialType::create(['code' => 'RAW', 'name' => 'Raw']);
    }

    public function test_widget_seeded_by_migration_and_enabled_by_default(): void
    {
        $w = DashboardWidget::firstWhere('widget_id', 'materials_overview');
        $this->assertNotNull($w);
        $this->assertTrue($w->enabled);
        $this->assertSame('main', $w->zone);
    }

    public function test_widget_renders_when_enabled_and_has_data(): void
    {
        Material::create([
            'code' => 'M-LOW', 'name' => 'Low stock material',
            'material_type_id' => $this->type->id,
            'unit_of_measure' => 'kg',
            'stock_quantity' => 5,
            'min_stock_level' => 50,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Materials Overview');
        $response->assertSee('Low stock material');
    }

    public function test_widget_lists_lots_expiring_within_30d(): void
    {
        $mat = Material::create([
            'code' => 'M-EXP', 'name' => 'Expiring material',
            'material_type_id' => $this->type->id,
            'unit_of_measure' => 'l', 'stock_quantity' => 100,
        ]);
        MaterialLot::create([
            'material_id' => $mat->id,
            'lot_number' => 'LOT-EXP-1',
            'received_qty' => 50,
            'available_qty' => 50,
            'received_at' => now()->subDays(10),
            'expiry_date' => now()->addDays(15)->toDateString(),
            'status' => MaterialLot::STATUS_AVAILABLE,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Expiring 30d');
        $response->assertSee('LOT-EXP-1');
    }

    public function test_widget_hidden_when_disabled(): void
    {
        DashboardWidget::where('widget_id', 'materials_overview')->update(['enabled' => false]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Materials Overview');
    }

    public function test_quarantined_lots_count_shown(): void
    {
        $mat = Material::create([
            'code' => 'M-Q', 'name' => 'Quarantine material',
            'material_type_id' => $this->type->id,
            'unit_of_measure' => 'pcs', 'stock_quantity' => 0,
        ]);
        MaterialLot::create([
            'material_id' => $mat->id,
            'lot_number' => 'LOT-BAD',
            'received_qty' => 100, 'available_qty' => 0,
            'received_at' => now(),
            'status' => MaterialLot::STATUS_QUARANTINED,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $response->assertOk();
        $response->assertSee('1 quarantined');
    }
}
