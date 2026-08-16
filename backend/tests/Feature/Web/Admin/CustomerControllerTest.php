<?php

namespace Tests\Feature\Web\Admin;

use App\Enums\Tier;
use App\Models\Customer;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Admin', 'web');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    public function test_admin_can_list_customers(): void
    {
        Customer::factory()->create(['name' => 'ABC Textiles']);

        // Rows live-sync via the `customers` collection rather than being
        // server-rendered, so we only assert the page loads for an admin.
        $this->actingAs($this->admin)
            ->get(route('admin.customers.index'))
            ->assertOk();
    }

    public function test_admin_can_create_customer(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.customers.store'), [
            'name' => 'ABC Textiles',
            'code' => 'ABC',
            'tier' => Tier::Gold->value,
            'payment_score' => 92,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.customers.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customers', [
            'name' => 'ABC Textiles',
            'code' => 'ABC',
            'tier' => 'gold',
            'payment_score' => 92,
            'is_active' => true,
        ]);
    }

    public function test_tier_must_be_a_valid_value(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.customers.store'), [
            'name' => 'Bad Tier',
            'tier' => 'platinum',
        ]);

        $response->assertSessionHasErrors('tier');
    }

    public function test_payment_score_must_be_within_range(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.customers.store'), [
            'name' => 'Out Of Range',
            'tier' => Tier::Bronze->value,
            'payment_score' => 150,
        ]);

        $response->assertSessionHasErrors('payment_score');
    }

    public function test_code_must_be_unique_among_live_customers(): void
    {
        Customer::factory()->create(['code' => 'DUP']);

        $response = $this->actingAs($this->admin)->post(route('admin.customers.store'), [
            'name' => 'Duplicate',
            'code' => 'DUP',
            'tier' => Tier::Bronze->value,
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_code_can_be_reused_after_soft_delete(): void
    {
        $deleted = Customer::factory()->create(['code' => 'REUSE']);
        $deleted->delete();

        $response = $this->actingAs($this->admin)->post(route('admin.customers.store'), [
            'name' => 'Reuses Code',
            'code' => 'REUSE',
            'tier' => Tier::Bronze->value,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customers', ['name' => 'Reuses Code', 'code' => 'REUSE', 'deleted_at' => null]);
    }

    public function test_admin_can_update_customer(): void
    {
        $customer = Customer::factory()->create(['name' => 'Old', 'tier' => Tier::Bronze]);

        $response = $this->actingAs($this->admin)->put(route('admin.customers.update', $customer), [
            'name' => 'New Name',
            'code' => $customer->code,
            'tier' => Tier::Vip->value,
            'payment_score' => 80,
        ]);

        $response->assertRedirect(route('admin.customers.index'));
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'New Name', 'tier' => 'vip']);
    }

    public function test_admin_can_toggle_active(): void
    {
        $customer = Customer::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin)->post(route('admin.customers.toggle-active', $customer));

        $this->assertFalse($customer->fresh()->is_active);
    }

    public function test_customer_is_soft_deleted(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->admin)->delete(route('admin.customers.destroy', $customer));

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    public function test_deleting_customer_preserves_its_work_orders(): void
    {
        $customer = Customer::factory()->create();
        $wo = WorkOrder::factory()->create(['customer_id' => $customer->id]);

        $this->actingAs($this->admin)->delete(route('admin.customers.destroy', $customer));

        // Customer is not in softDeleteCascades — deleting it must never remove
        // the customer's work orders. The WO row stays live.
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
        $this->assertDatabaseHas('work_orders', ['id' => $wo->id, 'deleted_at' => null]);
    }

    public function test_non_admin_cannot_manage_customers(): void
    {
        Role::findOrCreate('Operator', 'web');
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        $this->actingAs($operator)->get(route('admin.customers.index'))->assertForbidden();
    }

    public function test_guest_cannot_manage_customers(): void
    {
        $this->get(route('admin.customers.index'))->assertRedirect('/login');
        $this->post(route('admin.customers.store'), [])->assertRedirect('/login');
        $customer = Customer::factory()->create();
        $this->delete(route('admin.customers.destroy', $customer))->assertRedirect('/login');
    }
}
