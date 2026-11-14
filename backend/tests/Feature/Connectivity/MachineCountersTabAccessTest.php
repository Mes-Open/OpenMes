<?php

namespace Tests\Feature\Connectivity;

use App\Models\User;
use App\Support\ModuleRegistry;
use App\Support\TabRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Machine counters are their own tab in Settings → Access: a supervisor can be
 * given them without the broker / PLC / gateway configuration.
 */
class MachineCountersTabAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('Supervisor');
    }

    private function grantCounters(): void
    {
        Role::findByName('Supervisor', 'web')->givePermissionTo(TabRegistry::permission('machine_counters'));
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_a_supervisor_without_the_grant_is_forbidden(): void
    {
        $this->actingAs($this->supervisor)->get('/admin/connectivity/counters')->assertForbidden();
        $this->actingAs($this->supervisor)->postJson('/admin/connectivity/counters', [])->assertForbidden();
    }

    public function test_the_grant_opens_the_counters_and_nothing_else_of_connectivity(): void
    {
        $this->grantCounters();

        $this->actingAs($this->supervisor)->get('/admin/connectivity/counters')->assertOk();
        // Reaches validation, i.e. past authorization.
        $this->actingAs($this->supervisor)->postJson('/admin/connectivity/counters', [])->assertUnprocessable();

        foreach (['/admin/connectivity', '/admin/connectivity/mqtt', '/admin/connectivity/modbus', '/admin/connectivity/opcua'] as $path) {
            $this->actingAs($this->supervisor)->get($path)->assertForbidden();
        }
        $this->assertSame(['machine_counters'], array_values(array_intersect(
            TabRegistry::accessibleFor($this->supervisor), ['connectivity', 'machine_counters'],
        )));
    }

    public function test_the_tab_goes_with_the_connectivity_module(): void
    {
        $this->grantCounters();
        ModuleRegistry::save(['reports']);

        $this->actingAs($this->supervisor)->get('/admin/connectivity/counters')->assertNotFound();
        $this->assertNotContains('machine_counters', TabRegistry::accessibleFor($this->supervisor));
    }

    public function test_the_tab_is_listed_in_the_access_matrix(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $tabs = $this->actingAs($admin)->get('/settings/access')->assertOk()
            ->getOriginalContent()->getData()['page']['props']['tabs'];

        $this->assertContains('machine_counters', array_column($tabs, 'key'));
    }

    public function test_upgrade_keeps_counters_for_everyone_who_had_connectivity(): void
    {
        // An installation from before the split: a role holding connectivity,
        // and no machine_counters permission yet.
        Permission::where('name', 'tab:machine_counters')->delete();
        $planner = Role::findOrCreate('Planner', 'web');
        $planner->givePermissionTo('tab:connectivity');

        $migration = require database_path('migrations/2026_11_10_120000_seed_machine_counters_tab_permission.php');
        $migration->up();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue($planner->fresh()->hasPermissionTo('tab:machine_counters'));
        $this->assertTrue(Role::findByName('Admin', 'web')->hasPermissionTo('tab:machine_counters'));
        $this->assertFalse(Role::findByName('Supervisor', 'web')->hasPermissionTo('tab:machine_counters'));
    }
}
