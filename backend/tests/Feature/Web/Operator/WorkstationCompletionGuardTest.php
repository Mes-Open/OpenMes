<?php

namespace Tests\Feature\Web\Operator;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Issue;
use App\Models\Line;
use App\Models\LineStatus;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\Shift;
use App\Models\TemplateStep;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderShiftEntry;
use App\Services\WorkOrder\WorkOrderService;
use App\Support\ProductionFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Whole-batch flow: the workstation's quantity entry ("+ produced", shift entry)
 * closes an order once the plan is reached. For an order routed through process
 * steps that must not skip the steps, their checklists or an open block — the
 * order closes through its batches, or once every batch is done.
 */
class WorkstationCompletionGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Operator', 'web');
        $this->operator = tap(User::factory()->create(), fn (User $u) => $u->assignRole('Operator'));
        $this->line = Line::factory()->create();
        ProductionFlow::set(ProductionFlow::WHOLE_BATCH);
    }

    private function actingAsOperator(): static
    {
        return $this->actingAs($this->operator)->withSession(['selected_line_id' => $this->line->id]);
    }

    /** An order of 10 routed through two steps (Mix, Pack). */
    private function routedOrder(float $planned = 10): WorkOrder
    {
        $pt = ProductType::factory()->create();
        $template = ProcessTemplate::factory()->create(['product_type_id' => $pt->id, 'is_active' => true]);
        foreach ([1 => 'Mix', 2 => 'Pack'] as $n => $name) {
            TemplateStep::factory()->create(['process_template_id' => $template->id, 'step_number' => $n, 'name' => $name]);
        }

        return WorkOrder::factory()->create([
            'line_id' => $this->line->id,
            'product_type_id' => $pt->id,
            'planned_qty' => $planned,
            'produced_qty' => 0,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
            'process_snapshot' => $template->load('steps')->toSnapshot(),
        ]);
    }

    private function batch(WorkOrder $order, float $qty): Batch
    {
        return app(WorkOrderService::class)->createBatch($order, $qty);
    }

    private function finish(Batch $batch): void
    {
        $batch->steps()->update(['status' => BatchStep::STATUS_DONE, 'completed_at' => now()]);
        $batch->update(['status' => Batch::STATUS_DONE, 'completed_at' => now(), 'produced_qty' => $batch->target_qty]);
    }

    private function shift(): Shift
    {
        return Shift::create(['line_id' => $this->line->id, 'name' => 'Day', 'code' => 'D', 'start_time' => '06:00', 'end_time' => '14:00', 'is_active' => true]);
    }

    private function assertStillOpen(WorkOrder $order, float $produced = 0): void
    {
        $fresh = $order->fresh();
        $this->assertSame(WorkOrder::STATUS_IN_PROGRESS, $fresh->status);
        $this->assertNull($fresh->completed_at);
        $this->assertEquals($produced, $fresh->produced_qty);
    }

    // ── Refused: the quantity would close an order with open process work ───

    public function test_quantity_entry_cannot_complete_an_order_with_a_running_batch(): void
    {
        $order = $this->routedOrder();
        $this->batch($order, 10)->update(['status' => Batch::STATUS_IN_PROGRESS]);

        $this->actingAsOperator()->post("/operator/workstation/{$order->id}/complete", ['produced_qty' => 10])
            ->assertSessionHasErrors(['produced_qty' => __('This work order cannot be completed while batch(es) :batches still have process steps or checklists to finish.', ['batches' => '#1'])]);

        $this->assertStillOpen($order);
    }

    public function test_quantity_entry_names_every_unfinished_batch(): void
    {
        $order = $this->routedOrder();
        $this->finish($this->batch($order, 4));
        $this->batch($order, 3);
        $this->batch($order, 3)->update(['status' => Batch::STATUS_IN_PROGRESS]);

        $this->actingAsOperator()->post("/operator/workstation/{$order->id}/complete", ['produced_qty' => 10])
            ->assertSessionHasErrors(['produced_qty' => __('This work order cannot be completed while batch(es) :batches still have process steps or checklists to finish.', ['batches' => '#2, #3'])]);

        $this->assertStillOpen($order);
    }

    public function test_quantity_entry_cannot_complete_a_routed_order_with_no_batch(): void
    {
        $order = $this->routedOrder();

        $this->actingAsOperator()->post("/operator/workstation/{$order->id}/complete", ['produced_qty' => 10])
            ->assertSessionHasErrors(['produced_qty' => __('This work order cannot be completed until its production has gone through the process steps of a batch.')]);

        $this->assertStillOpen($order);
    }

    public function test_quantity_entry_cannot_complete_a_blocked_order(): void
    {
        $order = $this->routedOrder();
        $this->finish($this->batch($order, 5));
        Issue::factory()->create(['work_order_id' => $order->id, 'blocks_work_order' => true]);
        $order->update(['status' => WorkOrder::STATUS_IN_PROGRESS]);

        $this->actingAsOperator()->post("/operator/workstation/{$order->id}/complete", ['produced_qty' => 10])
            ->assertSessionHasErrors(['produced_qty' => __('This work order is blocked by an open issue and cannot be completed.')]);

        $this->assertNotSame(WorkOrder::STATUS_DONE, $order->fresh()->status);
        $this->assertEquals(0, $order->fresh()->produced_qty);
    }

    public function test_shift_entry_cannot_complete_an_order_with_a_running_batch(): void
    {
        $order = $this->routedOrder();
        $this->batch($order, 10)->update(['status' => Batch::STATUS_IN_PROGRESS]);

        $this->actingAsOperator()->post("/operator/workstation/{$order->id}/shift-entry", ['shift_id' => $this->shift()->id, 'quantity' => 10])
            ->assertSessionHasErrors('quantity');

        $this->assertStillOpen($order);
        $this->assertSame(0, WorkOrderShiftEntry::count(), 'A refused entry must not leave a shift row behind');
    }

    public function test_quantity_entry_cannot_skip_a_pending_step_of_a_done_batch(): void
    {
        $order = $this->routedOrder();
        $batch = $this->batch($order, 10);
        $this->finish($batch);
        $batch->steps()->where('step_number', 2)->update(['status' => BatchStep::STATUS_PENDING, 'completed_at' => null]);

        $this->actingAsOperator()->post("/operator/workstation/{$order->id}/complete", ['produced_qty' => 10])
            ->assertSessionHasErrors('produced_qty');

        $this->assertStillOpen($order);
    }

    public function test_done_board_column_cannot_complete_an_order_with_a_running_batch(): void
    {
        DB::table('system_settings')->updateOrInsert(['key' => 'workflow_mode'], ['value' => json_encode('board_status')]);
        $done = LineStatus::create(['name' => 'Done', 'color' => '#22c55e', 'sort_order' => 9, 'line_id' => $this->line->id, 'is_done_status' => true]);
        $order = $this->routedOrder();
        $batch = $this->batch($order, 10);
        $batch->update(['status' => Batch::STATUS_IN_PROGRESS]);
        $move = fn () => $this->actingAsOperator()->post("/operator/work-order/{$order->id}/line-status", ['line_status_id' => $done->id, 'produced_qty' => 10]);

        $move()->assertSessionHas('error', __('This work order cannot be completed while batch(es) :batches still have process steps or checklists to finish.', ['batches' => '#1']));
        $this->assertStillOpen($order);
        $this->assertNull($order->fresh()->line_status_id);

        $this->finish($batch);
        $move()->assertSessionMissing('error');
        $this->assertSame(WorkOrder::STATUS_DONE, $order->fresh()->status);
    }

    public function test_json_request_gets_a_422(): void
    {
        $order = $this->routedOrder();
        $this->batch($order, 10);

        $this->actingAsOperator()->postJson("/operator/workstation/{$order->id}/complete", ['produced_qty' => 10])
            ->assertStatus(422)
            ->assertJsonValidationErrors('produced_qty');

        $this->assertStillOpen($order);
    }

    // ── Allowed ──────────────────────────────────────────────────────────────

    public function test_partial_quantity_below_the_plan_is_still_recorded(): void
    {
        $order = $this->routedOrder();
        $this->batch($order, 10);

        $this->actingAsOperator()->post("/operator/workstation/{$order->id}/complete", ['produced_qty' => 4])
            ->assertSessionHasNoErrors();

        $this->assertStillOpen($order, 4);
    }

    public function test_quantity_entry_completes_once_every_live_batch_is_done(): void
    {
        $order = $this->routedOrder();
        $this->finish($this->batch($order, 6));
        $this->batch($order, 4)->update(['status' => Batch::STATUS_CANCELLED]);

        $this->actingAsOperator()->post("/operator/workstation/{$order->id}/complete", ['produced_qty' => 10])
            ->assertSessionHasNoErrors();

        $this->assertSame(WorkOrder::STATUS_DONE, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->completed_at);
    }

    public function test_order_without_process_steps_keeps_completing_from_the_quantity(): void
    {
        $order = WorkOrder::factory()->create([
            'line_id' => $this->line->id,
            'planned_qty' => 10,
            'produced_qty' => 0,
            'process_snapshot' => ['steps' => []],
        ]);

        $this->actingAsOperator()->post("/operator/workstation/{$order->id}/complete", ['produced_qty' => 10])
            ->assertSessionHasNoErrors();
        $this->assertSame(WorkOrder::STATUS_DONE, $order->fresh()->status);

        $other = WorkOrder::factory()->create([
            'line_id' => $this->line->id,
            'planned_qty' => 5,
            'produced_qty' => 0,
            'process_snapshot' => ['steps' => []],
        ]);
        $this->actingAsOperator()->post("/operator/workstation/{$other->id}/shift-entry", ['shift_id' => $this->shift()->id, 'quantity' => 5])
            ->assertSessionHasNoErrors();
        $this->assertSame(WorkOrder::STATUS_DONE, $other->fresh()->status);
    }

    // ── Validation & access ─────────────────────────────────────────────────

    public function test_quantity_is_validated(): void
    {
        $order = $this->routedOrder();

        $this->actingAsOperator()->postJson("/operator/workstation/{$order->id}/complete", [])
            ->assertStatus(422)->assertJsonValidationErrors('produced_qty');
        $this->actingAsOperator()->postJson("/operator/workstation/{$order->id}/complete", ['produced_qty' => 0])
            ->assertStatus(422)->assertJsonValidationErrors('produced_qty');
        $this->actingAsOperator()->postJson("/operator/workstation/{$order->id}/shift-entry", ['shift_id' => 999999, 'quantity' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors(['shift_id', 'quantity']);

        $this->assertStillOpen($order);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $order = $this->routedOrder();

        $this->post("/operator/workstation/{$order->id}/complete", ['produced_qty' => 10])->assertRedirect('/login');
        $this->post("/operator/workstation/{$order->id}/shift-entry", ['shift_id' => $this->shift()->id, 'quantity' => 10])->assertRedirect('/login');

        $this->assertStillOpen($order);
    }

    public function test_user_without_a_production_role_is_forbidden(): void
    {
        $order = $this->routedOrder();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->withSession(['selected_line_id' => $this->line->id])
            ->post("/operator/workstation/{$order->id}/complete", ['produced_qty' => 10])
            ->assertForbidden();

        $this->assertStillOpen($order);
    }
}
