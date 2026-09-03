<?php

namespace Tests\Feature\Supervisor;

use App\Models\Line;
use App\Models\ProductType;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Per the role docs, Supervisors may create and manage work orders — from their
 * own section. The admin tree is not theirs: see RoleSectionSeparationTest.
 */
class SupervisorWorkOrderCreateTest extends TestCase
{
    use RefreshDatabase;

    private function supervisor(): User
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Supervisor');

        return $user;
    }

    public function test_supervisor_manages_orders_from_their_own_section(): void
    {
        $supervisor = $this->supervisor();

        // The capability is the permission; which section it is exercised from
        // is a separate question. Creating and editing happen under /supervisor.
        $this->assertTrue($supervisor->can('create work orders'));

        // `orders` is granted so change control (#182) is reachable on the admin
        // work-order pages, but nothing else in /admin comes with it.
        $this->assertTrue($supervisor->can('tab:orders'));
        $this->assertFalse($supervisor->can('tab:order_data'));
    }

    public function test_supervisor_can_open_the_create_form(): void
    {
        $this->actingAs($this->supervisor())
            ->get('/supervisor/work-orders/create')
            ->assertOk();
    }

    public function test_supervisor_can_create_a_work_order(): void
    {
        $line = Line::factory()->create();
        $product = ProductType::factory()->create();

        $this->actingAs($this->supervisor())
            ->post('/supervisor/work-orders', [
                'order_no' => 'WO-SUP-1',
                'line_id' => $line->id,
                'product_type_id' => $product->id,
                'planned_qty' => 100,
            ])
            ->assertRedirect('/supervisor/work-orders');

        $this->assertDatabaseHas('work_orders', ['order_no' => 'WO-SUP-1']);
    }

    public function test_create_validates_required_fields(): void
    {
        $this->actingAs($this->supervisor())
            ->post('/supervisor/work-orders', [])
            ->assertSessionHasErrors(['order_no', 'planned_qty']);
    }

    public function test_supervisor_cannot_delete_a_work_order_from_the_admin_pages(): void
    {
        // Reaching the admin order screens (for change control, #182) must not
        // hand over a capability the role does not have: Supervisor holds
        // `edit work orders`, never `delete work orders`. Before the tab was
        // granted this route was Admin-only and unguarded, so the gate was the
        // section, not the policy.
        $order = WorkOrder::factory()->create();

        $this->actingAs($this->supervisor())
            ->delete("/admin/work-orders/{$order->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('work_orders', ['id' => $order->id, 'deleted_at' => null]);
    }

    public function test_supervisor_is_refused_the_commercial_order_pages(): void
    {
        // Day-to-day order work lives at /supervisor/work-orders for this role.
        // The admin order screens are reachable — that is where a production stop
        // and its change request are handled (#182) — but the commercial pages
        // that used to share the tab are on `order_data`, which they don't hold.
        $this->actingAs($this->supervisor())
            ->get('/admin/customers')
            ->assertForbidden();

        // The importer's admin mount is on its own `import` tab; supervisors
        // import work orders from /supervisor/import instead.
        $this->actingAs($this->supervisor())
            ->get('/admin/import')
            ->assertForbidden();
    }

    public function test_guest_cannot_create_work_orders(): void
    {
        $this->get('/supervisor/work-orders/create')->assertStatus(302);
        $this->post('/supervisor/work-orders', [])->assertStatus(302);
    }

    public function test_operator_cannot_create_work_orders(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        // No 'create work orders' ability → policy denies, and no /supervisor access.
        $this->actingAs($operator)->get('/supervisor/work-orders/create')->assertForbidden();
        $this->actingAs($operator)->post('/supervisor/work-orders', [
            'order_no' => 'WO-OP-1',
            'planned_qty' => 10,
        ])->assertForbidden();
        $this->assertDatabaseMissing('work_orders', ['order_no' => 'WO-OP-1']);
    }
}
