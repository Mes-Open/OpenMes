<?php

namespace Tests\Feature\Sync;

use App\Models\Pallet;
use App\Models\User;
use App\Support\TabRegistry;
use App\Sync\ShapeRegistry;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Who may read a synced collection. The snapshot (GET /api/collections/{name})
 * and the live channel (col.{tenant}.{collection}) share one rule,
 * Shape::allows(): Admin and Supervisor read everything; anyone else only the
 * collections whose page they may open (an access-matrix tab) or whose screen
 * their role reaches. Before this, the snapshot served every collection to any
 * signed-in user, e.g. customers to an Operator.
 */
class CollectionAccessTest extends TestCase
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

    private function grantTab(string $role, string $tab): void
    {
        Role::findByName($role, 'web')->givePermissionTo(TabRegistry::permission($tab));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_admin_and_supervisor_read_every_collection(): void
    {
        foreach (['Admin', 'Supervisor'] as $role) {
            $user = $this->userWithRole($role);

            foreach (['customers', 'workers', 'users', 'work_orders_active', 'integration_configs'] as $name) {
                $this->actingAs($user)->getJson("/api/collections/{$name}")->assertOk();
            }
        }
    }

    public function test_operator_cannot_read_admin_collections(): void
    {
        $operator = $this->userWithRole('Operator');

        foreach (['customers', 'companies', 'workers', 'users', 'cost_sources', 'work_orders_all', 'work_orders_active', 'issues_open', 'integration_configs', 'webhooks'] as $name) {
            $this->actingAs($operator)->getJson("/api/collections/{$name}")->assertForbidden();
        }
    }

    public function test_user_without_a_role_is_refused(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/collections/customers')
            ->assertForbidden();
    }

    public function test_guest_is_unauthenticated(): void
    {
        $this->getJson('/api/collections/customers')->assertUnauthorized();
    }

    public function test_operator_reads_pallets_for_the_logistics_terminal(): void
    {
        // /logistics/pallets is open to Operators by role, and its table is
        // fed by the `pallets` collection.
        Pallet::factory()->create();

        $this->actingAs($this->userWithRole('Operator'))
            ->getJson('/api/collections/pallets')
            ->assertOk()
            ->assertJsonCount(1, 'rows');
    }

    public function test_a_granted_tab_opens_the_collections_its_pages_read(): void
    {
        $operator = $this->userWithRole('Operator');
        $this->grantTab('Operator', 'order_data');

        $this->actingAs($operator)->getJson('/api/collections/customers')->assertOk();
        $this->actingAs($operator)->getJson('/api/collections/priority_rules')->assertOk();

        // ...and nothing outside that tab.
        $this->actingAs($operator)->getJson('/api/collections/workers')->assertForbidden();
        $this->actingAs($operator)->getJson('/api/collections/companies')->assertForbidden();
    }

    public function test_class_shapes_declare_their_tabs_too(): void
    {
        $operator = $this->userWithRole('Operator');
        $this->grantTab('Operator', 'dashboard');

        $this->actingAs($operator)->getJson('/api/collections/work_orders_active')->assertOk();
        $this->actingAs($operator)->getJson('/api/collections/oee_records_recent')->assertOk();
        $this->actingAs($operator)->getJson('/api/collections/work_orders_all')->assertForbidden();
    }

    public function test_every_declared_tab_exists(): void
    {
        $registry = app(ShapeRegistry::class);

        foreach (array_keys($registry->shapes()) as $name) {
            foreach ($registry->find($name)->tabs() as $tab) {
                $this->assertTrue(TabRegistry::exists($tab), "Collection {$name} names unknown tab {$tab}.");
            }
        }
    }

    public function test_live_channel_follows_the_same_rule(): void
    {
        // See ShiftMonitorTest: the null broadcaster authorizes everyone, so pin
        // a real driver and re-register the channels on it.
        config(['broadcasting.default' => 'reverb']);
        require base_path('routes/channels.php');

        $auth = fn (User $user, string $collection) => $this->actingAs($user)->postJson('/broadcasting/auth', [
            'channel_name' => "private-col.g.{$collection}",
            'socket_id' => '1234.5678',
        ]);

        $operator = $this->userWithRole('Operator');
        $auth($operator, 'customers')->assertForbidden();
        $auth($operator, 'pallets')->assertOk();
        $auth($operator, 'not_a_collection')->assertForbidden();

        $this->grantTab('Operator', 'order_data');
        $auth($operator, 'customers')->assertOk();

        $auth($this->userWithRole('Supervisor'), 'workers')->assertOk();
    }
}
