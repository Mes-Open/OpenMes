<?php

namespace Tests\Feature\WorkOrder;

use App\Models\Batch;
use App\Models\MachineConnection;
use App\Models\MachineTopic;
use App\Models\TopicMapping;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Connectivity\ActionExecutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Closing an order by hand (supervisor, admin, API, machine message) must not
 * leave a pending or running batch behind on a DONE order. Cancelled batches
 * don't count; an order without batches closes as before.
 */
class CompleteWithOpenBatchesTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    private User $admin;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->supervisor = tap(User::factory()->create(), fn (User $u) => $u->assignRole('Supervisor'));
        $this->admin = tap(User::factory()->create(), fn (User $u) => $u->assignRole('Admin'));
        $this->operator = tap(User::factory()->create(), fn (User $u) => $u->assignRole('Operator'));
    }

    private function order(): WorkOrder
    {
        return WorkOrder::factory()->create(['status' => WorkOrder::STATUS_IN_PROGRESS, 'planned_qty' => 10, 'produced_qty' => 0]);
    }

    private function batch(WorkOrder $order, string $status, int $number): Batch
    {
        return Batch::factory()->create(['work_order_id' => $order->id, 'batch_number' => $number, 'status' => $status, 'target_qty' => 5]);
    }

    private function refusal(int $count): string
    {
        return __('This work order still has :count batch(es) that are not done. Finish or cancel them before completing the order.', ['count' => $count]);
    }

    private function assertStillOpen(WorkOrder $order): void
    {
        $fresh = $order->fresh();
        $this->assertSame(WorkOrder::STATUS_IN_PROGRESS, $fresh->status);
        $this->assertNull($fresh->completed_at);
    }

    // ── Refused ─────────────────────────────────────────────────────────────

    public function test_supervisor_cannot_complete_with_pending_and_running_batches(): void
    {
        $order = $this->order();
        $this->batch($order, Batch::STATUS_DONE, 1);
        $this->batch($order, Batch::STATUS_PENDING, 2);
        $this->batch($order, Batch::STATUS_IN_PROGRESS, 3);

        $this->actingAs($this->supervisor)
            ->post("/supervisor/work-orders/{$order->id}/complete", ['produced_qty' => 10])
            ->assertRedirect()
            ->assertSessionHas('error', $this->refusal(2));

        $this->assertStillOpen($order);
        $this->assertEquals(0, $order->fresh()->produced_qty);
    }

    public function test_admin_cannot_complete_with_an_open_batch(): void
    {
        $order = $this->order();
        $this->batch($order, Batch::STATUS_PENDING, 1);

        $this->actingAs($this->admin)
            ->post("/admin/work-orders/{$order->id}/complete", ['produced_qty' => 10])
            ->assertSessionHas('error', $this->refusal(1));

        $this->assertStillOpen($order);
    }

    public function test_api_complete_with_an_open_batch_is_a_422(): void
    {
        $order = $this->order();
        $this->batch($order, Batch::STATUS_IN_PROGRESS, 1);

        $this->actingAs($this->supervisor, 'sanctum')
            ->postJson("/api/v1/work-orders/{$order->id}/complete")
            ->assertStatus(422)
            ->assertJsonPath('message', $this->refusal(1));

        $this->assertStillOpen($order);
    }

    public function test_machine_message_cannot_complete_with_an_open_batch(): void
    {
        $order = $this->order();
        $this->batch($order, Batch::STATUS_PENDING, 1);
        $connection = MachineConnection::create(['name' => 'Line PLC', 'protocol' => 'rest']);
        $topic = MachineTopic::create(['machine_connection_id' => $connection->id, 'topic_pattern' => 'status', 'is_active' => true]);
        $mapping = TopicMapping::create(['machine_topic_id' => $topic->id, 'action_type' => 'set_work_order_status', 'action_params' => ['order_id' => $order->id, 'status' => 'completed'], 'is_active' => true]);

        $result = app(ActionExecutor::class)->executeSingle($mapping, []);

        $this->assertSame('error', $result['status']);
        $this->assertStillOpen($order);
    }

    // ── Allowed ─────────────────────────────────────────────────────────────

    public function test_supervisor_completes_once_every_live_batch_is_done(): void
    {
        $order = $this->order();
        $this->batch($order, Batch::STATUS_DONE, 1);
        $this->batch($order, Batch::STATUS_CANCELLED, 2);

        $this->actingAs($this->supervisor)
            ->post("/supervisor/work-orders/{$order->id}/complete", ['produced_qty' => 5])
            ->assertSessionHas('success');

        $fresh = $order->fresh();
        $this->assertSame(WorkOrder::STATUS_DONE, $fresh->status);
        $this->assertEquals(5, $fresh->produced_qty);
        $this->assertNotNull($fresh->completed_at);
    }

    public function test_order_without_batches_completes_on_every_path(): void
    {
        $order = $this->order();
        $this->actingAs($this->supervisor)
            ->post("/supervisor/work-orders/{$order->id}/complete", ['produced_qty' => 10])
            ->assertSessionHas('success');
        $this->assertSame(WorkOrder::STATUS_DONE, $order->fresh()->status);

        $other = $this->order();
        $this->actingAs($this->admin)
            ->post("/admin/work-orders/{$other->id}/complete", ['produced_qty' => 10])
            ->assertSessionHas('success');
        $this->assertSame(WorkOrder::STATUS_DONE, $other->fresh()->status);

        $third = $this->order();
        $this->actingAs($this->supervisor, 'sanctum')
            ->postJson("/api/v1/work-orders/{$third->id}/complete")
            ->assertOk();
        $this->assertSame(WorkOrder::STATUS_DONE, $third->fresh()->status);
    }

    public function test_status_check_still_comes_first(): void
    {
        $order = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_PENDING]);
        $this->batch($order, Batch::STATUS_PENDING, 1);

        $this->actingAs($this->supervisor)
            ->post("/supervisor/work-orders/{$order->id}/complete", ['produced_qty' => 10])
            ->assertSessionHas('error', 'Only IN_PROGRESS work orders can be completed.');
    }

    // ── Validation & access ─────────────────────────────────────────────────

    public function test_produced_quantity_is_validated(): void
    {
        $order = $this->order();

        foreach (['supervisor' => $this->supervisor, 'admin' => $this->admin] as $area => $user) {
            $this->actingAs($user)->postJson("/{$area}/work-orders/{$order->id}/complete", [])
                ->assertStatus(422)->assertJsonValidationErrors('produced_qty');
            $this->actingAs($user)->postJson("/{$area}/work-orders/{$order->id}/complete", ['produced_qty' => 0])
                ->assertStatus(422)->assertJsonValidationErrors('produced_qty');
            $this->actingAs($user)->postJson("/{$area}/work-orders/{$order->id}/complete", ['produced_qty' => 100000000])
                ->assertStatus(422)->assertJsonValidationErrors('produced_qty');
        }

        $this->assertStillOpen($order);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $order = $this->order();

        $this->post("/supervisor/work-orders/{$order->id}/complete", ['produced_qty' => 10])->assertRedirect('/login');
        $this->post("/admin/work-orders/{$order->id}/complete", ['produced_qty' => 10])->assertRedirect('/login');
        $this->postJson("/api/v1/work-orders/{$order->id}/complete")->assertUnauthorized();

        $this->assertStillOpen($order);
    }

    public function test_operator_is_forbidden(): void
    {
        $order = $this->order();

        $this->actingAs($this->operator)->post("/supervisor/work-orders/{$order->id}/complete", ['produced_qty' => 10])->assertForbidden();
        $this->actingAs($this->operator)->post("/admin/work-orders/{$order->id}/complete", ['produced_qty' => 10])->assertForbidden();
        $this->actingAs($this->operator, 'sanctum')->postJson("/api/v1/work-orders/{$order->id}/complete")->assertForbidden();

        $this->assertStillOpen($order);
    }
}
