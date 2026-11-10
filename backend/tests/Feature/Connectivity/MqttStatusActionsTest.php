<?php

namespace Tests\Feature\Connectivity;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Line;
use App\Models\LineStatus;
use App\Models\MachineConnection;
use App\Models\MachineTopic;
use App\Models\TopicMapping;
use App\Models\WorkOrder;
use App\Services\Connectivity\ActionExecutor;
use App\Support\ProductionFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Statuses written from MQTT messages are the ones the rest of OpenMES knows. */
class MqttStatusActionsTest extends TestCase
{
    use RefreshDatabase;

    private function execute(string $action, array $params, array $payload): array
    {
        $conn = MachineConnection::create(['name' => 'Line PLC', 'protocol' => 'mqtt', 'is_active' => true]);
        $topic = MachineTopic::create(['machine_connection_id' => $conn->id, 'topic_pattern' => 'plc', 'is_active' => true]);
        $mapping = TopicMapping::create(['machine_topic_id' => $topic->id, 'action_type' => $action,
            'field_path' => '$.status', 'is_active' => true, 'action_params' => $params]);

        return app(ActionExecutor::class)->executeSingle($mapping, $payload);
    }

    public function test_a_machine_status_in_lower_case_is_stored_as_the_app_status(): void
    {
        $order = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_ACCEPTED]);

        $result = $this->execute(TopicMapping::ACTION_SET_WORK_ORDER_STATUS, ['order_no_path' => '$.order', 'status_path' => '$.status'],
            ['order' => $order->order_no, 'status' => 'in_progress']);

        $this->assertSame('ok', $result['status'], (string) $result['message']);
        $this->assertSame(WorkOrder::STATUS_IN_PROGRESS, $order->fresh()->status);
    }

    public function test_an_unknown_status_is_refused(): void
    {
        $order = WorkOrder::factory()->create(['status' => WorkOrder::STATUS_ACCEPTED]);

        $result = $this->execute(TopicMapping::ACTION_SET_WORK_ORDER_STATUS, ['order_no_path' => '$.order', 'status_path' => '$.status'],
            ['order' => $order->order_no, 'status' => 'exploded']);

        $this->assertSame('error', $result['status']);
        $this->assertSame(WorkOrder::STATUS_ACCEPTED, $order->fresh()->status);
    }

    public function test_line_status_reaches_the_running_order(): void
    {
        $line = Line::factory()->create();
        $order = WorkOrder::factory()->inProgress()->create(['line_id' => $line->id]);
        $lineStatus = LineStatus::create(['name' => 'Material shortage', 'color' => '#f00', 'line_id' => $line->id]);

        $result = $this->execute(TopicMapping::ACTION_UPDATE_LINE_STATUS, ['line_id' => $line->id, 'status_code_path' => '$.status'],
            ['status' => 'material shortage']);

        $this->assertSame('ok', $result['status'], (string) $result['message']);
        $this->assertSame($lineStatus->id, $order->fresh()->line_status_id);
    }

    public function test_a_step_found_by_batch_and_number_is_marked_done(): void
    {
        ProductionFlow::set(ProductionFlow::WHOLE_BATCH);
        $order = WorkOrder::factory()->inProgress()->create();
        $batch = Batch::factory()->inProgress()->create(['work_order_id' => $order->id]);
        $step = BatchStep::factory()->inProgress()->create(['batch_id' => $batch->id, 'step_number' => 2]);

        $result = $this->execute(TopicMapping::ACTION_UPDATE_BATCH_STEP, ['batch_id_path' => '$.batch', 'step_number' => 2],
            ['batch' => $batch->id]);

        $this->assertSame('ok', $result['status'], (string) $result['message']);
        $this->assertSame(BatchStep::STATUS_DONE, $step->fresh()->status);
    }

    public function test_a_failed_step_result_is_refused_rather_than_stored(): void
    {
        $order = WorkOrder::factory()->inProgress()->create();
        $batch = Batch::factory()->inProgress()->create(['work_order_id' => $order->id]);
        $step = BatchStep::factory()->inProgress()->create(['batch_id' => $batch->id, 'step_number' => 1]);

        $result = $this->execute(TopicMapping::ACTION_UPDATE_BATCH_STEP, ['step_id_path' => '$.step', 'result_path' => '$.result'],
            ['step' => $step->id, 'result' => 'failed']);

        $this->assertSame('error', $result['status']);
        $this->assertSame(BatchStep::STATUS_IN_PROGRESS, $step->fresh()->status);
    }

    public function test_a_held_or_finished_order_is_not_moved_by_a_machine_message(): void
    {
        foreach ([WorkOrder::STATUS_BLOCKED, WorkOrder::STATUS_PAUSED, WorkOrder::STATUS_CHANGE_HOLD, WorkOrder::STATUS_DONE, WorkOrder::STATUS_CANCELLED] as $status) {
            $order = WorkOrder::factory()->create(['status' => $status]);

            $result = $this->execute(TopicMapping::ACTION_SET_WORK_ORDER_STATUS, ['order_no_path' => '$.order', 'status_path' => '$.status'],
                ['order' => $order->order_no, 'status' => 'in_progress']);

            $this->assertSame('error', $result['status'], $status);
            $this->assertSame($status, $order->fresh()->status);
        }
    }
}
