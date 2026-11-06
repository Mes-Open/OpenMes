<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Customer;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The read-only "Source" card on the admin work-order page: what the ERP sent
 * with the order (system, reference, customer, currency, extra data).
 */
class WorkOrderSourceSectionTest extends TestCase
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

    private function erpOrder(array $overrides = []): WorkOrder
    {
        return WorkOrder::factory()->create(array_merge([
            'order_no' => 'ERP-DEMO-1',
            'unit_price' => 12.5,
            'price_currency' => 'EUR',
            'external_system' => 'pantheon',
            'external_ref' => 'SO-4711/3',
            'customer_name' => 'Acme GmbH',
            'extra_data' => ['color' => 'red', 'sizes' => ['S', 'M']],
        ], $overrides));
    }

    public function test_admin_sees_the_source_of_an_erp_order(): void
    {
        $order = $this->erpOrder();

        $this->actingAs($this->admin)->get("/admin/work-orders/{$order->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/work-orders/Show')
                ->where('workOrder.source.external_system', 'pantheon')
                ->where('workOrder.source.external_ref', 'SO-4711/3')
                ->where('workOrder.source.customer_name', 'Acme GmbH')
                ->where('workOrder.source.price_currency', 'EUR')
                ->where('workOrder.extra_data.color', 'red')
                ->where('workOrder.extra_data.sizes', ['S', 'M'])
                // No linked Customer record — the ERP's name does not leak into it.
                ->where('workOrder.customer_name', null)
            );
    }

    public function test_linked_customer_and_erp_customer_name_stay_apart(): void
    {
        $customer = Customer::factory()->create(['name' => 'Linked Customer Sp. z o.o.']);
        $order = $this->erpOrder(['customer_id' => $customer->id]);

        $this->actingAs($this->admin)->get("/admin/work-orders/{$order->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('workOrder.customer_name', 'Linked Customer Sp. z o.o.')
                ->where('workOrder.source.customer_name', 'Acme GmbH')
            );
    }

    public function test_order_without_source_has_empty_source_props(): void
    {
        $order = WorkOrder::factory()->create(['unit_price' => null, 'extra_data' => null]);

        $this->actingAs($this->admin)->get("/admin/work-orders/{$order->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('workOrder.source.external_system', null)
                ->where('workOrder.source.external_ref', null)
                ->where('workOrder.source.customer_name', null)
                ->where('workOrder.source.price_currency', null)
            );
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $order = $this->erpOrder();

        $this->get("/admin/work-orders/{$order->id}")->assertRedirect('/login');
    }

    public function test_operator_cannot_open_the_admin_order_page(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Operator');
        $order = $this->erpOrder();

        $this->actingAs($operator)->get("/admin/work-orders/{$order->id}")->assertForbidden();
    }
}
