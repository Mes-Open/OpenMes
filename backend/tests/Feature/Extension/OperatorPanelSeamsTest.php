<?php

namespace Tests\Feature\Extension;

use App\Extension\FilterRegistry;
use App\Extension\HookRegistry;
use App\Models\Line;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\MenuRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The operator-panel, Settings → System and Import seams a module bends.
 *
 * Every one has two sides worth pinning: a community install gets exactly what
 * it got before (defaults, `{}`), and a listening module's contribution reaches
 * the page with the context it was promised.
 */
class OperatorPanelSeamsTest extends TestCase
{
    use RefreshDatabase;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Admin', 'Supervisor', 'Operator'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $this->line = Line::factory()->create();
    }

    private function operator(): User
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');
        $operator->lines()->attach($this->line->id);

        return $operator;
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        return $admin;
    }

    private function atStation(): self
    {
        return $this->actingAs($this->operator())->withSession(['selected_line_id' => $this->line->id]);
    }

    /** @return array<string, mixed> */
    private function props(TestResponse $response): array
    {
        // Not via assertInertia's dot paths: hook names contain dots.
        return $response->viewData('page')['props'];
    }

    // ── Shared operator chrome ──────────────────────────────────────────────

    public function test_a_community_install_gets_the_default_operator_chrome(): void
    {
        $props = $this->props($this->atStation()->get(route('operator.workstation'))->assertOk());

        $this->assertSame(['queue', 'workstation', 'unit_labels', 'packing'], array_column($props['operatorTabs'], 'key'));
        $this->assertContains('/operator/work-order', $props['operatorTabs'][0]['prefixes']);
        $this->assertTrue($props['operatorCanLogout']);
        $this->assertSame([], $props['operatorHooks']);
    }

    public function test_a_module_can_drop_the_core_operator_tabs(): void
    {
        $seen = null;
        app(FilterRegistry::class)->addFilter('operator.tabs', function (array $tabs, array $ctx) use (&$seen) {
            $seen = $ctx['user'];

            return array_filter($tabs, fn ($t) => $t['key'] !== 'queue');
        });

        $props = $this->props($this->atStation()->get(route('operator.workstation'))->assertOk());

        // Re-indexed, so the browser receives a list and not an object.
        $this->assertSame(['workstation', 'unit_labels', 'packing'], array_column($props['operatorTabs'], 'key'));
        $this->assertSame(['key' => 'workstation', 'label' => 'Workstation', 'url' => '/operator/workstation', 'prefixes' => ['/operator/workstation']], $props['operatorTabs'][0]);
        $this->assertInstanceOf(User::class, $seen);
    }

    public function test_a_module_can_hide_the_operator_logout_button(): void
    {
        app(FilterRegistry::class)->addFilter('operator.can_logout', fn () => false);

        $props = $this->props($this->atStation()->get(route('operator.workstation'))->assertOk());

        $this->assertFalse($props['operatorCanLogout']);
    }

    public function test_a_module_can_render_on_every_operator_screen(): void
    {
        app(HookRegistry::class)->listen('display.operator.layout', ['title' => 'Tapped in: nobody']);

        $props = $this->props($this->atStation()->get(route('operator.workstation'))->assertOk());

        $this->assertSame([['title' => 'Tapped in: nobody']], $props['operatorHooks']['display.operator.layout']);
    }

    public function test_module_operator_tabs_pass_through_a_per_request_filter(): void
    {
        $menu = app(MenuRegistry::class);
        $menu->addOperatorItem('Team', url('/operator/team'), order: 30);
        $menu->addOperatorItem('Waste', url('/operator/waste'), order: 40);

        app(FilterRegistry::class)->addFilter(
            'operator.module_tabs',
            fn (array $items) => array_filter($items, fn ($i) => $i['label'] !== 'Team'),
        );

        $this->assertSame(['Waste'], array_column($menu->getOperatorItems(), 'label'));
        $this->assertSame([0], array_keys($menu->getOperatorItems()));
    }

    // ── Workstation page ────────────────────────────────────────────────────

    public function test_the_workstation_page_offers_the_shift_cell_and_quantity_field_points(): void
    {
        $seen = null;
        app(HookRegistry::class)->listen('display.operator.workstation.shift_cell', function ($ctx) use (&$seen) {
            $seen = $ctx;

            return ['component' => 'ext:Keypad/ShiftCell'];
        });
        app(HookRegistry::class)->listen('display.operator.quantity_field', ['component' => 'ext:Keypad/Field']);

        $hooks = $this->props($this->atStation()->get(route('operator.workstation'))->assertOk())['hooks'];

        $this->assertSame([['component' => 'ext:Keypad/ShiftCell']], $hooks['display.operator.workstation.shift_cell']);
        $this->assertSame([['component' => 'ext:Keypad/Field']], $hooks['display.operator.quantity_field']);
        $this->assertSame($this->line->id, $seen['lineId']);
        $this->assertArrayHasKey('workstationId', $seen);
    }

    // ── Queue and work-order detail ─────────────────────────────────────────

    public function test_the_queue_offers_the_quantity_field_point(): void
    {
        $response = $this->atStation()->get('/operator/queue')->assertOk();
        $this->assertSame([], $this->props($response)['hooks']);

        app(HookRegistry::class)->listen('display.operator.quantity_field', ['component' => 'ext:Keypad/Field']);

        $response = $this->atStation()->get('/operator/queue')->assertOk();
        $this->assertSame([['component' => 'ext:Keypad/Field']], $this->props($response)['hooks']['display.operator.quantity_field']);
    }

    public function test_the_work_order_page_offers_a_sections_point_told_which_order(): void
    {
        $workOrder = WorkOrder::factory()->create(['line_id' => $this->line->id]);

        $response = $this->atStation()->get("/operator/work-order/{$workOrder->id}")->assertOk();
        $this->assertSame([], $this->props($response)['hooks']);

        $seen = null;
        app(HookRegistry::class)->listen('display.operator.work_order.sections', function ($ctx) use (&$seen) {
            $seen = $ctx;

            return ['title' => 'Recipe vs weighed'];
        });

        $response = $this->atStation()->get("/operator/work-order/{$workOrder->id}")->assertOk();

        $this->assertSame([['title' => 'Recipe vs weighed']], $this->props($response)['hooks']['display.operator.work_order.sections']);
        $this->assertSame($workOrder->id, $seen['workOrderId']);
        $this->assertArrayHasKey('workstationId', $seen);
    }

    // ── Settings → System ───────────────────────────────────────────────────

    public function test_a_module_can_add_a_tab_to_system_settings(): void
    {
        $response = $this->actingAs($this->admin())->get('/settings/system')->assertOk();
        $this->assertSame([], $this->props($response)['hooks']);

        app(HookRegistry::class)->listen('display.settings.system.tabs', [
            'slot' => 'operator-panel',
            'title' => 'Operator panel',
            'component' => 'ext:Panel/Settings',
        ]);

        $response = $this->actingAs($this->admin())->get('/settings/system')->assertOk();

        $this->assertSame([[
            'slot' => 'operator-panel',
            'title' => 'Operator panel',
            'component' => 'ext:Panel/Settings',
        ]], $this->props($response)['hooks']['display.settings.system.tabs']);
    }

    public function test_system_settings_stay_admin_only(): void
    {
        $this->get('/settings/system')->assertRedirect();
        $this->actingAs($this->operator())->get('/settings/system')->assertForbidden();
    }

    // ── Import routes ───────────────────────────────────────────────────────

    public function test_a_module_importer_is_reachable_although_routes_were_defined_first(): void
    {
        // The routes already exist; the importer arrives afterwards, the way a
        // module provider's boot() adds it.
        app(FilterRegistry::class)->addFilter('import.entities', fn (array $e) => [...$e, SeamThingImporter::class]);

        $this->actingAs($this->admin())->get('/admin/import/seam-things')->assertOk();
        $this->actingAs($this->admin())->get('/admin/import/samples/seam-things')->assertOk();
    }

    public function test_an_unknown_import_entity_still_404s(): void
    {
        $this->actingAs($this->admin())->get('/admin/import/no-such-thing')->assertNotFound();
        $this->actingAs($this->admin())->get('/admin/import/samples/no-such-thing')->assertNotFound();
    }
}
