<?php

namespace Tests\Feature\Connectivity;

use App\Models\Issue;
use App\Models\IssueType;
use App\Models\Line;
use App\Models\MachineConnection;
use App\Models\MachineTopic;
use App\Models\TopicMapping;
use App\Models\WorkOrder;
use App\Services\Connectivity\ActionExecutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A machine reporting a fault over MQTT files one open issue the floor can see. */
class MqttCreateIssueTest extends TestCase
{
    use RefreshDatabase;

    private function mapping(array $params): TopicMapping
    {
        $conn = MachineConnection::create(['name' => 'Press', 'protocol' => 'mqtt', 'is_active' => true]);
        $topic = MachineTopic::create(['machine_connection_id' => $conn->id, 'topic_pattern' => 'press/alarm', 'is_active' => true]);

        return TopicMapping::create(['machine_topic_id' => $topic->id, 'action_type' => TopicMapping::ACTION_CREATE_ISSUE,
            'field_path' => '$.alarm', 'is_active' => true, 'action_params' => $params]);
    }

    public function test_a_machine_fault_files_an_open_issue_against_the_order(): void
    {
        $type = IssueType::factory()->create(['is_blocking' => true]);
        $order = WorkOrder::factory()->inProgress()->create(['line_id' => Line::factory()->create()->id]);
        $mapping = $this->mapping(['issue_type_id' => $type->id, 'work_order_no_path' => '$.order', 'title' => 'Press 3 hydraulic fault']);

        $result = app(ActionExecutor::class)->executeSingle($mapping, ['alarm' => 1, 'order' => $order->order_no]);

        $this->assertSame('ok', $result['status'], (string) $result['message']);
        $issue = Issue::sole();
        $this->assertSame(Issue::STATUS_OPEN, $issue->status);
        $this->assertSame('Press 3 hydraulic fault', $issue->title);
        $this->assertSame($order->id, $issue->work_order_id);
        $this->assertNotNull($issue->reported_at);
        $this->assertSame(WorkOrder::STATUS_BLOCKED, $order->fresh()->status);
    }

    public function test_a_repeating_fault_message_does_not_flood_issues(): void
    {
        $type = IssueType::factory()->create(['is_blocking' => false]);
        $mapping = $this->mapping(['issue_type_id' => $type->id, 'title' => 'Press 3 hydraulic fault']);
        $executor = app(ActionExecutor::class);

        foreach (range(1, 5) as $i) {
            $executor->executeSingle($mapping, ['alarm' => 1]);
        }
        $this->assertSame(1, Issue::count());

        // Once it is resolved, the next fault is a new issue.
        Issue::sole()->update(['status' => Issue::STATUS_RESOLVED]);
        $executor->executeSingle($mapping, ['alarm' => 1]);
        $this->assertSame(2, Issue::count());
    }

    public function test_a_blocking_type_without_an_order_still_files_the_issue(): void
    {
        $type = IssueType::factory()->create(['is_blocking' => true]);
        $mapping = $this->mapping(['issue_type_id' => $type->id, 'description' => 'Compressor pressure low']);

        $result = app(ActionExecutor::class)->executeSingle($mapping, ['alarm' => 1]);

        $this->assertSame('ok', $result['status'], (string) $result['message']);
        $issue = Issue::sole();
        $this->assertSame('Compressor pressure low', $issue->title);
        $this->assertFalse($issue->blocks_work_order);
    }

    public function test_a_mapping_without_an_issue_type_reports_why(): void
    {
        $result = app(ActionExecutor::class)->executeSingle($this->mapping(['title' => 'Fault']), ['alarm' => 1]);

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('Issue type not found', $result['message']);
        $this->assertSame(0, Issue::count());
    }
}
