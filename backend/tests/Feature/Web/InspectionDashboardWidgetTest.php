<?php

namespace Tests\Feature\Web;

use App\Models\DashboardWidget;
use App\Models\Inspection;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        Inspection::factory()->passed()->create(['material_id' => $this->material->id]);
        Inspection::factory()->failed()->create(['material_id' => $this->material->id]);
        Inspection::factory()->pending()->create(['material_id' => $this->material->id]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Inbound QC Overview');
        $response->assertSee('Pass rate', false);
        $response->assertSee('Pending', false);
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
        // Force at least one inspection so the widget block renders.
        Inspection::factory()->passed()->create(['material_id' => $this->material->id]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $response->assertOk();

        // After the sort_order change, $widgetOrder from DB lists inbound_qc_overview
        // first → array_flip gives index 0 → CSS order is 0. The OEE block becomes
        // index 1 → order 10. So inbound appears before OEE in the rendered DOM order
        // (and CSS visual order). Verify both: presence of inbound block and that its
        // CSS order is < OEE's.
        $html = $response->getContent();
        $this->assertStringContainsString('Inbound QC Overview', $html);

        // Build a quick map of widget → its style="order: N" value (from blade).
        // Inbound block is now order 0 (since it's first in $widgetOrder after sort).
        $this->assertMatchesRegularExpression(
            '/style="order: 0"[^>]*>[\s\S]*?Inbound QC Overview/',
            $html,
            'After moving Inbound to sort_order=15, its CSS order should be 0 (first widget)'
        );
    }

    public function test_pass_rate_color_yellow_when_between_80_and_95(): void
    {
        Inspection::factory()->count(4)->passed()->create(['material_id' => $this->material->id]);
        Inspection::factory()->failed()->create(['material_id' => $this->material->id]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('80.0%', false);
        $response->assertSee('bg-yellow-50', false);
    }

    public function test_widget_handles_zero_completed_inspections(): void
    {
        Inspection::factory()->pending()->create(['material_id' => $this->material->id]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Inbound QC Overview');
        $response->assertSee('—', false);
    }

    public function test_recent_failures_link_to_inspection_detail(): void
    {
        $failed = Inspection::factory()->failed()->create(['material_id' => $this->material->id]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee(route('inspections.show', $failed), false);
    }
}
