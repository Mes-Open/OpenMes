<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * OEE lives on the `maintenance` tab, which the `maintenance` optional module
 * controls (#144). With that module switched off — e.g. the `light` onboarding
 * preset, which enables `reports` only — TabAccessMiddleware 404s every
 * /admin/oee route, even for an Admin. These tests pin that behaviour down in
 * both directions, because the default test DB has no `enabled_modules` row at
 * all (= everything enabled) and therefore never exercised the disabled path.
 */
class OeeModuleAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
    }

    public function test_guest_is_redirected_to_login_not_404(): void
    {
        $this->get('/admin/oee')->assertRedirect(route('login'));
    }

    public function test_operator_without_the_tab_is_forbidden(): void
    {
        $this->actingAs($this->operator)
            ->get('/admin/oee')
            ->assertForbidden();
    }

    public function test_admin_can_open_oee_when_the_maintenance_module_is_enabled(): void
    {
        ModuleRegistry::save(['reports', 'maintenance']);

        $this->actingAs($this->admin)
            ->get('/admin/oee')
            ->assertOk();
    }

    public function test_admin_gets_404_when_the_maintenance_module_is_disabled(): void
    {
        // The `light` preset: reports only, no maintenance.
        ModuleRegistry::save(ModuleRegistry::modulesForPreset('light'));

        $this->assertFalse(ModuleRegistry::isTabEnabled('maintenance'));

        $this->actingAs($this->admin)
            ->get('/admin/oee')
            ->assertNotFound();
    }

    public function test_light_preset_does_not_include_maintenance(): void
    {
        $this->assertNotContains('maintenance', ModuleRegistry::modulesForPreset('light'));
        $this->assertContains('maintenance', ModuleRegistry::modulesForPreset('advanced'));
    }

    public function test_oee_print_route_is_not_swallowed_by_the_line_wildcard(): void
    {
        ModuleRegistry::save(['maintenance']);

        // /admin/oee/print must resolve to the print action, not bind "print"
        // as an OeeController@show {line}.
        $this->actingAs($this->admin)
            ->get('/admin/oee/print')
            ->assertOk();

        $this->assertSame(
            'admin.oee.print',
            app('router')->getRoutes()->match(
                \Illuminate\Http\Request::create('/admin/oee/print', 'GET')
            )->getName()
        );
    }
}
