<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Services\MenuRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Module menu hooks (MenuRegistry) must reach the React sidebar.
 *
 * Regression: the old Blade sidebar read MenuRegistry directly via View::share.
 * After the React/Inertia migration that sidebar was deleted, so module hooks
 * (addItem / addGroup / addGroupItem) silently rendered nowhere. They are now
 * bridged to the frontend through the `moduleNav` Inertia prop.
 */
class ModuleMenuHooksTest extends TestCase
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

    public function test_module_items_and_groups_are_exposed_as_the_module_nav_prop(): void
    {
        // A module would do this in its ServiceProvider::boot().
        $menu = app(MenuRegistry::class);
        $menu->addItem('admin', 'My Module Page', '/module/mine', order: 90);
        $menu->addGroup('mymod', 'My Module', order: 55);
        $menu->addGroupItem('mymod', 'Overview', '/module/mine/overview');

        $this->actingAs($this->admin)
            ->get('/admin/work-orders')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                // Injected into a built-in dropdown (admin).
                ->where('moduleNav.items.admin.0.label', 'My Module Page')
                ->where('moduleNav.items.admin.0.url', '/module/mine')
                // Custom top-level dropdown declared by the module.
                ->where('moduleNav.groups.0.id', 'mymod')
                ->where('moduleNav.groups.0.label', 'My Module')
                ->where('moduleNav.groups.0.items.0.label', 'Overview')
                ->where('moduleNav.groups.0.items.0.url', '/module/mine/overview'));
    }

    public function test_module_nav_is_present_and_empty_when_no_module_registers_anything(): void
    {
        // No hooks registered — the prop must still exist (empty), never crash.
        $this->actingAs($this->admin)
            ->get('/admin/work-orders')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('moduleNav')
                ->where('moduleNav.items', [])
                ->where('moduleNav.groups', []));
    }
}
