<?php

namespace Tests\Feature\Supervisor;

use App\Models\Line;
use App\Models\ProductType;
use App\Models\User;
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

    public function test_supervisor_may_manage_orders_without_any_admin_tab(): void
    {
        $supervisor = $this->supervisor();

        // The capability is the permission; reaching /admin is a separate
        // question the role no longer answers yes to.
        $this->assertTrue($supervisor->can('create work orders'));
        $this->assertFalse($supervisor->can('tab:orders'));
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

    public function test_supervisor_is_refused_the_admin_orders_pages(): void
    {
        // Orders live at /supervisor/work-orders for this role; the admin copy
        // is gated by tab:orders, which a supervisor is no longer granted.
        $this->actingAs($this->supervisor())
            ->get('/admin/work-orders/create')
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
