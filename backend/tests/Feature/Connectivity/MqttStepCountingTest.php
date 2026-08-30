<?php

namespace Tests\Feature\Connectivity;

use App\Jobs\ProcessMqttMessageJob;
use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Line;
use App\Models\MachineConnection;
use App\Models\MachineTopic;
use App\Models\Tenant;
use App\Models\TopicMapping;
use App\Models\WorkOrder;
use App\Services\Connectivity\ActionExecutor;
use App\Services\Connectivity\MqttMessageParser;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MQTT break-beam counting: a device is assigned to a line, and each sensor
 * pulse (count_step action) increments the passed_qty of the addressed step on
 * the line's running work order — optionally also feeding produced_qty.
 */
class MqttStepCountingTest extends TestCase
{
    use RefreshDatabase;

    private function device(?int $lineId): MachineConnection
    {
        return MachineConnection::create([
            'name' => 'Sensor', 'protocol' => 'mqtt', 'line_id' => $lineId,
            'is_active' => true, 'status' => 'disconnected',
        ]);
    }

    private function mapping(MachineConnection $conn, array $params): TopicMapping
    {
        $topic = MachineTopic::create([
            'machine_connection_id' => $conn->id,
            'topic_pattern' => 'line/sensor',
            'payload_format' => 'json',
            'is_active' => true,
        ]);

        return TopicMapping::create([
            'machine_topic_id' => $topic->id,
            'action_type' => TopicMapping::ACTION_COUNT_STEP,
            'action_params' => $params,
            'priority' => 100,
            'is_active' => true,
        ]);
    }

    private function runningOrderOnLine(Line $line, string $counting = WorkOrder::COUNTING_MACHINE): array
    {
        $wo = WorkOrder::factory()->create([
            'line_id' => $line->id,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
            'counting_source' => $counting,
        ]);
        $batch = Batch::factory()->create(['work_order_id' => $wo->id]);
        $step = BatchStep::factory()->create(['batch_id' => $batch->id, 'step_number' => 2, 'passed_qty' => 0]);

        return [$wo, $step];
    }

    public function test_a_pulse_increments_the_step_passed_qty_using_the_device_line(): void
    {
        $line = Line::factory()->create();
        [, $step] = $this->runningOrderOnLine($line);
        // No line_id in the mapping — it must inherit the device's assigned line.
        $mapping = $this->mapping($this->device($line->id), ['step_number' => 2, 'increment' => 1]);

        app(ActionExecutor::class)->executeSingle($mapping, []);

        $this->assertSame(1, $step->fresh()->passed_qty);
    }

    public function test_repeated_pulses_accumulate(): void
    {
        $line = Line::factory()->create();
        [, $step] = $this->runningOrderOnLine($line);
        $mapping = $this->mapping($this->device($line->id), ['step_number' => 2]);

        $exec = app(ActionExecutor::class);
        $exec->executeSingle($mapping, []);
        $exec->executeSingle($mapping, []);
        $exec->executeSingle($mapping, []);

        $this->assertSame(3, $step->fresh()->passed_qty);
    }

    public function test_finished_goods_flag_also_feeds_produced_qty(): void
    {
        $line = Line::factory()->create();
        [$wo, $step] = $this->runningOrderOnLine($line, WorkOrder::COUNTING_MACHINE);
        $mapping = $this->mapping($this->device($line->id), [
            'step_number' => 2, 'increment' => 1, 'also_count_work_order' => true,
        ]);

        app(ActionExecutor::class)->executeSingle($mapping, []);

        $this->assertSame(1, $step->fresh()->passed_qty);
        $this->assertEqualsWithDelta(1.0, (float) $wo->fresh()->produced_qty, 0.0001);
    }

    public function test_intermediate_station_does_not_touch_produced_qty(): void
    {
        $line = Line::factory()->create();
        [$wo, $step] = $this->runningOrderOnLine($line, WorkOrder::COUNTING_MACHINE);
        // No also_count_work_order — throughput only.
        $mapping = $this->mapping($this->device($line->id), ['step_number' => 2]);

        app(ActionExecutor::class)->executeSingle($mapping, []);

        $this->assertSame(1, $step->fresh()->passed_qty);
        $this->assertEqualsWithDelta(0.0, (float) $wo->fresh()->produced_qty, 0.0001);
    }

    public function test_explicit_line_param_overrides_the_device_line(): void
    {
        $deviceLine = Line::factory()->create();
        $targetLine = Line::factory()->create();
        [, $step] = $this->runningOrderOnLine($targetLine);
        $mapping = $this->mapping($this->device($deviceLine->id), [
            'line_id' => $targetLine->id, 'step_number' => 2,
        ]);

        app(ActionExecutor::class)->executeSingle($mapping, []);

        $this->assertSame(1, $step->fresh()->passed_qty);
    }

    public function test_a_payload_line_id_cannot_reach_another_tenants_work_order(): void
    {
        $tenant = app(TenantContext::class);
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        // Tenant B's running order + step (created under B's context).
        $tenant->set($tenantB->id);
        $lineB = Line::factory()->create();
        [, $stepB] = $this->runningOrderOnLine($lineB);
        $tenant->clear();

        // Device belongs to tenant A; its mapping tries to target tenant B's line.
        $tenant->set($tenantA->id);
        $mapping = $this->mapping($this->device(null), ['line_id' => $lineB->id, 'step_number' => 2]);
        $connId = $mapping->topic->machine_connection_id;
        $tenant->clear();

        // The queued job scopes execution to the device's tenant (A), so the
        // cross-tenant line_id resolves to nothing — B's step is untouched.
        (new ProcessMqttMessageJob($connId, 'line/sensor', '{}', now()->toIso8601String()))
            ->handle(app(MqttMessageParser::class), app(ActionExecutor::class), $tenant);

        $this->assertSame(0, $stepB->fresh()->passed_qty);
    }

    public function test_an_idle_line_is_skipped_not_errored(): void
    {
        $line = Line::factory()->create(); // no running work order
        $mapping = $this->mapping($this->device($line->id), ['step_number' => 2]);

        $result = app(ActionExecutor::class)->executeSingle($mapping, []);

        $this->assertSame('ok', $result['status']);
        $this->assertStringContainsString('no in-progress work order', $result['message']);
    }
}
