<?php

namespace Tests\Feature\Web;

use App\Models\ProductType;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Opening the Orders section (tab:orders on /admin, the Supervisor role on
 * /supervisor) is not the same as being allowed to create or edit an order:
 * the web forms enforce WorkOrderPolicy::create / ::update, and the UI only
 * offers "New Work Order" / "Edit" to users who hold the ability.
 */
class WorkOrderCreatePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /** An Operator granted the Orders tab in Settings → Access — and nothing more. */
    private function tabOnlyUser(): User
    {
        Role::findByName('Operator', 'web')->givePermissionTo('tab:orders');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->userWithRole('Operator');
    }

    private function supervisorWithout(string $permission): User
    {
        Role::findByName('Supervisor', 'web')->revokePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->userWithRole('Supervisor');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'order_no' => 'WO-PERM-1',
            'product_type_id' => ProductType::factory()->create()->id,
            'planned_qty' => 10,
        ], $overrides);
    }

    // ── Admin tree: create / store ─────────────────────────────────

    public function test_tab_only_user_cannot_open_the_admin_create_form(): void
    {
        $this->actingAs($this->tabOnlyUser())
            ->get('/admin/work-orders/create')
            ->assertForbidden();
    }

    public function test_in_production_the_refusal_is_the_friendly_error_page(): void
    {
        // The handler renders the Inertia Error page ("Forbidden — You do not
        // have permission to view this page.") outside local/testing; it keeps
        // the user's chrome and carries nothing but the status.
        $this->app['env'] = 'production';

        $this->actingAs($this->tabOnlyUser())
            ->get('/admin/work-orders/create')
            ->assertForbidden()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Error')
                ->where('status', 403)
                ->where('auth.user.roles', ['Operator'])
                ->missing('exception')
                ->missing('message'));
    }

    public function test_tab_only_user_cannot_store_an_order_on_the_admin_tree(): void
    {
        $this->actingAs($this->tabOnlyUser())
            ->post('/admin/work-orders', $this->payload())
            ->assertForbidden();

        $this->assertDatabaseMissing('work_orders', ['order_no' => 'WO-PERM-1']);
    }

    public function test_authorization_runs_before_validation_on_store(): void
    {
        // An empty body gets 403, not 422 — no probing order_no uniqueness
        // without the ability.
        $this->actingAs($this->tabOnlyUser())
            ->post('/admin/work-orders', [])
            ->assertForbidden()
            ->assertSessionDoesntHaveErrors();
    }

    public function test_admin_can_open_the_create_form_and_store_an_order(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)->get('/admin/work-orders/create')->assertOk();

        $this->actingAs($admin)
            ->post('/admin/work-orders', $this->payload())
            ->assertRedirect('/admin/work-orders');

        $this->assertDatabaseHas('work_orders', ['order_no' => 'WO-PERM-1']);
    }

    public function test_supervisor_keeps_creating_on_the_admin_tree(): void
    {
        $supervisor = $this->userWithRole('Supervisor');

        $this->actingAs($supervisor)->get('/admin/work-orders/create')->assertOk();

        $this->actingAs($supervisor)
            ->post('/admin/work-orders', $this->payload())
            ->assertRedirect();

        $this->assertDatabaseHas('work_orders', ['order_no' => 'WO-PERM-1']);
    }

    public function test_validation_still_runs_for_authorized_users(): void
    {
        $this->actingAs($this->userWithRole('Admin'))
            ->from('/admin/work-orders/create')
            ->post('/admin/work-orders', [])
            ->assertSessionHasErrors(['order_no', 'planned_qty']);
    }

    // ── Supervisor tree: create / store ────────────────────────────

    public function test_supervisor_without_create_permission_is_refused_on_both_trees(): void
    {
        $supervisor = $this->supervisorWithout('create work orders');

        $this->actingAs($supervisor)->get('/supervisor/work-orders/create')->assertForbidden();
        $this->actingAs($supervisor)->post('/supervisor/work-orders', $this->payload())->assertForbidden();
        $this->actingAs($supervisor)->post('/supervisor/work-orders', [])
            ->assertForbidden()
            ->assertSessionDoesntHaveErrors();

        $this->actingAs($supervisor)->get('/admin/work-orders/create')->assertForbidden();
        $this->actingAs($supervisor)->post('/admin/work-orders', $this->payload())->assertForbidden();

        $this->assertDatabaseMissing('work_orders', ['order_no' => 'WO-PERM-1']);
    }

    public function test_supervisor_creates_from_their_own_section(): void
    {
        $supervisor = $this->userWithRole('Supervisor');

        $this->actingAs($supervisor)->get('/supervisor/work-orders/create')->assertOk();
        $this->actingAs($supervisor)
            ->post('/supervisor/work-orders', $this->payload())
            ->assertRedirect();

        $this->assertDatabaseHas('work_orders', ['order_no' => 'WO-PERM-1']);
    }

    // ── Edit / update ──────────────────────────────────────────────

    public function test_tab_only_user_cannot_edit_on_the_admin_tree(): void
    {
        $user = $this->tabOnlyUser();
        $wo = WorkOrder::factory()->create(['planned_qty' => 5]);

        $this->actingAs($user)->get("/admin/work-orders/{$wo->id}/edit")->assertForbidden();
        $this->actingAs($user)
            ->put("/admin/work-orders/{$wo->id}", ['order_no' => $wo->order_no, 'planned_qty' => 99, 'status' => $wo->status])
            ->assertForbidden();

        $this->assertSame(5.0, (float) $wo->fresh()->planned_qty);
    }

    public function test_supervisor_without_edit_permission_cannot_edit_on_the_supervisor_tree(): void
    {
        $supervisor = $this->supervisorWithout('edit work orders');
        $wo = WorkOrder::factory()->create(['planned_qty' => 5]);

        $this->actingAs($supervisor)->get("/supervisor/work-orders/{$wo->id}/edit")->assertForbidden();
        $this->actingAs($supervisor)
            ->put("/supervisor/work-orders/{$wo->id}", ['order_no' => $wo->order_no, 'planned_qty' => 99, 'status' => $wo->status])
            ->assertForbidden();

        $this->assertSame(5.0, (float) $wo->fresh()->planned_qty);
    }

    public function test_admin_and_supervisor_can_edit_on_both_trees(): void
    {
        foreach (['Admin', 'Supervisor'] as $role) {
            $user = $this->userWithRole($role);

            foreach (['admin', 'supervisor'] as $tree) {
                $wo = WorkOrder::factory()->create(['planned_qty' => 5]);

                $this->actingAs($user)->get("/{$tree}/work-orders/{$wo->id}/edit")->assertOk();
                $this->actingAs($user)
                    ->put("/{$tree}/work-orders/{$wo->id}", ['order_no' => $wo->order_no, 'planned_qty' => 7, 'status' => $wo->status])
                    ->assertRedirect();

                $this->assertSame(7.0, (float) $wo->fresh()->planned_qty, "{$role} on /{$tree}");
            }
        }
    }

    // ── Guests ─────────────────────────────────────────────────────

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/admin/work-orders/create')->assertRedirect('/login');
        $this->post('/admin/work-orders', $this->payload())->assertRedirect('/login');
        $this->get('/supervisor/work-orders/create')->assertRedirect('/login');
        $this->post('/supervisor/work-orders', $this->payload())->assertRedirect('/login');
    }

    // ── UI flags (UX only) ─────────────────────────────────────────

    public function test_shared_ability_flags_hide_create_and_edit_for_the_tab_only_user(): void
    {
        $this->actingAs($this->tabOnlyUser())
            ->get('/admin/work-orders')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/work-orders/Index')
                ->where('auth.user.can.createWorkOrders', false)
                ->where('auth.user.can.editWorkOrders', false)
                // The create modal's option lists are not sent either.
                ->missing('productTypes'));
    }

    public function test_shared_ability_flags_are_on_for_admin_and_supervisor(): void
    {
        foreach (['Admin' => '/admin/work-orders', 'Supervisor' => '/supervisor/work-orders'] as $role => $url) {
            $this->actingAs($this->userWithRole($role))
                ->get($url)
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->where('auth.user.can.createWorkOrders', true)
                    ->where('auth.user.can.editWorkOrders', true)
                    ->has('productTypes'));
        }
    }

    public function test_show_page_withholds_the_edit_form_from_users_who_cannot_edit(): void
    {
        $wo = WorkOrder::factory()->create();

        $this->actingAs($this->tabOnlyUser())
            ->get("/admin/work-orders/{$wo->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.user.can.editWorkOrders', false)
                ->reloadOnly('editForm', fn (AssertableInertia $reload) => $reload->where('editForm', null)));

        $this->actingAs($this->userWithRole('Admin'))
            ->get("/admin/work-orders/{$wo->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->reloadOnly('editForm', fn (AssertableInertia $reload) => $reload->where('editForm.workOrder.id', $wo->id)));
    }

    public function test_access_page_shows_which_roles_may_create_and_edit_work_orders(): void
    {
        $this->actingAs($this->userWithRole('Admin'))
            ->get('/settings/access')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('settings/Access')
                ->has('workOrderAbilities', 2)
                ->where('workOrderAbilityMatrix.Supervisor', ['create work orders', 'edit work orders'])
                ->where('workOrderAbilityMatrix.Operator', []));
    }

    // ── Seeder: same roles hold the abilities as before ────────────

    public function test_seeder_grants_create_and_edit_to_the_same_roles_as_before(): void
    {
        $this->assertTrue(Role::findByName('Admin', 'web')->hasPermissionTo('create work orders'));
        $this->assertTrue(Role::findByName('Admin', 'web')->hasPermissionTo('edit work orders'));
        $this->assertTrue(Role::findByName('Supervisor', 'web')->hasPermissionTo('create work orders'));
        $this->assertTrue(Role::findByName('Supervisor', 'web')->hasPermissionTo('edit work orders'));
        $this->assertFalse(Role::findByName('Operator', 'web')->hasPermissionTo('create work orders'));
        $this->assertFalse(Role::findByName('Operator', 'web')->hasPermissionTo('edit work orders'));

        // Re-seeding (every container start) keeps a Settings → Access tab grant
        // without turning it into the ability to create.
        Role::findByName('Operator', 'web')->givePermissionTo('tab:orders');
        $this->seed(RolesAndPermissionsSeeder::class);

        $operator = Role::findByName('Operator', 'web');
        $this->assertTrue($operator->hasPermissionTo('tab:orders'));
        $this->assertFalse($operator->hasPermissionTo('create work orders'));
    }
}
