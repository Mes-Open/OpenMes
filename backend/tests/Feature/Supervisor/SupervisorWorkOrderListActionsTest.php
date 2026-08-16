<?php

namespace Tests\Feature\Supervisor;

use App\Models\Batch;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The supervisor work-order list's bulk toolbar and Delete item — the two
 * actions that reach their own /supervisor endpoints rather than the admin ones.
 */
class SupervisorWorkOrderListActionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $supervisor;

    protected User $admin;

    protected User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('Supervisor');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
    }

    // ── Bulk transitions ─────────────────────────────────────────────────────

    public function test_supervisor_can_accept_many_work_orders_at_once(): void
    {
        $orders = WorkOrder::factory()->count(3)->create(['status' => WorkOrder::STATUS_PENDING]);

        $response = $this->actingAs($this->supervisor)
            ->from('/supervisor/work-orders')
            ->post('/supervisor/work-orders/bulk', [
                'action' => 'accept',
                'ids' => $orders->pluck('id')->all(),
            ]);

        $response->assertRedirect('/supervisor/work-orders');
        $response->assertSessionHas('success');

        foreach ($orders as $order) {
            $this->assertSame(WorkOrder::STATUS_ACCEPTED, $order->fresh()->status);
        }
    }

    public function test_orders_the_action_does_not_apply_to_are_skipped_not_failed(): void
    {
        $pending = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_PENDING]);
        $done = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_DONE]);

        $response = $this->actingAs($this->supervisor)->post('/supervisor/work-orders/bulk', [
            'action' => 'accept',
            'ids' => [$pending->id, $done->id],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(WorkOrder::STATUS_ACCEPTED, $pending->fresh()->status);
        $this->assertSame(WorkOrder::STATUS_DONE, $done->fresh()->status);
        $this->assertStringContainsString(
            __(':count skipped (not applicable in their current status).', ['count' => 1]),
            session('success'),
        );
    }

    public function test_an_unknown_bulk_action_is_rejected(): void
    {
        $order = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_PENDING]);

        $response = $this->actingAs($this->supervisor)->post('/supervisor/work-orders/bulk', [
            'action' => 'delete_everything',
            'ids' => [$order->id],
        ]);

        $response->assertSessionHasErrors('action');
        $this->assertSame(WorkOrder::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_guest_cannot_bulk_transition_work_orders(): void
    {
        $order = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_PENDING]);

        $this->post('/supervisor/work-orders/bulk', [
            'action' => 'accept',
            'ids' => [$order->id],
        ])->assertRedirect('/login');

        $this->assertSame(WorkOrder::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_operator_cannot_bulk_transition_work_orders(): void
    {
        $order = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_PENDING]);

        $this->actingAs($this->operator)->post('/supervisor/work-orders/bulk', [
            'action' => 'accept',
            'ids' => [$order->id],
        ])->assertStatus(403);

        $this->assertSame(WorkOrder::STATUS_PENDING, $order->fresh()->status);
    }

    // ── Delete ───────────────────────────────────────────────────────────────

    public function test_admin_can_delete_a_work_order_from_the_supervisor_list(): void
    {
        $order = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_PENDING]);

        $this->actingAs($this->admin)
            ->delete("/supervisor/work-orders/{$order->id}")
            ->assertRedirect('/supervisor/work-orders');

        $this->assertSoftDeleted($order);
    }

    public function test_supervisor_without_the_delete_ability_is_forbidden(): void
    {
        $order = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_PENDING]);

        $this->actingAs($this->supervisor)
            ->delete("/supervisor/work-orders/{$order->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('work_orders', ['id' => $order->id, 'deleted_at' => null]);
    }

    /** The list hides Delete for a user the policy would refuse. */
    public function test_the_list_reports_whether_the_viewer_may_delete(): void
    {
        $this->actingAs($this->supervisor)->get('/supervisor/work-orders')
            ->assertInertia(fn ($page) => $page->where('can.delete', false));

        $this->actingAs($this->admin)->get('/supervisor/work-orders')
            ->assertInertia(fn ($page) => $page->where('can.delete', true));
    }

    public function test_an_order_with_batches_cannot_be_deleted(): void
    {
        $order = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_IN_PROGRESS]);
        Batch::factory()->create(['work_order_id' => $order->id]);

        $this->actingAs($this->admin)
            ->from('/supervisor/work-orders')
            ->delete("/supervisor/work-orders/{$order->id}")
            ->assertSessionHas('error');

        $this->assertDatabaseHas('work_orders', ['id' => $order->id, 'deleted_at' => null]);
    }

    // ── Create from the list modal ───────────────────────────────────────────

    public function test_the_list_carries_the_options_its_create_modal_needs(): void
    {
        $this->actingAs($this->supervisor)->get('/supervisor/work-orders')
            ->assertInertia(fn ($page) => $page
                ->has('lines')
                ->has('productTypes')
                ->has('customers')
                ->has('bomTemplates')
                ->has('productRevisions')
                ->has('customerNames')
                ->etc());
    }

    public function test_creating_with_stay_returns_to_the_list_instead_of_redirecting(): void
    {
        $response = $this->actingAs($this->supervisor)
            ->from('/supervisor/work-orders')
            ->post('/supervisor/work-orders', [
                'order_no' => 'WO-STAY-1',
                'planned_qty' => 10,
                'stay' => 1,
            ]);

        $response->assertRedirect('/supervisor/work-orders');
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('work_orders', ['order_no' => 'WO-STAY-1']);
    }
}
