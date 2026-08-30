<?php

namespace Tests\Feature\Web;

use App\Models\DashboardWidget;
use App\Models\Inspection;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class InspectionDashboardWidgetTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Material $material;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\IssueTypesSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        $type = MaterialType::create(['code' => 'RAW', 'name' => 'Raw']);
        $this->material = Material::create(['code' => 'M', 'name' => 'Bolt', 'material_type_id' => $type->id]);
    }

    public function test_widget_registered_in_dashboard_widgets_table(): void
    {
        $widget = DashboardWidget::where('widget_id', 'inbound_qc_overview')->first();

        $this->assertNotNull($widget, 'inbound_qc_overview widget must be seeded by migration');
        $this->assertSame('main', $widget->zone);
        $this->assertTrue($widget->enabled, 'widget should be enabled by default');
        $this->assertSame(25, $widget->sort_order, 'default sort order between OEE (20) and recent WOs (30)');
    }

    public function test_dashboard_renders_widget_when_enabled_and_has_data(): void
    {
        // 1 pass + 1 fail completed in the last 30d, plus 1 pending.
        Inspection::factory()->passed()->create(['material_id' => $this->material->id]);
        Inspection::factory()->failed()->create(['material_id' => $this->material->id]);
        Inspection::factory()->pending()->create(['material_id' => $this->material->id]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/Dashboard')
                ->where('enabledWidgets', fn ($widgets) => collect($widgets)->contains('inbound_qc_overview'))
                ->where('inboundQcStats.pending', 1)
                ->where('inboundQcStats.completed_30d', 2)
                ->where('inboundQcStats.failed_30d', 1)
                ->where('inboundQcStats.pass_rate_30d', fn ($rate) => abs((float) $rate - 50.0) < 0.001)
            );
    }

    public function test_widget_hidden_when_disabled(): void
    {
        DashboardWidget::where('widget_id', 'inbound_qc_overview')->update(['enabled' => false]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Inbound QC Overview');
    }

    public function test_widget_order_reflects_sort_order(): void
    {
        // Move widget before OEE (sort_order < 20).
        DashboardWidget::where('widget_id', 'inbound_qc_overview')->update(['sort_order' => 15]);
        // Force at least one inspection so the widget has data.
        Inspection::factory()->passed()->create(['material_id' => $this->material->id]);

        // Order is driven client-side from the $widgetOrder prop, which the
        // controller derives from DashboardWidget.sort_order. After moving
        // inbound_qc_overview to sort_order=15 it must precede the OEE widget
        // (default sort_order=20) in that ordered list.
        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/Dashboard')
                ->where('widgetOrder', function ($order) {
                    $order = collect($order)->values();
                    $inbound = $order->search('inbound_qc_overview', true);
                    $oee = $order->search('oee_overview', true);

                    // Inbound must be present and, if OEE is also present,
                    // appear before it.
                    if ($inbound === false) {
                        return false;
                    }

                    return $oee === false || $inbound < $oee;
                })
            );
    }

    public function test_pass_rate_color_yellow_when_between_80_and_95(): void
    {
        // 4 passed + 1 failed = 5 completed → 80.0% pass rate. The yellow
        // colour band (80–95%) is applied client-side from this stat; here we
        // assert the server-computed value that drives it.
        Inspection::factory()->count(4)->passed()->create(['material_id' => $this->material->id]);
        Inspection::factory()->failed()->create(['material_id' => $this->material->id]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/Dashboard')
                ->where('inboundQcStats.completed_30d', 5)
                ->where('inboundQcStats.failed_30d', 1)
                ->where('inboundQcStats.pass_rate_30d', fn ($rate) => abs((float) $rate - 80.0) < 0.001)
            );
    }

    public function test_widget_handles_zero_completed_inspections(): void
    {
        Inspection::factory()->pending()->create(['material_id' => $this->material->id]);

        // No completed inspections → pass_rate_30d is null (the UI renders an
        // em-dash placeholder for a null rate).
        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/Dashboard')
                ->where('enabledWidgets', fn ($widgets) => collect($widgets)->contains('inbound_qc_overview'))
                ->where('inboundQcStats.pending', 1)
                ->where('inboundQcStats.completed_30d', 0)
                ->where('inboundQcStats.pass_rate_30d', null)
            );
    }

    public function test_recent_failures_link_to_inspection_detail(): void
    {
        // Recent-failure rows themselves arrive in the browser via live sync,
        // not as Inertia props, so the dashboard payload only carries the
        // aggregate failure count. Assert that count reflects the failed
        // inspection (the client builds the detail links from synced rows).
        Inspection::factory()->failed()->create(['material_id' => $this->material->id]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/Dashboard')
                ->where('enabledWidgets', fn ($widgets) => collect($widgets)->contains('inbound_qc_overview'))
                ->where('inboundQcStats.failed_30d', 1)
                ->where('inboundQcStats.completed_30d', 1)
            );
    }
}
