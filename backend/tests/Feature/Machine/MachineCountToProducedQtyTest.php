<?php

namespace Tests\Feature\Machine;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Line;
use App\Models\MachineConnection;
use App\Models\MachineTag;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Services\Machine\MachineSignalIngestor;
use App\Services\WorkOrder\MachineProductionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Covers #46 — machine counter signals reaching work_orders.produced_qty, gated
 * by the per-order counting_source, with the double-count guard on the legacy
 * MQTT path.
 */
class MachineCountToProducedQtyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A work order running at a workstation, with an in-progress batch step there
     * so a machine tag on that workstation resolves back to the order.
     */
    private function scenario(string $countingSource, float $planned = 100, string $status = WorkOrder::STATUS_IN_PROGRESS): array
    {
        $line = Line::factory()->create();
        $workOrder = WorkOrder::factory()->create([
            'line_id' => $line->id,
            'counting_source' => $countingSource,
            'planned_qty' => $planned,
            'produced_qty' => 0,
            'status' => $status,
        ]);
        $workstation = Workstation::factory()->create(['line_id' => $line->id]);
        $batch = Batch::factory()->inProgress()->create([
            'work_order_id' => $workOrder->id,
            'workstation_id' => $workstation->id,
        ]);
        $step = BatchStep::factory()->inProgress()->create([
            'batch_id' => $batch->id,
            'workstation_id' => $workstation->id,
        ]);

        $connection = MachineConnection::create([
            'name' => 'TEST-CONN',
            'protocol' => 'modbus',
            'is_active' => true,
            'status' => MachineConnection::STATUS_CONNECTED,
        ]);
        $tag = MachineTag::create([
            'machine_connection_id' => $connection->id,
            'workstation_id' => $workstation->id,
            'name' => 'good_parts',
            'address' => '40001',
            'signal_type' => MachineTag::SIGNAL_GOOD_COUNT,
            'is_active' => true,
        ]);

        if ($countingSource === WorkOrder::COUNTING_MACHINE) {
            $service = app(\App\Services\Machine\MachineCounterService::class);
            $service->configure($service->forSource($tag), ['workstation_id' => $workstation->id, 'batch_step_id' => $step->id,
                'mode' => 'cumulative', 'kind' => 'good', 'note' => 'Test assignment'], User::factory()->create()->id);
        }

        return [$workOrder, $workstation, $tag->fresh()];
    }

    public function test_unconfigured_source_keeps_legacy_counting_for_whole_batch_orders(): void
    {
        [$workOrder, , $tag] = $this->scenario(WorkOrder::COUNTING_OPERATOR);
        $workOrder->update(['counting_source' => WorkOrder::COUNTING_MACHINE]);
        app(MachineSignalIngestor::class)->ingest($tag, 10);
        app(MachineSignalIngestor::class)->ingest($tag, 13);
        $this->assertEquals(3, $workOrder->fresh()->produced_qty);
    }

    public function test_machine_counted_order_gains_produced_qty_from_a_good_count_delta(): void
    {
        [$workOrder, , $tag] = $this->scenario(WorkOrder::COUNTING_MACHINE, planned: 100);
        $ingestor = app(MachineSignalIngestor::class);

        // First reading establishes the cumulative baseline (delta 0, no-op).
        $ingestor->ingest($tag, 0, now());
        // Machine counter climbs to 7 → a delta of 7 good parts.
        $ingestor->ingest($tag, 7, now());

        $this->assertEqualsWithDelta(7.0, (float) $workOrder->fresh()->produced_qty, 0.001);
    }

    public function test_operator_counted_order_ignores_machine_good_counts(): void
    {
        [$workOrder, , $tag] = $this->scenario(WorkOrder::COUNTING_OPERATOR);
        $ingestor = app(MachineSignalIngestor::class);

        $ingestor->ingest($tag, 0, now());
        $ingestor->ingest($tag, 12, now());

        // The event is still logged upstream, but produced_qty must not move.
        $this->assertEqualsWithDelta(0.0, (float) $workOrder->fresh()->produced_qty, 0.001);
    }

    public function test_machine_count_auto_completes_the_order_when_reaching_planned(): void
    {
        [$workOrder] = $this->scenario(WorkOrder::COUNTING_MACHINE, planned: 10);

        app(MachineProductionService::class)->recordGoodCount($workOrder, 10);

        $fresh = $workOrder->fresh();
        $this->assertEqualsWithDelta(10.0, (float) $fresh->produced_qty, 0.001);
        $this->assertSame(WorkOrder::STATUS_DONE, $fresh->status);
        $this->assertNotNull($fresh->completed_at);
    }

    public function test_source_assignment_cannot_override_operator_counting(): void
    {
        [$workOrder, , $tag] = $this->scenario(WorkOrder::COUNTING_MACHINE);
        app(MachineSignalIngestor::class)->ingest($tag, 0, now());
        $workOrder->update(['counting_source' => WorkOrder::COUNTING_OPERATOR]);
        app(MachineSignalIngestor::class)->ingest($tag, 5, now());
        $this->assertEquals(0, $workOrder->fresh()->produced_qty);
    }

    public function test_operator_cannot_manually_enter_qty_on_a_machine_counted_order(): void
    {
        Role::findOrCreate('Operator', 'web');
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        [$workOrder] = $this->scenario(WorkOrder::COUNTING_MACHINE, planned: 100);

        $response = $this->actingAs($operator)
            ->withSession(['selected_line_id' => $workOrder->line_id])
            ->post("/operator/workstation/{$workOrder->id}/complete", ['produced_qty' => 5]);

        $response->assertSessionHas('error');
        $this->assertEqualsWithDelta(0.0, (float) $workOrder->fresh()->produced_qty, 0.001);
    }

    public function test_operator_can_still_enter_qty_on_an_operator_counted_order(): void
    {
        Role::findOrCreate('Operator', 'web');
        $operator = User::factory()->create();
        $operator->assignRole('Operator');

        [$workOrder] = $this->scenario(WorkOrder::COUNTING_OPERATOR, planned: 100);

        $this->actingAs($operator)
            ->withSession(['selected_line_id' => $workOrder->line_id])
            ->post("/operator/workstation/{$workOrder->id}/complete", ['produced_qty' => 5])
            ->assertSessionHas('success');

        $this->assertEqualsWithDelta(5.0, (float) $workOrder->fresh()->produced_qty, 0.001);
    }
}
