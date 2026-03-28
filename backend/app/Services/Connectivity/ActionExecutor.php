<?php

namespace App\Services\Connectivity;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\Issue;
use App\Models\Line;
use App\Models\LineStatus;
use App\Models\MachineTopic;
use App\Models\TopicMapping;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ActionExecutor
{
    public function __construct(
        private readonly MqttMessageParser $parser
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
            'mapping_id'  => $mapping->id,
            'action_type' => $mapping->action_type,
            'status'      => 'skipped',
            'message'     => null,
        ];

        try {
            // Resolve the primary field value
            $fieldValue = $this->parser->resolvePath($mapping->field_path, $parsedData);

            // Evaluate condition
            if (!$this->parser->evaluateCondition($mapping->condition_expr, $fieldValue)) {
                $result['message'] = 'Condition not met';
                return $result;
            }

            $params = $mapping->action_params ?? [];
            $outcome = match ($mapping->action_type) {
                TopicMapping::ACTION_UPDATE_BATCH_STEP     => $this->updateBatchStep($params, $parsedData, $fieldValue),
                TopicMapping::ACTION_UPDATE_WORK_ORDER_QTY => $this->updateWorkOrderQty($params, $parsedData, $fieldValue),
                TopicMapping::ACTION_CREATE_ISSUE          => $this->createIssue($params, $parsedData, $fieldValue),
                TopicMapping::ACTION_UPDATE_LINE_STATUS    => $this->updateLineStatus($params, $parsedData, $fieldValue),
                TopicMapping::ACTION_SET_WORK_ORDER_STATUS => $this->setWorkOrderStatus($params, $parsedData, $fieldValue),
                TopicMapping::ACTION_WEBHOOK_FORWARD       => $this->webhookForward($params, $parsedData),
                TopicMapping::ACTION_LOG_EVENT             => ['logged' => true],
                default => throw new \InvalidArgumentException("Unknown action: {$mapping->action_type}"),
            };

            $result['status']  = 'ok';
            $result['message'] = json_encode($outcome);
        } catch (\Throwable $e) {
            $result['status']  = 'error';
            $result['message'] = $e->getMessage();
            Log::warning('ActionExecutor error', [
                'mapping_id' => $mapping->id,
                'error'      => $e->getMessage(),
            ]);
        }

        return $result;
    }

    // ── Action handlers ──────────────────────────────────────────────────────

    private function updateBatchStep(array $params, array $data, mixed $fieldValue): array
    {
        // params: { step_id_path, result_path, result (static), batch_id_path, step_order }
        $stepId    = $this->resolveParam($params, 'step_id_path', $data);
        $batchId   = $this->resolveParam($params, 'batch_id_path', $data);
        $stepOrder = $this->resolveParam($params, 'step_order_path', $data) ?? ($params['step_order'] ?? null);
        $result    = $this->resolveParam($params, 'result_path', $data) ?? ($params['result'] ?? 'done');

        $step = null;
        if ($stepId) {
            $step = BatchStep::find($stepId);
        } elseif ($batchId && $stepOrder !== null) {
            $step = BatchStep::where('batch_id', $batchId)
                ->where('step_order', $stepOrder)
                ->first();
        }

        if (!$step) {
            throw new \RuntimeException("BatchStep not found (step_id={$stepId}, batch_id={$batchId})");
        }

        $newStatus = match ($result) {
            'done', 'completed', '1', 'true' => 'done',
            'failed', 'error', '0', 'false'  => 'failed',
            default                           => 'done',
        };

        $step->update([
            'status'       => $newStatus,
            'completed_at' => $newStatus === 'done' ? now() : null,
        ]);

        return ['step_id' => $step->id, 'new_status' => $newStatus];
    }

    private function updateWorkOrderQty(array $params, array $data, mixed $fieldValue): array
    {
        // params: { order_no_path, order_id (static), qty_path, qty_increment (bool) }
        $orderNo  = $this->resolveParam($params, 'order_no_path', $data) ?? ($params['order_no'] ?? null);
        $orderId  = $this->resolveParam($params, 'order_id_path', $data) ?? ($params['order_id'] ?? null);
        $qty      = $this->resolveParam($params, 'qty_path', $data) ?? $fieldValue;
        $increment = (bool) ($params['qty_increment'] ?? false);

        $workOrder = null;
        if ($orderNo) {
            $workOrder = WorkOrder::where('order_no', $orderNo)->first();
        } elseif ($orderId) {
            $workOrder = WorkOrder::find($orderId);
        }

        if (!$workOrder) {
            throw new \RuntimeException("WorkOrder not found (order_no={$orderNo})");
        }

        if ($increment) {
            $workOrder->increment('produced_qty', (float) $qty);
        } else {
            $workOrder->update(['produced_qty' => (float) $qty]);
        }

        return ['order_no' => $workOrder->order_no, 'produced_qty' => $workOrder->fresh()->produced_qty];
    }

    private function createIssue(array $params, array $data, mixed $fieldValue): array
    {
        // params: { issue_type_id, work_order_no_path, description_path, description (static) }
        $issueTypeId = $this->resolveParam($params, 'issue_type_id_path', $data) ?? ($params['issue_type_id'] ?? null);
        $orderNo     = $this->resolveParam($params, 'work_order_no_path', $data) ?? ($params['work_order_no'] ?? null);
        $description = $this->resolveParam($params, 'description_path', $data)
            ?? ($params['description'] ?? 'Machine-generated issue');

        $workOrderId = null;
        if ($orderNo) {
            $workOrderId = WorkOrder::where('order_no', $orderNo)->value('id');
        }

        $issue = Issue::create([
            'issue_type_id'  => $issueTypeId,
            'work_order_id'  => $workOrderId,
            'description'    => (string) $description,
            'status'         => 'open',
            'reported_by'    => null, // machine-generated
        ]);

        return ['issue_id' => $issue->id];
    }

    private function updateLineStatus(array $params, array $data, mixed $fieldValue): array
    {
        // params: { line_id (static), line_code_path, status_id (static), status_code_path }
        $lineId      = $this->resolveParam($params, 'line_id_path', $data) ?? ($params['line_id'] ?? null);
        $lineCode    = $this->resolveParam($params, 'line_code_path', $data) ?? ($params['line_code'] ?? null);
        $statusId    = $this->resolveParam($params, 'status_id_path', $data) ?? ($params['status_id'] ?? null);
        $statusCode  = $this->resolveParam($params, 'status_code_path', $data) ?? ($params['status_code'] ?? null);

        $line = $lineId
            ? Line::find($lineId)
            : Line::where('code', $lineCode)->first();

        if (!$line) {
            throw new \RuntimeException("Line not found (id={$lineId}, code={$lineCode})");
        }

        $lineStatus = $statusId
            ? LineStatus::find($statusId)
            : LineStatus::where('code', $statusCode)->first();

        if (!$lineStatus) {
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
        $orderNo  = $this->resolveParam($params, 'order_no_path', $data) ?? ($params['order_no'] ?? null);
        $orderId  = $this->resolveParam($params, 'order_id_path', $data) ?? ($params['order_id'] ?? null);
        $status   = $this->resolveParam($params, 'status_path', $data) ?? ($params['status'] ?? null);

        $allowed = ['pending', 'accepted', 'in_progress', 'completed', 'paused', 'rejected'];
        if (!in_array($status, $allowed)) {
            throw new \RuntimeException("Invalid work order status: {$status}");
        }

        $workOrder = $orderNo
            ? WorkOrder::where('order_no', $orderNo)->first()
            : WorkOrder::find($orderId);

        if (!$workOrder) {
            throw new \RuntimeException("WorkOrder not found");
        }

        $workOrder->update(['status' => $status]);

        return ['order_no' => $workOrder->order_no, 'new_status' => $status];
    }

    private function webhookForward(array $params, array $data): array
    {
        // params: { url, method (GET/POST), headers (object) }
        $url     = $params['url'] ?? null;
        $method  = strtolower($params['method'] ?? 'post');
        $headers = $params['headers'] ?? [];

        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \RuntimeException("Invalid or missing webhook URL");
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
        if (!isset($params[$pathKey])) {
            return null;
        }
        return $this->parser->resolvePath($params[$pathKey], $data);
    }
}
