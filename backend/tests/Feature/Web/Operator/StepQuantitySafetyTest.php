<?php

namespace Tests\Feature\Web\Operator;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Line;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\TemplateStep;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrder\BatchService;
use App\Services\WorkOrder\WorkOrderService;
use App\Support\ProductionFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Per-step quantity ledger and the transfer production flow: pieces logged as
 * good at one station open the next station while the first is still working;
 * scrap logged at a step never reaches the next one; a step can only be
 * finished once nothing more can arrive and nothing is left waiting.
 */
class StepQuantitySafetyTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private Line $line;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Operator', 'web');
        $this->operator = User::factory()->create();
        $this->operator->assignRole('Operator');
        $this->line = Line::factory()->create();
    }

    /** One batch of 10 through three plain sequential steps. */
    private function makeBatch(float $qty = 10): Batch
    {
        $pt = ProductType::factory()->create();
        $template = ProcessTemplate::factory()->create(['product_type_id' => $pt->id, 'is_active' => true]);
        foreach ([1 => 'Mix', 2 => 'Bake', 3 => 'Pack'] as $n => $name) {
            TemplateStep::factory()->create(['process_template_id' => $template->id, 'step_number' => $n, 'name' => $name]);
        }

        $wo = WorkOrder::factory()->create([
            'line_id' => $this->line->id,
            'planned_qty' => $qty,
            'process_snapshot' => $template->load('steps')->toSnapshot(),
        ]);

        return app(WorkOrderService::class)->createBatch($wo, $qty);
    }

    private function step(Batch $batch, int $number): BatchStep
    {
        return $batch->steps()->where('step_number', $number)->first();
    }

    private function actingAsOperator(): static
    {
        return $this->actingAs($this->operator)->withSession(['selected_line_id' => $this->line->id]);
    }

    public function test_skipping_partially_scrapped_step_preserves_material_balance(): void
    {
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $batch = $this->makeBatch();
        $service = app(BatchService::class);
        $step = $this->step($batch, 1);
        $step->update(['is_optional' => true]);
        $service->startStep($step->fresh(), $this->operator);
        $service->recordQuantity($step->fresh(), $this->operator, 4, 2);
        try {
            $service->skipStep($step->fresh(), $this->operator);
            $this->fail('A processed step was skipped.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('cannot be skipped', $e->getMessage());
        }
        $this->assertSame(BatchStep::STATUS_IN_PROGRESS, $step->fresh()->status);
        $this->assertLessThanOrEqual(8, $this->step($batch, 2)->incomingQty(), 'Skipping must not resurrect the 2 scrapped pieces');
    }

    public function test_legacy_quantity_entry_cannot_close_running_transfer_order(): void
    {
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $batch = $this->makeBatch();
        app(BatchService::class)->startStep($this->step($batch, 1), $this->operator);
        $this->actingAsOperator()->post('/operator/workstation/'.$batch->work_order_id.'/complete', ['produced_qty' => 10])->assertSessionHasErrors('produced_qty');
        $this->assertEquals(0, $batch->workOrder->fresh()->produced_qty, 'Only final-step output may count as produced');
        $this->assertSame(WorkOrder::STATUS_IN_PROGRESS, $batch->workOrder->fresh()->status);
    }

    public function test_skipping_final_step_rolls_up_finished_output(): void
    {
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $batch = $this->makeBatch();
        $service = app(BatchService::class);
        foreach ([1, 2] as $n) {
            $service->startStep($this->step($batch, $n), $this->operator);
            $service->recordQuantity($this->step($batch, $n), $this->operator, 10);
            $service->completeStep($this->step($batch, $n), $this->operator);
        }
        $last = $this->step($batch, 3);
        $last->update(['is_optional' => true]);
        $service->skipStep($last->fresh(), $this->operator);
        $this->assertSame(Batch::STATUS_DONE, $batch->fresh()->status);
        $this->assertEquals(0, $last->fresh()->availableQty());
        $this->assertEquals(10, $batch->fresh()->produced_qty, 'Final effective step passed 10 but finished batch remains at zero');
    }

    public function test_blocked_work_order_rejects_quantity_logs(): void
    {
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $batch = $this->makeBatch();
        $service = app(BatchService::class);
        $service->startStep($this->step($batch, 1), $this->operator);
        $type = \App\Models\IssueType::factory()->blocking()->create();
        \App\Models\Issue::factory()->create(['work_order_id' => $batch->work_order_id, 'issue_type_id' => $type->id, 'status' => 'OPEN']);
        $this->assertTrue($batch->workOrder->fresh()->isBlocked());
        try {
            $service->recordQuantity($this->step($batch, 1), $this->operator, 4);
            $this->fail('A blocked order accepted output.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('blocking issues', $e->getMessage());
        }
        $this->assertEquals(0, $this->step($batch, 1)->passed_qty, 'A blocked order must not keep moving pieces downstream');
    }

    public function test_editing_scrap_entry_keeps_step_ledger_consistent(): void
    {
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $batch = $this->makeBatch();
        $service = app(BatchService::class);
        $service->startStep($this->step($batch, 1), $this->operator);
        $service->recordQuantity($this->step($batch, 1), $this->operator, 0, 2);
        $entry = \App\Models\ScrapEntry::where('batch_step_id', $this->step($batch, 1)->id)->firstOrFail();
        \Laravel\Sanctum\Sanctum::actingAs($this->operator, ['*']);
        $this->patchJson('/api/v1/scrap-entries/'.$entry->id, ['quantity' => 3])->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->assertEquals(2, $this->step($batch, 1)->scrap_qty);
        $this->assertEquals(2, $entry->fresh()->quantity);
    }

    public function test_shift_entry_and_correction_cannot_override_step_output(): void
    {
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $batch = $this->makeBatch();
        $shift = \App\Models\Shift::create(['line_id' => $this->line->id, 'name' => 'Day', 'code' => 'D', 'start_time' => '06:00', 'end_time' => '14:00', 'is_active' => true]);
        $this->actingAsOperator()->post('/operator/workstation/'.$batch->work_order_id.'/shift-entry', ['shift_id' => $shift->id, 'quantity' => 10])
            ->assertSessionHasErrors('quantity');
        $entry = \App\Models\WorkOrderShiftEntry::create(['work_order_id' => $batch->work_order_id, 'shift_id' => $shift->id, 'quantity' => 1, 'user_id' => $this->operator->id, 'production_date' => today()]);
        $this->get('/operator/shift-entry/'.$entry->id.'/correct')->assertForbidden();
        $this->put('/operator/shift-entry/'.$entry->id.'/correct', ['quantity' => 10])->assertForbidden();
        $this->assertEquals(1, $entry->fresh()->quantity);
        $this->assertEquals(0, $batch->workOrder->fresh()->produced_qty);
    }

    public function test_unrouted_order_keeps_manual_entry_in_transfer_mode(): void
    {
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $order = WorkOrder::factory()->create(['line_id' => $this->line->id, 'process_snapshot' => ['steps' => []], 'planned_qty' => 10]);
        $this->actingAsOperator()->post('/operator/workstation/'.$order->id.'/complete', ['produced_qty' => 4])->assertSessionHasNoErrors();
        $this->assertEquals(4, $order->fresh()->produced_qty);
    }

    public function test_blocking_quality_control_stops_operator_and_machine_logs(): void
    {
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $batch = $this->makeBatch();
        $service = app(BatchService::class);
        $service->startStep($this->step($batch, 1), $this->operator);
        $trigger = \App\Models\QualityControlTrigger::factory()->blocking()->create();
        \App\Models\QualityControlTask::factory()->create(['quality_control_trigger_id' => $trigger->id, 'work_order_id' => $batch->work_order_id, 'batch_id' => $batch->id]);
        $this->actingAsOperator()->post('/operator/batch-step/'.$this->step($batch, 1)->id.'/quantity', ['good_qty' => 4])
            ->assertSessionHasErrors('good_qty');
        $this->assertEquals(0, $service->recordMachinePass($this->step($batch, 1), 4));
        $this->assertEquals(0, $this->step($batch, 1)->passed_qty);
    }

    public function test_step_scrap_can_be_classified_but_not_moved_or_deleted(): void
    {
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $batch = $this->makeBatch();
        $service = app(BatchService::class);
        $service->startStep($this->step($batch, 1), $this->operator);
        $service->recordQuantity($this->step($batch, 1), $this->operator, 0, 2);
        $entry = \App\Models\ScrapEntry::where('batch_step_id', $this->step($batch, 1)->id)->firstOrFail();
        $reason = \App\Models\ScrapReason::factory()->create(['is_active' => true]);
        \Laravel\Sanctum\Sanctum::actingAs($this->operator, ['*']);
        $this->patchJson('/api/v1/scrap-entries/'.$entry->id, ['scrap_reason_id' => $reason->id, 'notes' => 'Classified later'])->assertOk();
        $this->assertEquals($reason->id, $entry->fresh()->scrap_reason_id);
        $this->patchJson('/api/v1/scrap-entries/'.$entry->id, ['batch_step_id' => null])->assertUnprocessable();
        $this->patchJson('/api/v1/scrap-entries/'.$entry->id, ['batch_step_id' => $this->step($batch, 2)->id])->assertUnprocessable();
        Role::findOrCreate('Admin', 'web');
        $this->operator->assignRole('Admin');
        $this->deleteJson('/api/v1/scrap-entries/'.$entry->id)->assertUnprocessable();
        $this->assertNotNull($entry->fresh());
        $this->assertEquals(2, $this->step($batch, 1)->scrap_qty);
    }

    public function test_variant_switch_cannot_discard_recorded_scrap(): void
    {
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $batch = $this->makeBatch();
        $first = $this->step($batch, 1);
        $first->update(['variant_group' => 'finish']);
        $second = $this->step($batch, 2);
        $second->update(['variant_group' => 'finish', 'status' => BatchStep::STATUS_SKIPPED]);
        $service = app(BatchService::class);
        $service->startStep($first->fresh(), $this->operator);
        $service->recordQuantity($first->fresh(), $this->operator, 0, 2);
        $this->expectException(\DomainException::class);
        $service->chooseVariant($second->fresh(), $this->operator);
    }

    public function test_done_board_status_cannot_override_step_output(): void
    {
        ProductionFlow::set(ProductionFlow::TRANSFER);
        DB::table('system_settings')->updateOrInsert(['key' => 'workflow_mode'], ['value' => json_encode('board_status')]);
        $batch = $this->makeBatch();
        $status = \App\Models\LineStatus::create(['name' => 'Done', 'is_done_status' => true]);
        $this->actingAsOperator()->post('/operator/work-order/'.$batch->work_order_id.'/line-status', ['line_status_id' => $status->id, 'produced_qty' => 10])
            ->assertSessionHas('error', 'Record production on the work order steps.');
        $this->assertNotSame(WorkOrder::STATUS_DONE, $batch->workOrder->fresh()->status);
        $this->assertEquals(0, $batch->workOrder->fresh()->produced_qty);
    }

    public function test_generic_scrap_forms_cannot_bypass_step_ledger(): void
    {
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $batch = $this->makeBatch();
        $reason = \App\Models\ScrapReason::factory()->create(['is_active' => true]);
        $data = ['work_order_id' => $batch->work_order_id, 'scrap_reason_id' => $reason->id, 'quantity' => 2];
        $this->actingAsOperator()->post('/operator/scrap', $data)->assertSessionHasErrors('quantity');
        \Laravel\Sanctum\Sanctum::actingAs($this->operator, ['*']);
        $this->postJson('/api/v1/work-orders/'.$batch->work_order_id.'/scrap-entries', $data)->assertUnprocessable();
        $this->assertDatabaseMissing('scrap_entries', ['work_order_id' => $batch->work_order_id]);
    }

    public function test_machine_cannot_direct_count_a_routed_order_before_its_first_batch(): void
    {
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $order = WorkOrder::factory()->create(['counting_source' => 'machine', 'process_snapshot' => ['steps' => [['step_number' => 1]]]]);
        $machine = app(\App\Services\WorkOrder\MachineProductionService::class);
        $this->assertFalse($machine->recordGoodCount($order, 10));
        $this->assertFalse($machine->recordAbsoluteCount($order->fresh(), 10));
        $this->assertEquals(0, $order->fresh()->produced_qty);
    }

    public function test_source_free_absolute_machine_reading_is_rejected(): void
    {
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $batch = $this->makeBatch();
        $batch->workOrder->update(['counting_source' => 'machine']);
        $service = app(BatchService::class);
        foreach ([1, 2] as $n) {
            $service->startStep($this->step($batch, $n), $this->operator);
            $service->recordQuantity($this->step($batch, $n), $this->operator, 10);
            $service->completeStep($this->step($batch, $n), $this->operator);
        }
        $service->startStep($this->step($batch, 3), $this->operator);
        // A raw absolute reading without a persistent source is rejected.
        $machine = app(\App\Services\WorkOrder\MachineProductionService::class);
        $this->assertFalse($machine->recordAbsoluteCount($batch->workOrder, 4));
        $this->assertEquals(0, $this->step($batch, 3)->passed_qty);
        $this->assertEquals(0, $batch->workOrder->fresh()->produced_qty);
    }
}
