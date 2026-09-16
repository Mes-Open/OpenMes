<?php

namespace App\Services\Machine;

use App\Models\BatchStep;
use App\Models\Line;
use App\Models\MachineCounter;
use App\Models\MachineEvent;
use App\Models\MachineTag;
use App\Models\TopicMapping;
use App\Models\WorkOrder;
use App\Services\Connectivity\MqttMessageParser;
use App\Services\WorkOrder\MachineProductionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** Upgrade compatibility for sources that have not opted into explicit counting. */
class LegacyMachineCounting
{
    public function __construct(private readonly MqttMessageParser $parser, private readonly MachineProductionService $production) {}

    public function ingest(MachineCounter $counter, MachineTag $tag, mixed $value, ?Carbon $at): void
    {
        if (! $tag->workstation || ! is_numeric($value)) {
            return;
        }
        $order = $this->production->resolveActiveWorkOrder($tag->workstation);
        if ($order?->usesStepLedger()) {
            $counter->readings()->create(['status' => 'legacy_incompatible', 'observed_at' => $at ?? now(),
                'payload' => ['value' => $value, 'reason' => 'Explicit counting is required for transfer production.']]);

            return;
        }
        $key = "machine_tag_last:{$tag->id}";
        $last = Cache::get($key);
        Cache::put($key, (float) $value, now()->addDay());
        $delta = ($last === null || $value < $last) ? ($last === null ? 0 : $value) : $value - $last;
        if ($delta <= 0) {
            return;
        }
        $kind = $tag->signal_type === 'reject_count' ? 'reject' : 'good';
        MachineEvent::create(['workstation_id' => $tag->workstation_id, 'machine_connection_id' => $tag->machine_connection_id,
            'event_type' => MachineEvent::TYPE_COUNTER, 'event_timestamp' => $at ?? now(), 'correlation_id' => (string) Str::uuid(),
            'payload' => ['kind' => $kind, 'value' => (float) $value, 'delta' => $delta]]);
        if ($kind === 'good' && $order) {
            $this->production->recordGoodCount($order, $delta);
        }
    }

    private function guard(WorkOrder $order): void
    {
        if ($order->usesStepLedger()) {
            throw new \DomainException('Explicit counting is required for transfer production. Configure this channel before resuming machine counts.');
        }
    }

    public function updateWorkOrderQty(array $params, array $data, mixed $fieldValue): array
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

        $this->guard($workOrder);

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
     * params: { line_id (static) | line_id_path, workstation_id (preferred) |
     *           workstation_id_path, step_number | step_number_path,
     *           increment (default 1) | increment_path, also_count_work_order }
     */
    public function countStep(TopicMapping $mapping, array $params, array $data, mixed $fieldValue): array
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

        $workOrder = WorkOrder::whereKey($workOrder->id)->lockForUpdate()->firstOrFail();
        $this->guard($workOrder);

        // A break-beam pulse is one whole unit; normalise once to an int so the
        // per-step counter and the work-order good-count stay in lock-step.
        $increment = (int) ($this->resolveParam($params, 'increment_path', $data) ?? $params['increment'] ?? 1);
        if ($increment < 1) {
            $increment = 1;
        }

        // Target the step by workstation (preferred — a stable, named station on
        // the line) or by step_number. Whichever the mapping supplies.
        $workstationId = $this->resolveParam($params, 'workstation_id_path', $data) ?? ($params['workstation_id'] ?? null);
        $stepNumber = $this->resolveParam($params, 'step_number_path', $data) ?? ($params['step_number'] ?? null);

        $stepResult = null;
        if ($workstationId !== null || $stepNumber !== null) {
            $query = BatchStep::whereHas('batch', fn ($b) => $b->where('work_order_id', $workOrder->id));
            if ($workstationId !== null) {
                $query->where('workstation_id', (int) $workstationId);
            } else {
                $query->where('step_number', (int) $stepNumber);
            }
            $step = $query->latest('id')->first();
            if ($step) {
                $step->increment('passed_qty', $increment);
                $stepResult = [
                    'step_number' => $step->step_number,
                    'workstation_id' => $step->workstation_id,
                    'passed_qty' => (int) $step->fresh()->passed_qty,
                ];
            } else {
                $stepResult = [
                    'workstation_id' => $workstationId,
                    'step_number' => $stepNumber,
                    'skipped' => 'step not found on active work order',
                ];
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

    private function resolveParam(array $params, string $key, array $data): mixed
    {
        return isset($params[$key]) ? $this->parser->resolvePath($params[$key], $data) : null;
    }
}
