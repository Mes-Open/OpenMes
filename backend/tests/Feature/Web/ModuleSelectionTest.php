<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Support\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Optional feature modules (#144): an installation can switch whole feature
 * areas off. A disabled module is hidden from navigation and its routes 404;
 * core areas and existing installs (no setting) stay fully on.
 */
class ModuleSelectionTest extends TestCase
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

    private function disableModule(string $key): void
    {
        ModuleRegistry::save(array_values(array_diff(ModuleRegistry::optionalKeys(), [$key])));
    }

    public function test_all_modules_enabled_by_default_when_unset(): void
    {
        $this->assertSame([], DB::table('system_settings')->where('key', ModuleRegistry::SETTING_KEY)->get()->all());
        $this->assertEqualsCanonicalizing(ModuleRegistry::optionalKeys(), ModuleRegistry::enabled());
        $this->assertTrue(ModuleRegistry::isTabEnabled('hr'));
    }

    public function test_core_areas_are_always_enabled(): void
    {
        $this->disableModule('hr'); // an optional one off…
        // …core tabs (not in OPTIONAL) stay enabled regardless.
        foreach (['dashboard', 'orders', 'production', 'admin'] as $core) {
            $this->assertTrue(ModuleRegistry::isTabEnabled($core), "$core must stay enabled");
        }
        $this->assertFalse(ModuleRegistry::isTabEnabled('hr'));
    }

    public function test_disabled_module_route_returns_404(): void
    {
        // HR enabled → reachable.
        $this->actingAs($this->admin)->get('/admin/workers')->assertOk();

        $this->disableModule('hr');

        // HR disabled → 404 (gone), not 403.
        $this->actingAs($this->admin)->get('/admin/workers')->assertNotFound();
    }

    public function test_employee_scheduling_is_gated_by_the_hr_module(): void
    {
        // HR enabled → employee scheduling (under the core Schedule area) is reachable.
        $this->actingAs($this->admin)->get('/admin/schedule/employees')->assertOk();

        $this->disableModule('hr');

        // HR disabled → the employee sub-page 404s, even though Schedule is core…
        $this->actingAs($this->admin)->get('/admin/schedule/employees')->assertNotFound();
        // …while the Schedule planner itself stays reachable.
        $this->actingAs($this->admin)->get('/admin/schedule')->assertOk();
    }

    /**
     * The granular feature modules (#144 split): each renders under the (core)
     * Production or Reports nav group but is toggled independently.
     *
     * @return array<string, array{0: string, 1: array<int, string>}>
     */
    public static function granularModuleProvider(): array
    {
        return [
            'materials' => ['materials', ['/admin/materials', '/admin/material-lots', '/admin/traceability']],
            'product_engineering' => ['product_engineering', ['/admin/process-segments', '/admin/product-revisions']],
            'companies' => ['companies', ['/admin/companies']],
            'quality' => ['quality', ['/admin/issues', '/admin/anomaly-reasons', '/admin/scrap-reasons']],
            'advanced_reports' => ['advanced_reports', [
                '/admin/cost-reports', '/admin/scrap-reports', '/admin/non-conformance-reports', '/admin/net-requirements',
            ]],
        ];
    }

    /**
     * @param  array<int, string>  $gated
     *
     * @dataProvider granularModuleProvider
     */
    public function test_granular_module_gates_only_its_own_pages(string $module, array $gated): void
    {
        foreach ($gated as $path) {
            $this->actingAs($this->admin)->get($path)->assertOk();
        }

        $this->disableModule($module);

        foreach ($gated as $path) {
            $this->actingAs($this->admin)->get($path)->assertNotFound();
        }

        // Core essentials and the base Work-Order History report stay reachable
        // regardless of which fine-grained module is off.
        $this->actingAs($this->admin)->get('/admin/product-types')->assertOk();
        $this->actingAs($this->admin)->get('/admin/lot-sequences')->assertOk();
        $this->actingAs($this->admin)->get('/admin/reports')->assertOk();
    }

    public function test_base_reports_and_advanced_reports_toggle_independently(): void
    {
        // Turning off the base Reports module must not take the analytical reports
        // with it, and vice-versa — they are separate toggles now.
        $this->disableModule('reports');
        $this->actingAs($this->admin)->get('/admin/reports')->assertNotFound();
        $this->actingAs($this->admin)->get('/admin/cost-reports')->assertOk();

        ModuleRegistry::save(array_values(array_diff(ModuleRegistry::optionalKeys(), ['advanced_reports'])));
        $this->actingAs($this->admin)->get('/admin/reports')->assertOk();
        $this->actingAs($this->admin)->get('/admin/cost-reports')->assertNotFound();
    }

    public function test_upgrade_migration_expands_a_pre_split_module_set(): void
    {
        // A pre-split install that explicitly saved a subset (materials/companies
        // etc. were core then; advanced reports lived under the Reports tab).
        DB::table('system_settings')->updateOrInsert(
            ['key' => ModuleRegistry::SETTING_KEY],
            ['value' => json_encode(['reports', 'hr']), 'updated_at' => now()],
        );

        (require database_path('migrations/2026_07_23_120000_expand_enabled_modules_for_granular_split.php'))->up();

        $enabled = ModuleRegistry::enabled();
        // Formerly-core areas stay visible…
        foreach (['materials', 'product_engineering', 'companies', 'quality'] as $key) {
            $this->assertContains($key, $enabled, "$key should be kept on after upgrade");
        }
        // …'reports' present ⇒ its old analytical reports follow…
        $this->assertContains('advanced_reports', $enabled);
        // …and the original explicit picks are retained.
        $this->assertContains('reports', $enabled);
        $this->assertContains('hr', $enabled);
        // Structure was NOT in the saved set and isn't a formerly-core area, so it
        // stays off — the backfill only restores what used to be always-visible.
        $this->assertNotContains('structure', $enabled);
    }

    public function test_disabled_module_is_dropped_from_accessible_tabs(): void
    {
        $this->disableModule('connectivity');

        $response = $this->actingAs($this->admin)->get('/admin/dashboard');
        $tabs = $response->getOriginalContent()->getData()['page']['props']['auth']['user']['accessibleTabs'] ?? [];

        $this->assertNotContains('connectivity', $tabs);
        $this->assertContains('orders', $tabs); // core stays
    }

    public function test_settings_update_persists_module_selection(): void
    {
        $payload = [
            'production_period' => 'none',
            'workflow_mode' => 'status',
            'schedule_view_mode' => 'weekly',
            'schedule_shifts_per_day' => 1,
            'schedule_horizon_weeks' => 6,
            'realtime_mode' => 'polling',
            'production_tracking_mode' => 'per_operation',
            'production_qty_edit_policy' => 'none',
            'scanner_mode' => 'hid',
            // Keep everything except Packaging.
            'enabled_modules' => array_values(array_diff(ModuleRegistry::optionalKeys(), ['packaging'])),
        ];

        $this->actingAs($this->admin)->post('/settings/system', $payload)->assertRedirect();

        $this->assertFalse(ModuleRegistry::isModuleEnabled('packaging'));
        $this->assertTrue(ModuleRegistry::isModuleEnabled('reports'));
    }

    public function test_settings_update_rejects_an_unknown_module(): void
    {
        $this->actingAs($this->admin)
            ->post('/settings/system', [
                'production_period' => 'none', 'workflow_mode' => 'status', 'schedule_view_mode' => 'weekly',
                'schedule_shifts_per_day' => 1, 'schedule_horizon_weeks' => 6, 'realtime_mode' => 'polling',
                'production_tracking_mode' => 'per_operation', 'production_qty_edit_policy' => 'none', 'scanner_mode' => 'hid',
                'enabled_modules' => ['orders'], // 'orders' is core, not an optional module key
            ])
            ->assertSessionHasErrors('enabled_modules.0');
    }

    public function test_system_settings_page_exposes_the_modules_prop(): void
    {
        $response = $this->actingAs($this->admin)->get('/settings/system');
        $modules = $response->getOriginalContent()->getData()['page']['props']['modules'] ?? null;

        $this->assertIsArray($modules);
        $this->assertCount(count(ModuleRegistry::optionalKeys()), $modules);
        $this->assertArrayHasKey('label', $modules[0]);
    }
}
