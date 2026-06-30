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
