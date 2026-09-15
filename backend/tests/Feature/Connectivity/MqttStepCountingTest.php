<?php

namespace Tests\Feature\Connectivity;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Line;
use App\Models\MachineConnection;
use App\Models\MachineTopic;
use App\Models\TopicMapping;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Services\Connectivity\ActionExecutor;
use App\Services\Machine\MachineCounterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** MQTT transport integration: routing is configured explicitly, never inferred from payload hints. */
class MqttStepCountingTest extends TestCase
{
    use RefreshDatabase;

    private function setupMapping(bool $assign = true, string $action = 'count_step'): array
    {
        $line = Line::factory()->create();
        $ws = Workstation::factory()->create(['line_id' => $line->id]);
        $wo = WorkOrder::factory()->inProgress()->create(['line_id' => $line->id, 'counting_source' => 'machine', 'planned_qty' => 100]);
        $batch = Batch::factory()->inProgress()->create(['work_order_id' => $wo->id]);
        $step = BatchStep::factory()->inProgress()->create(['batch_id' => $batch->id, 'workstation_id' => $ws->id, 'step_number' => 1]);
        $conn = MachineConnection::create(['name' => 'MQTT test', 'protocol' => 'mqtt', 'line_id' => $line->id, 'is_active' => true]);
        $topic = MachineTopic::create(['machine_connection_id' => $conn->id, 'topic_pattern' => 'test', 'is_active' => true]);
        $mapping = TopicMapping::create(['machine_topic_id' => $topic->id, 'action_type' => $action, 'field_path' => '$.count', 'is_active' => true, 'action_params' => ['line_id' => $line->id, 'step_number' => 1]]);
        $service = app(MachineCounterService::class);
        $counter = $service->forSource($mapping);
        if ($assign) {
            $service->configure($counter, ['mode' => $action === 'count_step' ? 'pulse' : 'cumulative', 'kind' => 'good', 'workstation_id' => $ws->id, 'batch_step_id' => $step->id, 'note' => 'Explicit MQTT assignment'], User::factory()->create()->id);
        }

        return [$mapping, $step, $wo, $counter];
    }

    public function test_unique_pulses_accumulate_and_duplicate_delivery_is_idempotent(): void
    {
        [$mapping, $step, $wo] = $this->setupMapping();
        $executor = app(ActionExecutor::class);
        foreach (['a', 'b', 'b', 'c'] as $id) {
            $result = $executor->executeSingle($mapping, ['event_id' => $id, 'timestamp' => now()->toISOString()]);
            $this->assertSame('ok', $result['status'], $result['message']);
        }
        $this->assertEquals(3, $step->fresh()->passed_qty);
        $this->assertEquals(3, $wo->fresh()->produced_qty);
    }

    public function test_payload_routing_and_latest_batch_cannot_replace_assignment(): void
    {
        [$mapping, $step, $wo] = $this->setupMapping();
        $other = Batch::factory()->inProgress()->create(['work_order_id' => $wo->id]);
        $otherStep = BatchStep::factory()->inProgress()->create(['batch_id' => $other->id, 'workstation_id' => $step->workstation_id]);
        app(ActionExecutor::class)->executeSingle($mapping, ['event_id' => 'a', 'timestamp' => now()->toISOString(), 'batch_step_id' => $otherStep->id]);
        $this->assertEquals(1, $step->fresh()->passed_qty);
        $this->assertEquals(0, $otherStep->fresh()->passed_qty);
    }

    public function test_unconfigured_channel_retains_readings_without_guessing(): void
    {
        [$mapping, $step, , $counter] = $this->setupMapping(false);
        $result = app(ActionExecutor::class)->executeSingle($mapping, ['event_id' => 'a', 'timestamp' => now()->toISOString()]);
        $this->assertStringContainsString('unconfigured', $result['message']);
        $this->assertEquals(0, $step->fresh()->passed_qty);
        $this->assertEquals(1, $counter->readings()->count());
    }

    public function test_pulse_without_identity_cannot_count(): void
    {
        [$mapping, $step] = $this->setupMapping();
        app(ActionExecutor::class)->executeSingle($mapping, ['timestamp' => now()->toISOString()]);
        $this->assertEquals(0, $step->fresh()->passed_qty);
    }

    public function test_absolute_mapping_uses_raw_baseline_instead_of_order_total(): void
    {
        [$mapping, $step, $wo] = $this->setupMapping(true, 'update_work_order_qty');
        foreach ([9000, 9004, 9004, 2] as $count) {
            $result = app(ActionExecutor::class)->executeSingle($mapping, ['count' => $count, 'timestamp' => now()->toISOString()]);
            $this->assertSame('ok', $result['status'], $result['message']);
        }
        $this->assertEquals(4, $step->fresh()->passed_qty);
        $this->assertEquals(4, $wo->fresh()->produced_qty);
    }
}
