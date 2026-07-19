<?php

namespace Tests\Feature\Web\Admin;

use App\Enums\Tier;
use App\Models\Customer;
use App\Models\Line;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Phase 4 — customer/priority integration on the schedule planner: the overdue
 * high-tier banner data and customer tier surfaced on backlog orders.
 */
class SchedulePlannerCustomerTest extends TestCase
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

    public function test_overdue_high_tier_orders_are_counted_for_the_banner(): void
    {
        $line = Line::factory()->create(['is_active' => true]);
        $gold = Customer::factory()->create(['tier' => Tier::Gold]);

        WorkOrder::factory()->create([
            'line_id' => $line->id,
            'customer_id' => $gold->id,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
            'due_date' => now()->subDays(2),
        ]);

        $this->actingAs($this->admin)->get('/admin/schedule')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('admin/schedule/Planner')
                ->where('overdueImportant.count', 1)
                ->has('overdueImportant.orders', 1)
        );
    }

    public function test_bronze_overdue_orders_are_not_counted(): void
    {
        $line = Line::factory()->create(['is_active' => true]);
        $bronze = Customer::factory()->create(['tier' => Tier::Bronze]);

        WorkOrder::factory()->create([
            'line_id' => $line->id,
            'customer_id' => $bronze->id,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
            'due_date' => now()->subDays(2),
        ]);

        $this->actingAs($this->admin)->get('/admin/schedule')->assertInertia(
            fn (AssertableInertia $page) => $page->where('overdueImportant.count', 0)
        );
    }

    public function test_backlog_orders_carry_customer_tier(): void
    {
        $gold = Customer::factory()->create(['tier' => Tier::Gold, 'name' => 'ACME']);

        // No line → lands in the planner backlog.
        WorkOrder::factory()->create([
            'line_id' => null,
            'customer_id' => $gold->id,
            'status' => WorkOrder::STATUS_PENDING,
        ]);

        $this->actingAs($this->admin)->get('/admin/schedule')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('backlogOrders', 1)
                ->where('backlogOrders.0.customer_tier', 'gold')
                ->where('backlogOrders.0.customer_name', 'ACME')
        );
    }
}
