<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Customer;
use App\Models\Line;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 — a work order can be linked to a customer through the admin create
 * and edit flows, and the link is validated against real customers.
 */
class WorkOrderCustomerTest extends TestCase
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

    public function test_work_order_can_be_created_with_a_customer(): void
    {
        $customer = Customer::factory()->create();
        $line = Line::factory()->create();
        $productType = ProductType::factory()->create();
        ProcessTemplate::factory()->withSteps(2)->create(['product_type_id' => $productType->id]);

        $response = $this->actingAs($this->admin)->post('/admin/work-orders', [
            'order_no' => 'WO-CUST-001',
            'customer_id' => $customer->id,
            'line_id' => $line->id,
            'product_type_id' => $productType->id,
            'planned_qty' => 100,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('work_orders', [
            'order_no' => 'WO-CUST-001',
            'customer_id' => $customer->id,
        ]);
    }

    public function test_customer_id_must_reference_an_existing_customer(): void
    {
        $line = Line::factory()->create();
        $productType = ProductType::factory()->create();
        ProcessTemplate::factory()->withSteps(2)->create(['product_type_id' => $productType->id]);

        $response = $this->actingAs($this->admin)->post('/admin/work-orders', [
            'order_no' => 'WO-CUST-002',
            'customer_id' => 999999,
            'line_id' => $line->id,
            'product_type_id' => $productType->id,
            'planned_qty' => 100,
        ]);

        $response->assertSessionHasErrors('customer_id');
    }

    public function test_cannot_assign_a_soft_deleted_customer(): void
    {
        $customer = Customer::factory()->create();
        $customer->delete();

        $line = Line::factory()->create();
        $productType = ProductType::factory()->create();
        ProcessTemplate::factory()->withSteps(2)->create(['product_type_id' => $productType->id]);

        $response = $this->actingAs($this->admin)->post('/admin/work-orders', [
            'order_no' => 'WO-CUST-003',
            'customer_id' => $customer->id,
            'line_id' => $line->id,
            'product_type_id' => $productType->id,
            'planned_qty' => 100,
        ]);

        $response->assertSessionHasErrors('customer_id');
    }

    public function test_admin_can_reassign_customer_on_update(): void
    {
        $original = Customer::factory()->create();
        $next = Customer::factory()->create();
        $wo = WorkOrder::factory()->create(['customer_id' => $original->id]);

        $response = $this->actingAs($this->admin)->put("/admin/work-orders/{$wo->id}", [
            'order_no' => $wo->order_no,
            'customer_id' => $next->id,
            'planned_qty' => $wo->planned_qty,
            'status' => WorkOrder::STATUS_PENDING,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('work_orders', ['id' => $wo->id, 'customer_id' => $next->id]);
    }
}
