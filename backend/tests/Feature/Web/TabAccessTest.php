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

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('Supervisor');
    }

    public function test_admin_reaches_every_tab(): void
    {
        $this->actingAs($this->admin)->get('/admin/work-orders')->assertOk();
        $this->actingAs($this->admin)->get('/admin/users')->assertOk();
    }

    public function test_supervisor_without_grant_is_forbidden(): void
    {
        $this->actingAs($this->supervisor)->get('/admin/work-orders')->assertForbidden();
        $this->actingAs($this->supervisor)->get('/admin/users')->assertForbidden();
    }

    public function test_granting_a_tab_lets_the_role_in(): void
    {
        Role::findByName('Supervisor', 'web')->givePermissionTo('tab:orders');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->supervisor)->get('/admin/work-orders')->assertOk();
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

    public function test_invalid_tab_key_is_rejected(): void
    {
        $this->actingAs($this->admin)->post('/settings/access', [
            'access' => ['Supervisor' => ['not-a-real-tab']],
        ])->assertSessionHasErrors('access.Supervisor.0');
    }
}
