<?php

namespace Tests\Feature\Web\Admin;

use App\Models\ProductType;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** A work order's numbers have a floor: no negative quantity, price or priority reaches the database. */
class WorkOrderQuantityValidationTest extends TestCase
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

    public function test_negative_or_zero_numbers_are_refused_on_create_and_update(): void
    {
        $product = ProductType::factory()->create();
        $base = ['order_no' => 'WO-NEG-1', 'product_type_id' => $product->id];

        $this->actingAs($this->admin)->from('/admin/work-orders/create')
            ->post('/admin/work-orders', $base + ['planned_qty' => -4])
            ->assertSessionHasErrors('planned_qty');
        $this->actingAs($this->admin)->from('/admin/work-orders/create')
            ->post('/admin/work-orders', $base + ['planned_qty' => 0])
            ->assertSessionHasErrors('planned_qty');
        $this->actingAs($this->admin)->from('/admin/work-orders/create')
            ->post('/admin/work-orders', $base + ['planned_qty' => 4, 'unit_price' => -1, 'priority' => -5])
            ->assertSessionHasErrors(['unit_price', 'priority']);
        $this->assertDatabaseMissing('work_orders', ['order_no' => 'WO-NEG-1']);

        $wo = WorkOrder::factory()->create(['product_type_id' => $product->id, 'planned_qty' => 4]);
        $this->actingAs($this->admin)->from("/admin/work-orders/{$wo->id}/edit")
            ->put("/admin/work-orders/{$wo->id}", ['order_no' => $wo->order_no, 'product_type_id' => $product->id, 'planned_qty' => -1])
            ->assertSessionHasErrors('planned_qty');
        $this->assertSame(4.0, (float) $wo->fresh()->planned_qty);
    }
}
