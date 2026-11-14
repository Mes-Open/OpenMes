<?php

namespace App\Services\Connectivity;

use App\Models\BatchStep;
use App\Models\Issue;
use App\Models\IssueType;
use App\Models\Line;
use App\Models\LineStatus;
use App\Models\MachineTopic;
use App\Models\TopicMapping;
use App\Models\WorkOrder;
use App\Services\IssueService;
use App\Services\Machine\MachineCounterService;
use App\Support\MachineTime;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ActionExecutor
{
    public function __construct(
        private readonly MqttMessageParser $parser,
        private readonly MachineCounterService $counters,
    ) {}

    /**
     * Execute all active mappings for a given topic against the parsed payload.
     *
     * @return array List of action execution results for logging.
     */
    public function executeAll(MachineTopic $topic, array $parsedData): array
    {
        $results = [];

        foreach ($topic->activeMappings as $mapping) {
            $results[] = $this->executeSingle($mapping, $parsedData);
        }

        return $results;
    }

    /**
     * Execute one mapping rule. Returns a result descriptor for logging.
     */
    public function executeSingle(TopicMapping $mapping, array $parsedData): array
    {
        $result = [
            'mapping_id' => $mapping->id,
            'action_type' => $mapping->action_type,
            'status' => 'skipped',
            'message' => null,
        ];

        try {
            // Resolve the primary field value
            $fieldValue = $this->parser->resolvePath($mapping->field_path, $parsedData);

            // Evaluate condition
            if (! $this->parser->evaluateCondition($mapping->condition_expr, $fieldValue)) {
                $result['message'] = 'Condition not met';

                return $result;
            }

            $params = $mapping->action_params ?? [];
            $outcome = match ($mapping->action_type) {
                TopicMapping::ACTION_UPDATE_BATCH_STEP => $this->updateBatchStep($params, $parsedData, $fieldValue),
                TopicMapping::ACTION_UPDATE_WORK_ORDER_QTY => $this->updateWorkOrderQty($mapping, $params, $parsedData, $fieldValue),
                TopicMapping::ACTION_COUNT_STEP => $this->countStep($mapping, $params, $parsedData, $fieldValue),
                TopicMapping::ACTION_CREATE_ISSUE => $this->createIssue($params, $parsedData, $fieldValue),
                TopicMapping::ACTION_UPDATE_LINE_STATUS => $this->updateLineStatus($params, $parsedData, $fieldValue),
                TopicMapping::ACTION_SET_WORK_ORDER_STATUS => $this->setWorkOrderStatus($params, $parsedData, $fieldValue),
                TopicMapping::ACTION_WEBHOOK_FORWARD => $this->webhookForward($params, $parsedData),
                TopicMapping::ACTION_LOG_EVENT => ['logged' => true],
                default => throw new \InvalidArgumentException("Unknown action: {$mapping->action_type}"),
            };

            $result['status'] = 'ok';
            $result['message'] = json_encode($outcome);
        } catch (\Throwable $e) {
            $result['status'] = 'error';
            $result['message'] = $e->getMessage();
            Log::warning('ActionExecutor error', [
                'mapping_id' => $mapping->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    // ── Action handlers ──────────────────────────────────────────────────────

    private function updateBatchStep(array $params, array $data, mixed $fieldValue): array
    {
        // params: { step_id_path, result_path, result (static), batch_id_path, step_order }
        $stepId = $this->resolveParam($params, 'step_id_path', $data);
        $batchId = $this->resolveParam($params, 'batch_id_path', $data);
        $stepOrder = $this->resolveParam($params, 'step_number_path', $data)
            ?? $this->resolveParam($params, 'step_order_path', $data)
            ?? ($params['step_number'] ?? $params['step_order'] ?? null);
        $result = $this->resolveParam($params, 'result_path', $data) ?? ($params['result'] ?? 'done');

        $step = null;
        if ($stepId) {
            $step = BatchStep::find($stepId);
        } elseif ($batchId && $stepOrder !== null) {
            $step = BatchStep::where('batch_id', $batchId)
                ->where('step_number', $stepOrder)
                ->first();
        }

        if (! $step) {
            throw new \RuntimeException("BatchStep not found (step_id={$stepId}, batch_id={$batchId})");
        }

        if ($step->batch?->workOrder?->usesStepLedger()) {
            throw new \DomainException('Transfer steps must be completed through the production workflow after quantities are recorded.');
        }

        // A step is either done or not; there is no "failed" step status. A
        // machine reporting a failed step is reporting a fault — that belongs
        // in a create_issue mapping, not in a status nothing else recognises.
        $newStatus = match (strtolower(trim((string) $result))) {
            'done', 'completed', '1', 'true' => BatchStep::STATUS_DONE,
            default => throw new \RuntimeException("Unsupported step result: {$result} — only a completed step can be reported; use a Create Issue mapping for failures."),
        };

        $step->update([
            'status' => $newStatus,
            'completed_at' => now(),
        ]);

        return ['step_id' => $step->id, 'new_status' => $newStatus];
    }

    private function updateWorkOrderQty(TopicMapping $mapping, array $params, array $data, mixed $fieldValue): array
    {
        return $this->counters->withChannel($mapping, fn ($counter) => $counter->configured_at
            ? $this->countReading($counter, $mapping, $data, $this->resolveParam($params, 'qty_path', $data) ?? $fieldValue)
            : app(\App\Services\Machine\LegacyMachineCounting::class)->updateWorkOrderQty($params, $data, $fieldValue));
    }

    private function countStep(TopicMapping $mapping, array $params, array $data, mixed $fieldValue): array
    {
        return $this->counters->withChannel($mapping, fn ($counter) => $counter->configured_at
            ? $this->countReading($counter, $mapping, $data, $this->resolveParam($params, 'increment_path', $data) ?? ($params['increment'] ?? 1))
            : app(\App\Services\Machine\LegacyMachineCounting::class)->countStep($mapping, $params, $data, $fieldValue));
    }

    private function countReading(\App\Models\MachineCounter $counter, TopicMapping $mapping, array $data, mixed $value): array
    {
        // Payload routing hints never override the supervisor's explicit assignment.
        $params = $mapping->action_params ?? [];
        $eventId = $this->parser->resolvePath($params['event_id_path'] ?? '$.event_id', $data);
        $timestamp = $this->parser->resolvePath($params['timestamp_path'] ?? '$.timestamp', $data);
        $at = is_scalar($timestamp) ? MachineTime::parse($timestamp) : null;
        $reading = $this->counters->ingest($counter, $value, $at, is_scalar($eventId) ? (string) $eventId : null, $this->counters->sourceSnapshot($mapping));

        return ['reading_id' => $reading->id, 'counter_status' => $reading->status, 'applied_qty' => (float) $reading->applied_qty];
    }

    private function createIssue(array $params, array $data, mixed $fieldValue): array
    {
        // params: { issue_type_id(_path), work_order_no(_path), title(_path), description(_path) }
        $issueTypeId = $this->resolveParam($params, 'issue_type_id_path', $data) ?? ($params['issue_type_id'] ?? null);
        $orderNo = $this->resolveParam($params, 'work_order_no_path', $data) ?? ($params['work_order_no'] ?? null);
        $description = $this->resolveParam($params, 'description_path', $data)
            ?? ($params['description'] ?? 'Machine-generated issue');
        $title = $this->resolveParam($params, 'title_path', $data) ?? ($params['title'] ?? null);
        $title = mb_substr(trim((string) ($title ?? $description)), 0, 255) ?: 'Machine-generated issue';

        if (! $issueTypeId || ! IssueType::whereKey($issueTypeId)->exists()) {
            throw new \RuntimeException("Issue type not found (issue_type_id={$issueTypeId}) — set it on the mapping.");
        }

        $workOrderId = $orderNo ? WorkOrder::where('order_no', $orderNo)->value('id') : null;

        // A machine keeps reporting a fault for as long as it lasts — often
        // every second. One unresolved issue per fault is what the floor needs,
        // not hundreds; a new one is filed once the previous is resolved.
        $open = Issue::query()
            ->where('issue_type_id', $issueTypeId)
            ->where('work_order_id', $workOrderId)
            ->where('title', $title)
            ->whereNull('reported_by_id')
            ->whereIn('status', [Issue::STATUS_OPEN, Issue::STATUS_ACKNOWLEDGED])
            ->value('id');
        if ($open) {
            return ['issue_id' => $open, 'duplicate' => true];
        }

        $attributes = [
            'issue_type_id' => $issueTypeId,
            'work_order_id' => $workOrderId,
            'title' => $title,
            'description' => (string) $description,
            'reported_by_id' => null, // machine-generated
        ];
        if ($workOrderId === null) {
            // Nothing to block; the type's blocking flag applies to an order.
            $attributes['blocks_work_order'] = false;
        }
        $issue = app(IssueService::class)->createIssue($attributes);

        return ['issue_id' => $issue->id];
    }

    private function updateLineStatus(array $params, array $data, mixed $fieldValue): array
    {
        // params: { line_id (static), line_code_path, status_id (static), status_code_path }
        $lineId = $this->resolveParam($params, 'line_id_path', $data) ?? ($params['line_id'] ?? null);
        $lineCode = $this->resolveParam($params, 'line_code_path', $data) ?? ($params['line_code'] ?? null);
        $statusId = $this->resolveParam($params, 'status_id_path', $data) ?? ($params['status_id'] ?? null);
        // Line statuses have a name, not a code; "status_code" is kept as the
        // param name existing mappings use, and matched against the name.
        $statusName = $this->resolveParam($params, 'status_name_path', $data)
            ?? $this->resolveParam($params, 'status_code_path', $data)
            ?? ($params['status_name'] ?? $params['status_code'] ?? null);

        $line = $lineId
            ? Line::find($lineId)
            : Line::where('code', $lineCode)->first();

        if (! $line) {
            throw new \RuntimeException("Line not found (id={$lineId}, code={$lineCode})");
        }

        // The line's own status wins over a shared one of the same name.
        $lineStatus = $statusId
            ? LineStatus::find($statusId)
            : ($statusName === null ? null : LineStatus::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $statusName))])
                ->where(fn ($q) => $q->where('line_id', $line->id)->orWhereNull('line_id'))
                ->orderByRaw('CASE WHEN line_id IS NULL THEN 1 ELSE 0 END')
                ->first());

        if (! $lineStatus) {
            throw new \RuntimeException("LineStatus not found (id={$statusId}, name={$statusName})");
        }

        // Update most recent in-progress work order on this line
        $workOrder = WorkOrder::where('line_id', $line->id)
            ->where('status', WorkOrder::STATUS_IN_PROGRESS)
            ->latest()
            ->first();

        if ($workOrder) {
            $workOrder->update(['line_status_id' => $lineStatus->id]);
        }

        return ['line_id' => $line->id, 'line_status_id' => $lineStatus->id];
    }

    private function setWorkOrderStatus(array $params, array $data, mixed $fieldValue): array
    {
        // params: { order_no_path, order_id (static), status (static), status_path }
        $orderNo = $this->resolveParam($params, 'order_no_path', $data) ?? ($params['order_no'] ?? null);
        $orderId = $this->resolveParam($params, 'order_id_path', $data) ?? ($params['order_id'] ?? null);
        $status = $this->resolveParam($params, 'status_path', $data) ?? ($params['status'] ?? null);

        // Machines send these in any case ("in_progress", "IN_PROGRESS"); the
        // order must end up with the status the rest of OpenMES knows, or it
        // drops out of every list and board that filters by status.
        $status = match (strtoupper(trim((string) $status))) {
            'PENDING' => WorkOrder::STATUS_PENDING,
            'ACCEPTED' => WorkOrder::STATUS_ACCEPTED,
            'IN_PROGRESS' => WorkOrder::STATUS_IN_PROGRESS,
            'COMPLETED', 'DONE' => WorkOrder::STATUS_DONE,
            'PAUSED' => WorkOrder::STATUS_PAUSED,
            'REJECTED' => WorkOrder::STATUS_REJECTED,
            default => throw new \RuntimeException("Invalid work order status: {$status}"),
        };

        $workOrder = $orderNo
            ? WorkOrder::where('order_no', $orderNo)->first()
            : WorkOrder::find($orderId);

        if (! $workOrder) {
            throw new \RuntimeException('WorkOrder not found');
        }

        if ($workOrder->usesStepLedger()) {
            throw new \DomainException('Transfer order status must be changed through the production workflow.');
        }

        // Held orders resume through the stop workflow or by resolving their
        // issue (a line block holds every order on the line); finished orders
        // do not reopen. A machine message must not bypass either.
        if (in_array($workOrder->status, [...WorkOrder::HELD_STATUSES, ...WorkOrder::TERMINAL_STATUSES], true)) {
            throw new \DomainException("Work order {$workOrder->order_no} is {$workOrder->status}; its status cannot be changed from a machine message.");
        }

        $workOrder->update(['status' => $status]);

        return ['order_no' => $workOrder->order_no, 'new_status' => $status];
    }

    private function webhookForward(array $params, array $data): array
    {
        // params: { url, method (GET/POST), headers (object) }
        $url = $params['url'] ?? null;
        $method = strtolower($params['method'] ?? 'post');
        $headers = $params['headers'] ?? [];

        if (! $url || ! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \RuntimeException('Invalid or missing webhook URL');
        }

        // Block SSRF — reject requests to private/loopback/metadata addresses
        $host = parse_url($url, PHP_URL_HOST);
        if ($host) {
            $ip = gethostbyname($host);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                foreach ([
                    FILTER_FLAG_NO_PRIV_RANGE,
                    FILTER_FLAG_NO_RES_RANGE,
                ] as $flag) {
                    if (! filter_var($ip, FILTER_VALIDATE_IP, $flag)) {
                        throw new \RuntimeException('Webhook URL resolves to a private/reserved address');
                    }
                }
                // Block AWS/GCP/Azure metadata endpoints explicitly
                if (in_array($ip, ['169.254.169.254', '169.254.170.2', '100.100.100.200'])) {
                    throw new \RuntimeException('Webhook URL resolves to a private/reserved address');
                }
            }
        }

        $response = Http::withHeaders($headers)
            ->timeout(5)
            ->{$method}($url, $data);

        return ['status_code' => $response->status(), 'ok' => $response->successful()];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Resolve a param value from action_params.
     * If the key ends with '_path', resolve that path against payload data.
     */
    private function resolveParam(array $params, string $pathKey, array $data): mixed
    {
        if (! isset($params[$pathKey])) {
            return null;
        }

        return $this->parser->resolvePath($params[$pathKey], $data);
    }
}
