<?php

namespace Tests\Feature\Web\Operator;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Line;
use App\Models\MachineConnection;
use App\Models\MachineTopic;
use App\Models\ProcessTemplate;
use App\Models\ProductType;
use App\Models\TemplateStep;
use App\Models\TopicMapping;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Services\Connectivity\ActionExecutor;
use App\Services\WorkOrder\BatchService;
use App\Services\WorkOrder\MachineProductionService;
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
class StepQuantityFlowTest extends TestCase
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

    // ── Transfer flow ────────────────────────────────────────────────────

    public function test_good_pieces_open_the_next_step_while_the_first_is_still_running(): void
    {
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $batch = $this->makeBatch();
        $service = app(BatchService::class);

        $service->startStep($this->step($batch, 1), $this->operator);
        $this->assertSame(BatchStep::STATUS_PENDING, $this->step($batch, 2)->status);

        $service->recordQuantity($this->step($batch, 1), $this->operator, good: 4);

        $this->assertSame(BatchStep::STATUS_IN_PROGRESS, $this->step($batch, 1)->status);
        $this->assertSame(BatchStep::STATUS_READY, $this->step($batch, 2)->status);
        $this->assertSame(BatchStep::STATUS_PENDING, $this->step($batch, 3)->status);
        $this->assertEquals(4.0, $this->step($batch, 2)->incomingQty());
        $this->assertEquals(4.0, $this->step($batch, 2)->availableQty());
        $this->assertEquals(6.0, $this->step($batch, 1)->availableQty());

        // Both stations work at once.
        $service->startStep($this->step($batch, 2), $this->operator);
        $this->assertSame(BatchStep::STATUS_IN_PROGRESS, $this->step($batch, 1)->status);
        $this->assertSame(BatchStep::STATUS_IN_PROGRESS, $this->step($batch, 2)->status);
    }

    public function test_scrap_does_not_flow_to_the_next_step_and_is_recorded_without_a_reason(): void
    {
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $batch = $this->makeBatch();
        $service = app(BatchService::class);

        $service->startStep($this->step($batch, 1), $this->operator);
        $service->recordQuantity($this->step($batch, 1), $this->operator, good: 7, scrap: 3, notes: 'burnt');

        $this->assertEquals(7.0, $this->step($batch, 1)->passed_qty);
        $this->assertEquals(3.0, $this->step($batch, 1)->scrap_qty);
        $this->assertEquals(0.0, $this->step($batch, 1)->availableQty());
        $this->assertEquals(7.0, $this->step($batch, 2)->incomingQty());

        $this->assertDatabaseHas('scrap_entries', [
            'work_order_id' => $batch->work_order_id,
            'batch_step_id' => $this->step($batch, 1)->id,
            'scrap_reason_id' => null,
            'quantity' => 3,
            'notes' => 'burnt',
            'reported_by' => $this->operator->id,
        ]);
    }

    public function test_cannot_log_more_than_is_waiting_at_the_step(): void
    {
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $batch = $this->makeBatch();
        $service = app(BatchService::class);
        $service->startStep($this->step($batch, 1), $this->operator);
        $service->recordQuantity($this->step($batch, 1), $this->operator, good: 8);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only 2 pieces are waiting at this step.');
        $service->recordQuantity($this->step($batch, 1), $this->operator, good: 3);
    }

    public function test_cannot_log_on_a_step_that_is_not_running(): void
    {
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $batch = $this->makeBatch();

        $this->expectException(\DomainException::class);
        app(BatchService::class)->recordQuantity($this->step($batch, 1), $this->operator, good: 1);
    }

    public function test_finishing_is_blocked_while_pieces_can_still_arrive_or_are_waiting(): void
    {
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $batch = $this->makeBatch();
        $service = app(BatchService::class);

        $service->startStep($this->step($batch, 1), $this->operator);
        $service->recordQuantity($this->step($batch, 1), $this->operator, good: 4);
        $service->startStep($this->step($batch, 2), $this->operator);
        $service->recordQuantity($this->step($batch, 2), $this->operator, good: 4);

        // Step 2 has nothing waiting, but step 1 is still open: more can arrive.
        try {
            $service->completeStep($this->step($batch, 2), $this->operator);
            $this->fail('Step 2 should not finish while step 1 is still running.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('can still arrive from Mix', $e->getMessage());
        }

        // Step 1 has 6 waiting: it cannot finish either.
        try {
            $service->completeStep($this->step($batch, 1), $this->operator);
            $this->fail('Step 1 should not finish with pieces waiting.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('6 pieces are still waiting', $e->getMessage());
        }
        $this->assertSame(BatchStep::STATUS_IN_PROGRESS, $this->step($batch, 1)->status);

        $service->recordQuantity($this->step($batch, 1), $this->operator, good: 6);
        $service->completeStep($this->step($batch, 1), $this->operator);
        $this->assertSame(BatchStep::STATUS_DONE, $this->step($batch, 1)->status);

        // Now step 2 has 6 more waiting; log them and it can finish.
        $this->assertEquals(6.0, $this->step($batch, 2)->availableQty());
        $service->recordQuantity($this->step($batch, 2), $this->operator, good: 6);
        $service->completeStep($this->step($batch, 2), $this->operator);
        $this->assertSame(BatchStep::STATUS_DONE, $this->step($batch, 2)->status);
    }

    public function test_produced_quantity_follows_the_last_step_live_and_closes_with_the_batch(): void
    {
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $batch = $this->makeBatch();
        $service = app(BatchService::class);
        $wo = $batch->workOrder;

        $service->startStep($this->step($batch, 1), $this->operator);
        $service->recordQuantity($this->step($batch, 1), $this->operator, good: 9, scrap: 1);
        $service->completeStep($this->step($batch, 1), $this->operator);
        $service->startStep($this->step($batch, 2), $this->operator);
        $service->recordQuantity($this->step($batch, 2), $this->operator, good: 9);
        $service->completeStep($this->step($batch, 2), $this->operator);
        $service->startStep($this->step($batch, 3), $this->operator);

        // Nothing has left the last step yet.
        $this->assertEquals(0.0, $wo->fresh()->produced_qty);

        $service->recordQuantity($this->step($batch, 3), $this->operator, good: 5);
        $this->assertEquals(5.0, $batch->fresh()->produced_qty);
        $this->assertEquals(5.0, $wo->fresh()->produced_qty);
        $this->assertSame(WorkOrder::STATUS_IN_PROGRESS, $wo->fresh()->status);

        $service->recordQuantity($this->step($batch, 3), $this->operator, good: 4);
        // 9 of 10 planned: still open, and still in progress until the step is finished.
        $this->assertEquals(9.0, $wo->fresh()->produced_qty);
        $this->assertSame(WorkOrder::STATUS_IN_PROGRESS, $wo->fresh()->status);

        $service->completeStep($this->step($batch, 3), $this->operator);
        $this->assertSame(Batch::STATUS_DONE, $batch->fresh()->status);
        $this->assertEquals(9.0, $batch->fresh()->produced_qty);
        $this->assertEquals(9.0, $wo->fresh()->produced_qty);
    }

    public function test_order_closes_with_its_last_batch_not_when_the_count_is_reached(): void
    {
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $batch = $this->makeBatch();
        $service = app(BatchService::class);
        $wo = $batch->workOrder;

        foreach ([1, 2] as $n) {
            $service->startStep($this->step($batch, $n), $this->operator);
            $service->recordQuantity($this->step($batch, $n), $this->operator, good: 10);
            $service->completeStep($this->step($batch, $n), $this->operator);
        }
        $service->startStep($this->step($batch, 3), $this->operator);
        $service->recordQuantity($this->step($batch, 3), $this->operator, good: 10);

        // Planned quantity reached, but the last station hasn't finished its step.
        $this->assertEquals(10.0, $wo->fresh()->produced_qty);
        $this->assertSame(WorkOrder::STATUS_IN_PROGRESS, $wo->fresh()->status);

        $service->completeStep($this->step($batch, 3), $this->operator);
        $this->assertSame(WorkOrder::STATUS_DONE, $wo->fresh()->status);
    }

    public function test_skipped_steps_are_bypassed_by_the_ledger(): void
    {
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $batch = $this->makeBatch();
        $this->step($batch, 2)->update(['status' => BatchStep::STATUS_SKIPPED, 'is_optional' => true]);
        $service = app(BatchService::class);

        $service->startStep($this->step($batch, 1), $this->operator);
        $service->recordQuantity($this->step($batch, 1), $this->operator, good: 3);

        $this->assertSame(BatchStep::STATUS_READY, $this->step($batch, 3)->status);
        $this->assertEquals(3.0, $this->step($batch, 3)->incomingQty());
    }

    public function test_order_shows_in_the_next_stations_queue_once_pieces_have_passed(): void
    {
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $mixer = Workstation::factory()->create(['line_id' => $this->line->id, 'name' => 'Mixer']);
        $oven = Workstation::factory()->create(['line_id' => $this->line->id, 'name' => 'Oven']);
        $batch = $this->makeBatch();
        $this->step($batch, 1)->update(['workstation_id' => $mixer->id]);
        $this->step($batch, 2)->update(['workstation_id' => $oven->id]);
        $batch->workOrder->update(['status' => WorkOrder::STATUS_IN_PROGRESS]);
        $service = app(BatchService::class);
        $service->startStep($this->step($batch, 1), $this->operator);

        $queueAt = fn (Workstation $ws) => collect($this->actingAsOperator()
            ->get('/operator/queue?workstation='.$ws->id)
            ->viewData('page')['props']['workstationQueue'] ?? [])->pluck('id')->all();

        // Nothing has left the mixer: the oven's queue is empty.
        $this->assertSame([$batch->work_order_id], $queueAt($mixer));
        $this->assertSame([], $queueAt($oven));

        $service->recordQuantity($this->step($batch, 1), $this->operator, good: 3);

        // Pieces are waiting at the oven while the mixer is still running: both stations see the order.
        $this->assertSame([$batch->work_order_id], $queueAt($mixer));
        $this->assertSame([$batch->work_order_id], $queueAt($oven));
    }

    public function test_logging_through_a_station_passes_pieces_along_its_consecutive_steps(): void
    {
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $mixer = Workstation::factory()->create(['line_id' => $this->line->id, 'name' => 'Mixer']);
        $oven = Workstation::factory()->create(['line_id' => $this->line->id, 'name' => 'Oven']);
        $batch = $this->makeBatch();
        // Mixer owns steps 1 and 2; the oven owns step 3.
        $this->step($batch, 1)->update(['workstation_id' => $mixer->id]);
        $this->step($batch, 2)->update(['workstation_id' => $mixer->id]);
        $this->step($batch, 3)->update(['workstation_id' => $oven->id]);
        $service = app(BatchService::class);
        $service->startStep($this->step($batch, 1), $this->operator);

        $service->recordQuantityThroughStation($this->step($batch, 1), $this->operator, good: 6, scrap: 1);

        // Scrap stays at step 1; the 6 good pieces went through step 2 (auto-started) and wait at step 3.
        $this->assertEquals(6.0, $this->step($batch, 1)->passed_qty);
        $this->assertEquals(1.0, $this->step($batch, 1)->scrap_qty);
        $this->assertSame(BatchStep::STATUS_IN_PROGRESS, $this->step($batch, 2)->status);
        $this->assertEquals(6.0, $this->step($batch, 2)->passed_qty);
        $this->assertEquals(0.0, $this->step($batch, 2)->availableQty());
        $this->assertSame(BatchStep::STATUS_READY, $this->step($batch, 3)->status);
        $this->assertEquals(0.0, $this->step($batch, 3)->passed_qty);
        $this->assertEquals(6.0, $this->step($batch, 3)->availableQty());

        // Over HTTP the flag selects the same behaviour.
        $this->actingAsOperator()
            ->post(route('operator.batch-step.quantity', $this->step($batch, 1)), ['good_qty' => 2, 'through_station' => true])
            ->assertSessionHas('success');
        $this->assertEquals(8.0, $this->step($batch, 2)->passed_qty);
        $this->assertEquals(8.0, $this->step($batch, 3)->availableQty());
    }

    public function test_work_order_page_carries_the_ledger_the_blocker_and_the_selected_station(): void
    {
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $mixer = Workstation::factory()->create(['line_id' => $this->line->id, 'name' => 'Mixer']);
        $batch = $this->makeBatch();
        $this->step($batch, 1)->update(['workstation_id' => $mixer->id]);
        $service = app(BatchService::class);
        $service->startStep($this->step($batch, 1), $this->operator);
        $service->recordQuantity($this->step($batch, 1), $this->operator, good: 3);

        $props = $this->actingAsOperator()
            ->get('/operator/work-order/'.$batch->work_order_id.'?workstation='.$mixer->id)
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame('transfer', $props['flowMode']);
        $this->assertSame($mixer->id, $props['selectedWorkstation']['id']);
        $steps = collect($props['workOrder']['batches'][0]['steps'])->keyBy('step_number');
        $this->assertEquals(10, $steps[1]['incoming_qty']);
        $this->assertEquals(7, $steps[1]['available_qty']);
        $this->assertStringContainsString('7 pieces are still waiting', $steps[1]['completion_blocker']);
        $this->assertSame('Mixer', $steps[1]['workstation']['name']);
        $this->assertEquals(3, $steps[2]['incoming_qty']);
        $this->assertTrue($steps[1]['prerequisites_met']);
        $this->assertTrue($steps[2]['prerequisites_met']);
        $this->assertFalse($steps[3]['prerequisites_met']);
        $this->assertNull($steps[2]['completion_blocker']);
        $this->assertArrayNotHasKey('batch', $steps[1]);
    }

    public function test_a_fully_scrapped_step_still_lets_the_batch_close(): void
    {
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $batch = $this->makeBatch();
        $service = app(BatchService::class);

        $service->startStep($this->step($batch, 1), $this->operator);
        $service->recordQuantity($this->step($batch, 1), $this->operator, good: 0, scrap: 10);
        $service->completeStep($this->step($batch, 1), $this->operator);

        // Nothing reached step 2, but its feeder is closed: it opens so it can be closed empty.
        $this->assertSame(BatchStep::STATUS_READY, $this->step($batch, 2)->status);
        foreach ([2, 3] as $n) {
            $this->assertEquals(0.0, $this->step($batch, $n)->incomingQty());
            $service->startStep($this->step($batch, $n), $this->operator);
            $service->completeStep($this->step($batch, $n), $this->operator);
        }

        $this->assertSame(Batch::STATUS_DONE, $batch->fresh()->status);
        $this->assertEquals(0.0, $batch->fresh()->produced_qty);
        $this->assertSame(WorkOrder::STATUS_IN_PROGRESS, $batch->workOrder->fresh()->status);
    }

    public function test_migration_backfills_passed_qty_of_steps_finished_before_the_ledger(): void
    {
        $batch = $this->makeBatch();
        // As rows look right after the column change: finished steps carry no quantity.
        $this->step($batch, 1)->update(['status' => BatchStep::STATUS_DONE, 'passed_qty' => 0]);
        $this->step($batch, 2)->update(['status' => BatchStep::STATUS_IN_PROGRESS, 'passed_qty' => 0]);
        $sensorCounted = $this->makeBatch()->steps()->where('step_number', 1)->first();
        $sensorCounted->update(['status' => BatchStep::STATUS_DONE, 'passed_qty' => 12]);

        $migration = require base_path('database/migrations/2026_09_15_100000_add_flow_ledger_to_batch_steps.php');
        $migration->backfillLegacyPassedQty();

        $this->assertEquals(10.0, $this->step($batch, 1)->passed_qty);  // whole batch passed
        $this->assertEquals(0.0, $this->step($batch, 2)->passed_qty);   // not finished: untouched
        $this->assertEquals(12.0, $sensorCounted->fresh()->passed_qty); // a sensor count above target is kept

        // The in-flight batch can carry on once the installation switches to transfer flow.
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $this->assertEquals(10.0, $this->step($batch, 2)->availableQty());
        app(BatchService::class)->recordQuantity($this->step($batch, 2), $this->operator, good: 5);
        $this->assertSame(BatchStep::STATUS_READY, $this->step($batch, 3)->status);
    }

    public function test_machine_pass_is_capped_at_what_is_waiting_and_opens_the_next_station(): void
    {
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $batch = $this->makeBatch();
        $service = app(BatchService::class);

        // An over-count can't create pieces: 12 pulses on a 10-piece batch count 10.
        $this->assertEquals(10.0, $service->recordMachinePass($this->step($batch, 1), 12));
        $this->assertEquals(10.0, $this->step($batch, 1)->passed_qty);
        $this->assertSame(BatchStep::STATUS_READY, $this->step($batch, 2)->status);
        $this->assertEquals(10.0, $this->step($batch, 2)->incomingQty());

        $this->assertEquals(0.0, $service->recordMachinePass($this->step($batch, 1), 1));
        $this->assertEquals(10.0, $this->step($batch, 1)->passed_qty);
    }

    public function test_machine_pass_keeps_the_bare_counter_in_whole_batch_flow(): void
    {
        $batch = $this->makeBatch();

        $this->assertEquals(12.0, app(BatchService::class)->recordMachinePass($this->step($batch, 1), 12));
        $this->assertEquals(12.0, $this->step($batch, 1)->passed_qty);
        $this->assertSame(BatchStep::STATUS_PENDING, $this->step($batch, 2)->status);
    }

    public function test_break_beam_pulses_go_through_the_ledger_in_transfer_flow(): void
    {
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $batch = $this->makeBatch();
        $wo = $batch->workOrder;
        $wo->update(['status' => WorkOrder::STATUS_IN_PROGRESS, 'counting_source' => WorkOrder::COUNTING_MACHINE]);
        $mappings = [];
        $pulse = function (int $stepNumber) use ($batch, &$mappings): array {
            if (! isset($mappings[$stepNumber])) {
                $step = $this->step($batch, $stepNumber);
                $ws = Workstation::factory()->create(['line_id' => $this->line->id]);
                $step->update(['workstation_id' => $ws->id, 'status' => BatchStep::STATUS_IN_PROGRESS]);
                $mapping = $this->countStepMapping(['step_number' => $stepNumber, 'also_count_work_order' => true]);
                $counters = app(\App\Services\Machine\MachineCounterService::class);
                $counters->configure($counters->forSource($mapping), ['workstation_id' => $ws->id, 'batch_step_id' => $step->id,
                    'mode' => 'pulse', 'kind' => 'good', 'note' => 'Test assignment'], $this->operator->id);
                $mappings[$stepNumber] = $mapping;
            }
            $result = app(ActionExecutor::class)->executeSingle($mappings[$stepNumber], ['event_id' => (string) \Illuminate\Support\Str::uuid(), 'timestamp' => now()->toISOString()]);
            $this->assertSame('ok', $result['status'], (string) $result['message']);

            return json_decode($result['message'], true);
        };

        // A pulse at the first station passes a piece on; it isn't finished output.
        $outcome = $pulse(1);
        $this->assertEquals(1.0, $this->step($batch, 1)->passed_qty);
        $this->assertSame(BatchStep::STATUS_READY, $this->step($batch, 2)->status);
        $this->assertEquals(0.0, $wo->fresh()->produced_qty);
        $this->assertEquals(1, $outcome['applied_qty']);

        // Nothing is waiting at the last station yet: a pulse there counts nothing.
        $outcome = $pulse(3);
        $this->assertEquals(0, $outcome['applied_qty']);
        $this->assertEquals(0.0, $this->step($batch, 3)->passed_qty);
        $this->assertEquals(0.0, $wo->fresh()->produced_qty);

        // Once a piece reaches it, the last station's pulse is the order's output — counted once.
        app(BatchService::class)->recordMachinePass($this->step($batch, 2), 1);
        $outcome = $pulse(3);
        $this->assertEquals(1, $outcome['applied_qty']);
        $this->assertEquals(1.0, $this->step($batch, 3)->passed_qty);
        $this->assertEquals(1.0, $wo->fresh()->produced_qty);
    }

    public function test_machine_good_count_feeds_the_running_batch_instead_of_the_order(): void
    {
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $batch = $this->makeBatch();
        $wo = $batch->workOrder;
        $wo->update(['counting_source' => WorkOrder::COUNTING_MACHINE]);
        $service = app(BatchService::class);
        $machine = app(MachineProductionService::class);
        $service->startStep($this->step($batch, 1), $this->operator); // batch running

        // No finished pieces can leave the line before any reach the last step.
        $this->assertFalse($machine->recordGoodCount($wo->fresh(), 10));
        $this->assertEquals(0.0, $wo->fresh()->produced_qty);

        $service->recordQuantity($this->step($batch, 1), $this->operator, good: 10);
        $service->recordMachinePass($this->step($batch, 2), 10);

        // The count lands on the last step and rolls up; reaching the plan doesn't
        // close the order while its batch is still running.
        $this->assertTrue($machine->recordGoodCount($wo->fresh(), 10, $this->step($batch, 3)));
        $this->assertEquals(10.0, $this->step($batch, 3)->passed_qty);
        $this->assertEquals(10.0, $wo->fresh()->produced_qty);
        $this->assertNotSame(WorkOrder::STATUS_DONE, $wo->fresh()->status);

        // An operator log elsewhere rolls up again without losing the machine's count,
        // and a lower absolute counter value (a reset) adds nothing.
        $this->assertFalse($machine->recordAbsoluteCount($wo->fresh(), 4));
        $this->assertEquals(10.0, $wo->fresh()->produced_qty);
    }

    public function test_flow_mode_is_cached_until_forgotten(): void
    {
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $this->assertSame(ProductionFlow::TRANSFER, ProductionFlow::mode()); // read once: now cached

        DB::table('system_settings')->where('key', ProductionFlow::SETTING_KEY)
            ->update(['value' => json_encode(ProductionFlow::WHOLE_BATCH)]);
        $this->assertSame(ProductionFlow::TRANSFER, ProductionFlow::mode());

        ProductionFlow::forget();
        $this->assertSame(ProductionFlow::WHOLE_BATCH, ProductionFlow::mode());
    }

    private function countStepMapping(array $params): TopicMapping
    {
        $conn = MachineConnection::create([
            'name' => 'Sensor', 'protocol' => 'mqtt', 'line_id' => $this->line->id,
            'is_active' => true, 'status' => 'disconnected',
        ]);
        $topic = MachineTopic::create([
            'machine_connection_id' => $conn->id, 'topic_pattern' => 'line/sensor',
            'payload_format' => 'json', 'is_active' => true,
        ]);

        return TopicMapping::create([
            'machine_topic_id' => $topic->id, 'action_type' => TopicMapping::ACTION_COUNT_STEP,
            'action_params' => $params + ['increment' => 1], 'priority' => 100, 'is_active' => true,
        ]);
    }

    // ── Whole-batch flow (default) keeps today's behaviour ───────────────

    public function test_whole_batch_order_still_closes_when_the_plan_is_reached_with_another_batch_running(): void
    {
        $batch = $this->makeBatch();
        $wo = $batch->workOrder;
        $other = app(WorkOrderService::class)->createBatch($wo, 5);
        $service = app(BatchService::class);

        $service->startStep($other->steps()->where('step_number', 1)->first(), $this->operator);
        foreach ([1, 2, 3] as $n) {
            $service->startStep($this->step($batch, $n), $this->operator);
            $service->completeStep($this->step($batch, $n), $this->operator);
        }

        $this->assertSame(Batch::STATUS_IN_PROGRESS, $other->fresh()->status);
        $this->assertEquals(10.0, $wo->fresh()->produced_qty);
        $this->assertSame(WorkOrder::STATUS_DONE, $wo->fresh()->status);
    }

    public function test_through_station_flag_is_an_ordinary_log_in_whole_batch_flow(): void
    {
        $mixer = Workstation::factory()->create(['line_id' => $this->line->id, 'name' => 'Mixer']);
        $batch = $this->makeBatch();
        $this->step($batch, 1)->update(['workstation_id' => $mixer->id]);
        $this->step($batch, 2)->update(['workstation_id' => $mixer->id]);
        app(BatchService::class)->startStep($this->step($batch, 1), $this->operator);

        $this->actingAsOperator()
            ->post(route('operator.batch-step.quantity', $this->step($batch, 1)), ['good_qty' => 4, 'through_station' => true])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertEquals(4.0, $this->step($batch, 1)->passed_qty);
        $this->assertSame(BatchStep::STATUS_PENDING, $this->step($batch, 2)->status);
        $this->assertEquals(0.0, $this->step($batch, 2)->passed_qty);
    }

    public function test_whole_batch_flow_still_waits_for_the_previous_step_to_finish(): void
    {
        $batch = $this->makeBatch();
        $service = app(BatchService::class);

        $service->startStep($this->step($batch, 1), $this->operator);
        $service->recordQuantity($this->step($batch, 1), $this->operator, good: 4);

        // Logging alone does not open the next station in whole-batch mode…
        $this->assertSame(BatchStep::STATUS_PENDING, $this->step($batch, 2)->status);

        // …finishing does, and passes the remainder along untouched.
        $service->completeStep($this->step($batch, 1), $this->operator);
        $this->assertSame(BatchStep::STATUS_READY, $this->step($batch, 2)->status);
        $this->assertEquals(10.0, $this->step($batch, 1)->passed_qty);
        $this->assertEquals(10.0, $this->step($batch, 2)->incomingQty());
    }

    public function test_whole_batch_flow_completes_the_batch_with_the_reported_quantity(): void
    {
        $batch = $this->makeBatch();
        $service = app(BatchService::class);

        foreach ([1, 2] as $n) {
            $service->startStep($this->step($batch, $n), $this->operator);
            $service->completeStep($this->step($batch, $n), $this->operator);
        }
        $service->startStep($this->step($batch, 3), $this->operator);
        $service->completeStep($this->step($batch, 3), $this->operator, ['produced_qty' => 8]);

        $this->assertSame(Batch::STATUS_DONE, $batch->fresh()->status);
        $this->assertEquals(8.0, $batch->fresh()->produced_qty);
        $this->assertEquals(8.0, $batch->workOrder->fresh()->produced_qty);
    }

    // ── HTTP endpoint ────────────────────────────────────────────────────

    public function test_operator_logs_quantities_over_http(): void
    {
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $batch = $this->makeBatch();
        app(BatchService::class)->startStep($this->step($batch, 1), $this->operator);

        $this->actingAsOperator()
            ->from('/operator/work-order/'.$batch->work_order_id)
            ->post(route('operator.batch-step.quantity', $this->step($batch, 1)), ['good_qty' => 6, 'scrap_qty' => 1])
            ->assertRedirect('/operator/work-order/'.$batch->work_order_id)
            ->assertSessionHas('success');

        $this->assertEquals(6.0, $this->step($batch, 1)->passed_qty);
        $this->assertEquals(1.0, $this->step($batch, 1)->scrap_qty);
        $this->assertSame(BatchStep::STATUS_READY, $this->step($batch, 2)->status);
    }

    public function test_quantity_request_is_validated(): void
    {
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $batch = $this->makeBatch();
        app(BatchService::class)->startStep($this->step($batch, 1), $this->operator);
        $url = route('operator.batch-step.quantity', $this->step($batch, 1));

        // Nothing logged.
        $this->actingAsOperator()->post($url, ['good_qty' => 0, 'scrap_qty' => 0])
            ->assertSessionHasErrors('good_qty');
        // Not a number / negative.
        $this->actingAsOperator()->post($url, ['good_qty' => 'many'])->assertSessionHasErrors('good_qty');
        $this->actingAsOperator()->post($url, ['good_qty' => -1])->assertSessionHasErrors('good_qty');
        // More than is waiting: a domain rule, surfaced under the same field only
        // (a flash as well would show the message twice).
        $this->actingAsOperator()->post($url, ['good_qty' => 11])
            ->assertSessionHasErrors('good_qty')
            ->assertSessionMissing('error');

        $this->assertEquals(0.0, $this->step($batch, 1)->passed_qty);
    }

    public function test_step_on_another_line_is_rejected(): void
    {
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $batch = $this->makeBatch();
        app(BatchService::class)->startStep($this->step($batch, 1), $this->operator);

        $this->actingAs($this->operator)
            ->withSession(['selected_line_id' => Line::factory()->create()->id])
            ->post(route('operator.batch-step.quantity', $this->step($batch, 1)), ['good_qty' => 1])
            ->assertSessionHasErrors('good_qty')
            ->assertSessionMissing('error');

        $this->assertEquals(0.0, $this->step($batch, 1)->passed_qty);
    }

    public function test_guest_and_unauthorised_roles_cannot_log_quantities(): void
    {
        $batch = $this->makeBatch();
        $url = route('operator.batch-step.quantity', $this->step($batch, 1));

        $this->post($url, ['good_qty' => 1])->assertRedirect(route('login'));

        $nobody = User::factory()->create(); // no role at all
        $this->actingAs($nobody)->withSession(['selected_line_id' => $this->line->id])
            ->post($url, ['good_qty' => 1])
            ->assertForbidden();
    }

    public function test_flow_mode_setting_defaults_to_whole_batch_and_can_be_switched(): void
    {
        $this->assertSame(ProductionFlow::WHOLE_BATCH, ProductionFlow::mode());
        ProductionFlow::set(ProductionFlow::TRANSFER);
        $this->assertSame(ProductionFlow::TRANSFER, ProductionFlow::mode());
    }
}
