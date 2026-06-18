<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Role × tab access: the admin panel is gated per-tab (TabAccessMiddleware) and
 * configured via the Settings → Access matrix. Admin always has full access.
 */
class TabAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $supervisor;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('Supervisor');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
    }

    public function test_admin_reaches_every_tab(): void
    {
        $this->actingAs($this->admin)->get('/admin/work-orders')->assertOk();
        $this->actingAs($this->admin)->get('/admin/users')->assertOk();
    }

    public function test_supervisor_without_grant_is_forbidden(): void
    {
        // Supervisor holds tab:orders by default, but not other tabs.
        $this->actingAs($this->supervisor)->get('/admin/sites')->assertForbidden();
        $this->actingAs($this->supervisor)->get('/admin/users')->assertForbidden();
    }

    public function test_granting_a_tab_lets_the_role_in(): void
    {
        // hr is not a Supervisor default → granting it opens HR pages.
        Role::findByName('Supervisor', 'web')->givePermissionTo('tab:hr');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->supervisor)->get('/admin/workers')->assertOk();
        // A non-granted tab stays forbidden.
        $this->actingAs($this->supervisor)->get('/admin/users')->assertForbidden();
    }

    public function test_matrix_page_is_admin_only_and_renders(): void
    {
        $this->actingAs($this->admin)->get('/settings/access')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('settings/Access')
                ->has('tabs')
                ->has('roles')
                ->has('matrix')
                ->where('lockedRole', 'Admin'));

        $this->actingAs($this->supervisor)->get('/settings/access')->assertForbidden();
    }

    public function test_update_grants_and_revokes_via_the_matrix(): void
    {
        $this->actingAs($this->admin)->post('/settings/access', [
            'access' => [
                'Supervisor' => ['orders', 'hr'],
                'Operator' => [],
            ],
        ])->assertRedirect();

        $this->assertTrue(Role::findByName('Supervisor', 'web')->hasPermissionTo('tab:orders'));
        $this->assertTrue(Role::findByName('Supervisor', 'web')->hasPermissionTo('tab:hr'));

        $this->actingAs($this->supervisor)->get('/admin/work-orders')->assertOk();
        $this->actingAs($this->supervisor)->get('/admin/workers')->assertOk();
        $this->actingAs($this->supervisor)->get('/admin/users')->assertForbidden();

        // Revoke by submitting an empty set for the role.
        $this->actingAs($this->admin)->post('/settings/access', [
            'access' => ['Supervisor' => []],
        ])->assertRedirect();

        $this->assertFalse(Role::findByName('Supervisor', 'web')->hasPermissionTo('tab:orders'));
        $this->actingAs($this->supervisor)->get('/admin/work-orders')->assertForbidden();
    }

    public function test_admin_access_cannot_be_revoked(): void
    {
        // Even if the matrix submits an empty Admin set, Admin keeps full access.
        $this->actingAs($this->admin)->post('/settings/access', [
            'access' => ['Admin' => []],
        ])->assertRedirect();

        $this->assertTrue(Role::findByName('Admin', 'web')->hasPermissionTo('tab:admin'));
        $this->actingAs($this->admin)->get('/admin/users')->assertOk();
    }

    public function test_operator_granted_a_tab_can_open_the_admin_panel(): void
    {
        // No grant → the admin panel stays forbidden.
        $this->actingAs($this->operator)->get('/admin/work-orders')->assertForbidden();

        Role::findByName('Operator', 'web')->givePermissionTo('tab:orders');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->operator)->get('/admin/work-orders')->assertOk();
    }

    public function test_operator_select_line_exposes_granted_tab_links(): void
    {
        // No grant → no sidebar links, but still flagged as an operator.
        $this->actingAs($this->operator)->get(route('operator.select-line'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.user.isOperator', true)
                ->where('auth.user.accessibleTabLinks', []));

        Role::findByName('Operator', 'web')->givePermissionTo('tab:orders');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // Granted → the operator sidebar lists the tab with its label and URL.
        $this->actingAs($this->operator)->get(route('operator.select-line'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.user.accessibleTabLinks', [
                    ['key' => 'orders', 'label' => 'Orders', 'url' => '/admin/work-orders'],
                ]));
    }

    public function test_reseeding_preserves_matrix_tab_grants(): void
    {
        // An admin grants Operator a tab via the matrix.
        Role::findByName('Operator', 'web')->givePermissionTo('tab:orders');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // The entrypoint reseeds RolesAndPermissionsSeeder on every deploy/boot.
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $operator = Role::findByName('Operator', 'web');
        // The matrix grant survives the reseed...
        $this->assertTrue($operator->hasPermissionTo('tab:orders'));
        // ...and the canonical operational set is still applied.
        $this->assertTrue($operator->hasPermissionTo('view work orders'));
        // A tab that was never granted stays absent.
        $this->assertFalse($operator->hasPermissionTo('tab:hr'));

        $this->actingAs($this->operator)->get('/admin/work-orders')->assertOk();
    }

    public function test_invalid_tab_key_is_rejected(): void
    {
        $this->actingAs($this->admin)->post('/settings/access', [
            'access' => ['Supervisor' => ['not-a-real-tab']],
        ])->assertSessionHasErrors('access.Supervisor.0');
    }
}
