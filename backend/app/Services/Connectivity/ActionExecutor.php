<?php

namespace App\Services\Connectivity;

use App\Models\BatchStep;
use App\Models\Issue;
use App\Models\Line;
use App\Models\LineStatus;
use App\Models\MachineTopic;
use App\Models\TopicMapping;
use App\Models\WorkOrder;
use App\Services\WorkOrder\MachineProductionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ActionExecutor
{
    public function __construct(
        private readonly MqttMessageParser $parser,
        private readonly MachineProductionService $production,
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
                TopicMapping::ACTION_UPDATE_WORK_ORDER_QTY => $this->updateWorkOrderQty($params, $parsedData, $fieldValue),
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
        $stepOrder = $this->resolveParam($params, 'step_order_path', $data) ?? ($params['step_order'] ?? null);
        $result = $this->resolveParam($params, 'result_path', $data) ?? ($params['result'] ?? 'done');

        $step = null;
        if ($stepId) {
            $step = BatchStep::find($stepId);
        } elseif ($batchId && $stepOrder !== null) {
            $step = BatchStep::where('batch_id', $batchId)
                ->where('step_order', $stepOrder)
                ->first();
        }

        if (! $step) {
            throw new \RuntimeException("BatchStep not found (step_id={$stepId}, batch_id={$batchId})");
        }

        $newStatus = match ($result) {
            'done', 'completed', '1', 'true' => 'done',
            'failed', 'error', '0', 'false' => 'failed',
            default => 'done',
        };

        $step->update([
            'status' => $newStatus,
            'completed_at' => $newStatus === 'done' ? now() : null,
        ]);

        return ['step_id' => $step->id, 'new_status' => $newStatus];
    }

    private function updateWorkOrderQty(array $params, array $data, mixed $fieldValue): array
    {
        // params: { order_no_path, order_id (static), qty_path, qty_increment (bool) }
        $orderNo = $this->resolveParam($params, 'order_no_path', $data) ?? ($params['order_no'] ?? null);
        $orderId = $this->resolveParam($params, 'order_id_path', $data) ?? ($params['order_id'] ?? null);
        $qty = $this->resolveParam($params, 'qty_path', $data) ?? $fieldValue;
        $increment = (bool) ($params['qty_increment'] ?? false);

        $lineId = $this->resolveParam($params, 'line_id_path', $data) ?? ($params['line_id'] ?? null);

        $workOrder = null;
        if ($orderNo) {
            $workOrder = WorkOrder::where('order_no', $orderNo)->first();
        } elseif ($orderId) {
            $workOrder = WorkOrder::find($orderId);
        } elseif ($lineId) {
            // Target whatever order is running on the line — no per-order config.
            $workOrder = $this->activeWorkOrderOnLine((int) $lineId);
        }

        if (! $workOrder) {
            throw new \RuntimeException("WorkOrder not found (order_no={$orderNo}, line_id={$lineId})");
        }

        // Route through the shared machine-count path so counting_source is
        // honoured (an operator-counted order is not touched — this is what
        // eliminates the double-count when both a machine mapping and operator
        // entry target the same order) and the auto-start / auto-complete side
        // effects stay identical to the signal pipeline.
        $applied = $increment
            ? $this->production->recordGoodCount($workOrder, (float) $qty)
            : $this->production->recordAbsoluteCount($workOrder, (float) $qty);

        $workOrder->refresh();

        return [
            'order_no' => $workOrder->order_no,
            'produced_qty' => $workOrder->produced_qty,
            'applied' => $applied,
            'skipped' => $applied ? null : 'work order is not machine-counted (counting_source)',
        ];
    }

    /**
     * Break-beam / interrupt sensor count: each pulse is one unit that has left a
     * station. Resolves the line (explicit param, else the device's assigned
     * line), takes the line's running work order, and increments the passed_qty
     * of the batch step identified by step_number. When the mapping flags this as
     * the finished-goods counting point, the same delta also feeds the work
     * order's produced_qty (through MachineProductionService, so counting_source
     * and auto start/complete are honoured — no double counting).
     *
     * params: { line_id (static) | line_id_path, step_number | step_number_path,
     *           increment (default 1) | increment_path, also_count_work_order }
     */
    private function countStep(TopicMapping $mapping, array $params, array $data, mixed $fieldValue): array
    {
        $lineId = $this->resolveParam($params, 'line_id_path', $data)
            ?? ($params['line_id'] ?? null)
            ?? $mapping->topic?->machineConnection?->line_id;

        $line = $lineId ? Line::find($lineId) : null;
        if (! $line) {
            throw new \RuntimeException("count_step: no line resolved (mapping {$mapping->id})");
        }

        $workOrder = $this->activeWorkOrderOnLine((int) $line->id);
        if (! $workOrder) {
            // Line is idle — nothing to count. Not an error.
            return ['line_id' => $line->id, 'skipped' => 'no in-progress work order on line'];
        }

        $increment = (float) ($this->resolveParam($params, 'increment_path', $data) ?? $params['increment'] ?? 1);
        if ($increment <= 0) {
            $increment = 1;
        }

        $stepNumber = $this->resolveParam($params, 'step_number_path', $data) ?? ($params['step_number'] ?? null);

        $stepResult = null;
        if ($stepNumber !== null) {
            $step = BatchStep::whereHas('batch', fn ($b) => $b->where('work_order_id', $workOrder->id))
                ->where('step_number', (int) $stepNumber)
                ->latest('id')
                ->first();
            if ($step) {
                $step->increment('passed_qty', (int) $increment);
                $stepResult = ['step_number' => (int) $stepNumber, 'passed_qty' => (int) $step->fresh()->passed_qty];
            } else {
                $stepResult = ['step_number' => (int) $stepNumber, 'skipped' => 'step not found on active work order'];
            }
        }

        // Optionally treat this station as the line's finished-goods counting point.
        $countedToWorkOrder = false;
        if ((bool) ($params['also_count_work_order'] ?? false)) {
            $countedToWorkOrder = $this->production->recordGoodCount($workOrder, $increment);
        }

        return [
            'line_id' => $line->id,
            'work_order_id' => $workOrder->id,
            'increment' => $increment,
            'step' => $stepResult,
            'counted_to_work_order' => $countedToWorkOrder,
        ];
    }

    /**
     * The line's current running work order (most recent IN_PROGRESS).
     */
    private function activeWorkOrderOnLine(int $lineId): ?WorkOrder
    {
        return WorkOrder::where('line_id', $lineId)
            ->where('status', WorkOrder::STATUS_IN_PROGRESS)
            ->latest()
            ->first();
    }

    private function createIssue(array $params, array $data, mixed $fieldValue): array
    {
        // params: { issue_type_id, work_order_no_path, description_path, description (static) }
        $issueTypeId = $this->resolveParam($params, 'issue_type_id_path', $data) ?? ($params['issue_type_id'] ?? null);
        $orderNo = $this->resolveParam($params, 'work_order_no_path', $data) ?? ($params['work_order_no'] ?? null);
        $description = $this->resolveParam($params, 'description_path', $data)
            ?? ($params['description'] ?? 'Machine-generated issue');

        $workOrderId = null;
        if ($orderNo) {
            $workOrderId = WorkOrder::where('order_no', $orderNo)->value('id');
        }

        $issue = Issue::create([
            'issue_type_id' => $issueTypeId,
            'work_order_id' => $workOrderId,
            'description' => (string) $description,
            'status' => 'open',
            'reported_by' => null, // machine-generated
        ]);

        return ['issue_id' => $issue->id];
    }

    private function updateLineStatus(array $params, array $data, mixed $fieldValue): array
    {
        // params: { line_id (static), line_code_path, status_id (static), status_code_path }
        $lineId = $this->resolveParam($params, 'line_id_path', $data) ?? ($params['line_id'] ?? null);
        $lineCode = $this->resolveParam($params, 'line_code_path', $data) ?? ($params['line_code'] ?? null);
        $statusId = $this->resolveParam($params, 'status_id_path', $data) ?? ($params['status_id'] ?? null);
        $statusCode = $this->resolveParam($params, 'status_code_path', $data) ?? ($params['status_code'] ?? null);

        $line = $lineId
            ? Line::find($lineId)
            : Line::where('code', $lineCode)->first();

        if (! $line) {
            throw new \RuntimeException("Line not found (id={$lineId}, code={$lineCode})");
        }

        $lineStatus = $statusId
            ? LineStatus::find($statusId)
            : LineStatus::where('code', $statusCode)->first();

        if (! $lineStatus) {
            throw new \RuntimeException("LineStatus not found (id={$statusId}, code={$statusCode})");
        }

        // Update most recent in-progress work order on this line
        $workOrder = WorkOrder::where('line_id', $line->id)
            ->where('status', 'in_progress')
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

        $allowed = ['pending', 'accepted', 'in_progress', 'completed', 'paused', 'rejected'];
        if (! in_array($status, $allowed)) {
            throw new \RuntimeException("Invalid work order status: {$status}");
        }

        $workOrder = $orderNo
            ? WorkOrder::where('order_no', $orderNo)->first()
            : WorkOrder::find($orderId);

        if (! $workOrder) {
            throw new \RuntimeException('WorkOrder not found');
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
