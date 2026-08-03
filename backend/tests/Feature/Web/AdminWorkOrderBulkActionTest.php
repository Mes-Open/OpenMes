<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bulk status transitions from the work-order list's selection toolbar
 * (POST /admin/work-orders/bulk).
 */
class AdminWorkOrderBulkActionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
    }

    // ── Happy path ───────────────────────────────────────────────────────────

    public function test_admin_can_accept_many_work_orders_at_once(): void
    {
        $orders = WorkOrder::factory()->count(3)->create(['status' => WorkOrder::STATUS_PENDING]);

        $response = $this->actingAs($this->admin)
            ->from('/admin/work-orders')
            ->post('/admin/work-orders/bulk', [
                'action' => 'accept',
                'ids' => $orders->pluck('id')->all(),
            ]);

        $response->assertRedirect('/admin/work-orders');
        $response->assertSessionHas('success');

        foreach ($orders as $order) {
            $this->assertSame(WorkOrder::STATUS_ACCEPTED, $order->fresh()->status);
        }
    }

    public function test_bulk_cancel_transitions_every_selected_non_terminal_order(): void
    {
        $orders = WorkOrder::factory()->count(2)->create(['status' => WorkOrder::STATUS_IN_PROGRESS]);

        $this->actingAs($this->admin)->post('/admin/work-orders/bulk', [
            'action' => 'cancel',
            'ids' => $orders->pluck('id')->all(),
        ]);

        foreach ($orders as $order) {
            $this->assertSame(WorkOrder::STATUS_CANCELLED, $order->fresh()->status);
        }
    }

    // ── Domain edge case: a selection spanning mixed statuses ────────────────

    public function test_orders_the_action_does_not_apply_to_are_skipped_not_failed(): void
    {
        $pending = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_PENDING]);
        $done = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_DONE]);

        $response = $this->actingAs($this->admin)->post('/admin/work-orders/bulk', [
            'action' => 'accept',
            'ids' => [$pending->id, $done->id],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(WorkOrder::STATUS_ACCEPTED, $pending->fresh()->status);
        // The terminal one is untouched, and the user is told one was skipped.
        $this->assertSame(WorkOrder::STATUS_DONE, $done->fresh()->status);
        // Asserted through the same key the controller uses, so the test doesn't
        // depend on the app's configured locale.
        $this->assertStringContainsString(
            __(':count skipped (not applicable in their current status).', ['count' => 1]),
            session('success'),
        );
    }

    public function test_a_selection_with_no_eligible_orders_changes_nothing(): void
    {
        $done = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_DONE]);

        $this->actingAs($this->admin)->post('/admin/work-orders/bulk', [
            'action' => 'pause',
            'ids' => [$done->id],
        ]);

        $this->assertSame(WorkOrder::STATUS_DONE, $done->fresh()->status);
    }

    // ── Validation ───────────────────────────────────────────────────────────

    public function test_an_unknown_action_is_rejected(): void
    {
        $order = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_PENDING]);

        $response = $this->actingAs($this->admin)->post('/admin/work-orders/bulk', [
            'action' => 'delete_everything',
            'ids' => [$order->id],
        ]);

        $response->assertSessionHasErrors('action');
        $this->assertSame(WorkOrder::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_an_empty_selection_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/work-orders/bulk', [
            'action' => 'accept',
            'ids' => [],
        ]);

        $response->assertSessionHasErrors('ids');
    }

    public function test_unknown_work_order_ids_are_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/work-orders/bulk', [
            'action' => 'accept',
            'ids' => [999999],
        ]);

        $response->assertSessionHasErrors('ids.0');
    }

    public function test_soft_deleted_work_orders_cannot_be_bulk_transitioned(): void
    {
        $order = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_PENDING]);
        $order->delete();

        $response = $this->actingAs($this->admin)->post('/admin/work-orders/bulk', [
            'action' => 'accept',
            'ids' => [$order->id],
        ]);

        $response->assertSessionHasErrors('ids.0');
    }

    // ── Authorization ────────────────────────────────────────────────────────

    public function test_guest_cannot_bulk_transition_work_orders(): void
    {
        $order = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_PENDING]);

        $response = $this->post('/admin/work-orders/bulk', [
            'action' => 'accept',
            'ids' => [$order->id],
        ]);

        $response->assertRedirect('/login');
        $this->assertSame(WorkOrder::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_operator_cannot_bulk_transition_work_orders(): void
    {
        $order = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_PENDING]);

        $response = $this->actingAs($this->operator)->post('/admin/work-orders/bulk', [
            'action' => 'accept',
            'ids' => [$order->id],
        ]);

        $response->assertStatus(403);
        $this->assertSame(WorkOrder::STATUS_PENDING, $order->fresh()->status);
    }
}
