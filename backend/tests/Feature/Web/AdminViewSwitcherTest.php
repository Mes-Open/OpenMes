<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Support\ViewAs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The administrator's view switcher: an Admin can look at the interface as a
 * supervisor or an operator sees it. Presentation only — the session holds the
 * choice, the server sends it to Admins only, and no permission changes.
 */
class AdminViewSwitcherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['force_password_change' => false]);
        $user->assignRole($role);

        return $user;
    }

    public function test_admin_switches_to_the_supervisor_view(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)
            ->post('/view-as', ['view' => 'supervisor'])
            ->assertRedirect(route('supervisor.dashboard'))
            ->assertSessionHas(ViewAs::SESSION_KEY, 'supervisor');

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('viewAs', 'supervisor'));
    }

    public function test_admin_switches_to_the_operator_view(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)
            ->post('/view-as', ['view' => 'operator'])
            ->assertRedirect(route('operator.select-line'))
            ->assertSessionHas(ViewAs::SESSION_KEY, 'operator');
    }

    public function test_switching_back_to_admin_clears_the_session(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)
            ->withSession([ViewAs::SESSION_KEY => 'operator'])
            ->post('/view-as', ['view' => 'admin'])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionMissing(ViewAs::SESSION_KEY);
    }

    public function test_admin_without_a_choice_gets_the_admin_view(): void
    {
        $this->actingAs($this->userWithRole('Admin'))
            ->get('/admin/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('viewAs', 'admin'));
    }

    public function test_an_unknown_view_is_rejected(): void
    {
        $this->actingAs($this->userWithRole('Admin'))
            ->post('/view-as', ['view' => 'superuser'])
            ->assertSessionHasErrors('view');

        $this->actingAs($this->userWithRole('Admin'))
            ->post('/view-as', [])
            ->assertSessionHasErrors('view');
    }

    public function test_supervisors_and_operators_cannot_switch(): void
    {
        foreach (['Supervisor', 'Operator'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->post('/view-as', ['view' => 'admin'])
                ->assertForbidden();
        }
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->post('/view-as', ['view' => 'operator'])->assertRedirect(route('login'));
    }

    public function test_non_admins_never_receive_the_bar(): void
    {
        // Even with a stale choice in the session, a supervisor gets no switcher.
        $this->actingAs($this->userWithRole('Supervisor'))
            ->withSession([ViewAs::SESSION_KEY => 'operator'])
            ->get('/supervisor/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('viewAs', null));
    }

    public function test_losing_the_admin_role_ends_the_preview(): void
    {
        $admin = $this->userWithRole('Admin');
        $admin->syncRoles(['Supervisor']);

        $this->actingAs($admin->fresh())
            ->withSession([ViewAs::SESSION_KEY => 'supervisor'])
            ->get('/supervisor/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('viewAs', null));
    }

    public function test_the_operator_view_lists_only_the_tabs_the_operator_role_grants(): void
    {
        Role::findByName('Operator')->syncPermissions(['tab:orders']);
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)
            ->withSession([ViewAs::SESSION_KEY => 'operator'])
            ->get('/admin/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.user.accessibleTabLinks', fn ($links) => collect($links)->pluck('key')->all() === ['orders']));

        // In the admin view the same administrator still gets every admin tab.
        $this->flushSession();
        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.user.accessibleTabLinks', fn ($links) => count($links) > 1));
    }

    public function test_the_preview_does_not_change_what_the_admin_may_do(): void
    {
        $admin = $this->userWithRole('Admin');

        // Operator view on, yet the admin-only users page still opens.
        $this->actingAs($admin)
            ->withSession([ViewAs::SESSION_KEY => 'operator'])
            ->get('/admin/users')
            ->assertOk();
    }
}
