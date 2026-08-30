<?php

namespace App\Jobs;

use App\Events\MachineMessageReceived;
use App\Models\MachineConnection;
use App\Models\MachineMessage;
use App\Scopes\TenantScope;
use App\Services\Connectivity\ActionExecutor;
use App\Services\Connectivity\MqttMessageParser;
use App\Support\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMqttMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        private readonly int $connectionId,
        private readonly string $topic,
        private readonly string $payload,
        private readonly string $receivedAt,
    ) {}

    public function handle(MqttMessageParser $parser, ActionExecutor $executor, TenantContext $tenant): void
    {
        // Resolve the connection with the tenant scope bypassed: this job runs
        // headless (no auth user), and a stale/foreign TenantContext left on a
        // reused Octane worker would otherwise hide the connection and silently
        // drop the message. The connection id comes from our own daemon, so the
        // unscoped lookup is trusted.
        $connection = MachineConnection::withoutGlobalScope(TenantScope::class)
            ->find($this->connectionId);
        if (! $connection) {
            return;
        }

        // Now scope the whole run to the device's tenant, so a payload-supplied
        // line_id can never reach another tenant's work order, and load the
        // topics/mappings under that tenant.
        $previousTenant = $tenant->id();
        $tenant->set($connection->tenant_id);
        $connection->load(['activeTopics.activeMappings']);

        try {
            // Find the matching topic definition (supports wildcards)
            $matchedTopic = $connection->activeTopics->first(
                fn ($t) => $t->matchesTopic($this->topic)
            );

            // Parse payload
            $format = $matchedTopic?->payload_format ?? 'json';
            $parsedData = $parser->parse($this->payload, $format);

            // Execute mappings if topic matched
            $actionsTriggered = [];
            $processingStatus = MachineMessage::STATUS_OK;
            $processingError = null;

            if ($matchedTopic) {
                try {
                    $actionsTriggered = $executor->executeAll($matchedTopic, $parsedData);
                    $hasError = collect($actionsTriggered)->contains('status', 'error');
                    if ($hasError) {
                        $processingStatus = MachineMessage::STATUS_ERROR;
                        $processingError = collect($actionsTriggered)
                            ->where('status', 'error')
                            ->pluck('message')
                            ->implode('; ');
                    }
                } catch (\Throwable $e) {
                    $processingStatus = MachineMessage::STATUS_ERROR;
                    $processingError = $e->getMessage();
                    Log::error('MQTT message processing failed', [
                        'connection_id' => $this->connectionId,
                        'topic' => $this->topic,
                        'error' => $e->getMessage(),
                    ]);
                }
            } else {
                $processingStatus = MachineMessage::STATUS_SKIPPED;
                $processingError = 'No matching topic definition';
            }

            // Persist message log
            $message = MachineMessage::create([
                'machine_connection_id' => $this->connectionId,
                'topic' => $this->topic,
                'raw_payload' => $this->payload,
                'parsed_data' => $parsedData,
                'actions_triggered' => $actionsTriggered,
                'processing_status' => $processingStatus,
                'processing_error' => $processingError,
                'received_at' => $this->receivedAt,
            ]);

            // Update connection counter
            $connection->incrementMessageCount();

            // Broadcast to live log listeners
            event(new MachineMessageReceived($message));

            // Prune old messages (keep last 10,000 per connection)
            if ($message->id % 100 === 0) {
                MachineMessage::pruneForConnection($this->connectionId, 10000);
            }
        } finally {
            $tenant->set($previousTenant);
        }
    }
}
