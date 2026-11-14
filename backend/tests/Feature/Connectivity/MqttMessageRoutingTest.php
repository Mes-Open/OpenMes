<?php

namespace Tests\Feature\Connectivity;

use App\Console\Commands\MqttListenCommand;
use App\Jobs\ProcessMqttMessageJob;
use App\Models\Issue;
use App\Models\IssueType;
use App\Models\MachineConnection;
use App\Models\MachineMessage;
use App\Models\MachineTopic;
use App\Models\TopicMapping;
use App\Services\Connectivity\MqttSubscriptionRejections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** One MQTT message from a machine is acted on exactly once, and never again on replay. */
class MqttMessageRoutingTest extends TestCase
{
    use RefreshDatabase;

    private MachineConnection $conn;

    protected function setUp(): void
    {
        parent::setUp();
        $this->conn = MachineConnection::create(['name' => 'Hall 1', 'protocol' => 'mqtt', 'is_active' => true]);
    }

    private function topic(string $pattern, string $action, array $params = []): void
    {
        $topic = MachineTopic::create(['machine_connection_id' => $this->conn->id, 'topic_pattern' => $pattern, 'is_active' => true]);
        TopicMapping::create(['machine_topic_id' => $topic->id, 'action_type' => $action, 'field_path' => '$.alarm',
            'is_active' => true, 'action_params' => $params]);
    }

    private function receive(string $topic, array $payload, bool $retained = false): MachineMessage
    {
        (new ProcessMqttMessageJob($this->conn->id, $topic, json_encode($payload), now()->toIso8601String(), $retained))->handle(
            app(\App\Services\Connectivity\MqttMessageParser::class),
            app(\App\Services\Connectivity\ActionExecutor::class),
            app(\App\Support\TenantContext::class),
        );

        return MachineMessage::latest('id')->first();
    }

    public function test_every_matching_topic_definition_runs_its_mappings(): void
    {
        $type = IssueType::factory()->create(['is_blocking' => false]);
        $this->topic('hall1/#', TopicMapping::ACTION_LOG_EVENT);
        $this->topic('hall1/press3/alarm', TopicMapping::ACTION_CREATE_ISSUE, ['issue_type_id' => $type->id, 'title' => 'Press 3 fault']);

        $message = $this->receive('hall1/press3/alarm', ['alarm' => 1]);

        $this->assertSame(MachineMessage::STATUS_OK, $message->processing_status, (string) $message->processing_error);
        $this->assertCount(2, $message->actions_triggered);
        $this->assertSame(1, Issue::count());
    }

    public function test_a_retained_replay_is_logged_but_not_acted_on(): void
    {
        $type = IssueType::factory()->create(['is_blocking' => false]);
        $this->topic('hall1/press3/alarm', TopicMapping::ACTION_CREATE_ISSUE, ['issue_type_id' => $type->id, 'title' => 'Press 3 fault']);

        $message = $this->receive('hall1/press3/alarm', ['alarm' => 1], retained: true);

        $this->assertSame(MachineMessage::STATUS_SKIPPED, $message->processing_status);
        $this->assertStringContainsString('Retained', $message->processing_error);
        $this->assertSame(0, Issue::count());
    }

    public function test_patterns_covered_by_a_broader_one_are_not_subscribed_twice(): void
    {
        $this->assertSame(
            ['hall1/#', 'hall2/+/count', 'hall2/press1/state'],
            MqttListenCommand::subscriptionPatterns([
                'hall1/#', 'hall1/press3/count', 'hall1/+/state', 'hall1/#',
                'hall2/+/count', 'hall2/press1/count', 'hall2/press1/state',
            ]),
        );
        // Partly overlapping patterns are both needed.
        $this->assertSame(['a/+/c', 'a/b/+'], MqttListenCommand::subscriptionPatterns(['a/+/c', 'a/b/+']));
        $this->assertSame(['#'], MqttListenCommand::subscriptionPatterns(['a/b', '#', '+/c']));
        // A leading wildcard never matches a $-topic, so it stays subscribed.
        $this->assertSame(['#', '$SYS/broker/#'], MqttListenCommand::subscriptionPatterns(['#', '$SYS/broker/#']));
    }

    public function test_a_refused_broad_subscription_falls_back_to_the_patterns_it_covered(): void
    {
        $patterns = ['hall1/#', 'hall1/press3/count', 'hall1/+/state', 'hall2/+/count', 'hall2/press1/count'];
        $subscribed = MqttListenCommand::subscriptionPatterns($patterns);
        $this->assertSame(['hall1/#', 'hall2/+/count'], $subscribed);

        // The broker's ACL refuses `hall1/#`: the narrower hall1 patterns it
        // made redundant are subscribed instead, nothing else twice.
        $this->assertSame(
            ['hall1/press3/count', 'hall1/+/state'],
            MqttListenCommand::fallbackPatterns($patterns, ['hall1/#'], $subscribed),
        );

        // One of those refused as well: nothing left to fall back to.
        $subscribed = [...$subscribed, 'hall1/press3/count', 'hall1/+/state'];
        $this->assertSame([], MqttListenCommand::fallbackPatterns($patterns, ['hall1/#', 'hall1/+/state'], $subscribed));
    }

    public function test_refusals_logged_by_the_mqtt_client_are_collected(): void
    {
        $rejections = new MqttSubscriptionRejections;
        // The client wraps its own logger in one that rewrites the message but
        // keeps the context.
        $rejections->notice('MQTT [broker:1883] [client]: The broker rejected the subscription to [{topicFilter}].', ['topicFilter' => 'hall1/#']);
        $rejections->debug('Subscribing to topic [{topicFilter}] with maximum QoS [{qos}].', ['topicFilter' => 'hall2/+/count', 'qos' => 0]);

        $this->assertSame(['hall1/#'], $rejections->take());
        $this->assertSame([], $rejections->take());
    }
}
