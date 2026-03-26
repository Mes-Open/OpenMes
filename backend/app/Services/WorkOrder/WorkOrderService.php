<?php

namespace App\Services\WorkOrder;

use App\Contracts\Services\WorkOrderServiceInterface;
use App\Models\WorkOrder;
use App\Models\ProcessTemplate;
use App\Models\Batch;
use App\Models\BatchStep;
use Illuminate\Support\Facades\DB;

class WorkOrderService implements WorkOrderServiceInterface
{
    /**
     * Create a new work order with process snapshot.
     *
     * @param array $data
     * @return WorkOrder
     * @throws \Exception
     */
    public function createWorkOrder(array $data): WorkOrder
    {
        return DB::transaction(function () use ($data) {
            // Get the active process template for this product type (optional)
            $processTemplate = isset($data['product_type_id'])
                ? ProcessTemplate::where('product_type_id', $data['product_type_id'])
                    ->where('is_active', true)
                    ->orderBy('version', 'desc')
                    ->first()
                : null;

            // Generate process snapshot (immutable copy) — null if no template
            $processSnapshot = $processTemplate?->toSnapshot();

            // Create work order
            $workOrder = WorkOrder::create([
                'order_no' => $data['order_no'],
                'line_id' => $data['line_id'] ?? null,
                'product_type_id' => $data['product_type_id'] ?? null,
                'process_snapshot' => $processSnapshot,
                'planned_qty' => $data['planned_qty'],
                'produced_qty' => 0,
                'status' => WorkOrder::STATUS_PENDING,
                'priority' => $data['priority'] ?? 0,
                'due_date' => $data['due_date'] ?? null,
                'description' => $data['description'] ?? null,
                'extra_data' => $data['extra_data'] ?? null,
            ]);

            event(new \App\Events\WorkOrder\WorkOrderCreated($workOrder));

            return $workOrder;
        });
    }

    /**
     * Update an existing work order.
     *
     * @param WorkOrder $workOrder
     * @param array $data
     * @return WorkOrder
     */
    public function updateWorkOrder(WorkOrder $workOrder, array $data): WorkOrder
    {
        // Don't allow updates to completed work orders
        if ($workOrder->status === WorkOrder::STATUS_DONE) {
            throw new \Exception('Cannot update completed work order');
        }

        $workOrder->update([
            'planned_qty' => $data['planned_qty'] ?? $workOrder->planned_qty,
            'priority' => $data['priority'] ?? $workOrder->priority,
            'due_date' => $data['due_date'] ?? $workOrder->due_date,
            'description' => $data['description'] ?? $workOrder->description,
        ]);

        return $workOrder->fresh();
    }

    /**
     * Create a new batch for a work order.
     *
     * @param WorkOrder $workOrder
     * @param float $targetQty
     * @return Batch
     */
    public function createBatch(WorkOrder $workOrder, float $targetQty): Batch
    {
        return DB::transaction(function () use ($workOrder, $targetQty) {
            // Calculate next batch number
            $lastBatch = $workOrder->batches()->reorder('batch_number', 'desc')->first();
            $batchNumber = $lastBatch ? $lastBatch->batch_number + 1 : 1;

            // Create batch
            $batch = Batch::create([
                'work_order_id' => $workOrder->id,
                'batch_number' => $batchNumber,
                'target_qty' => $targetQty,
                'produced_qty' => 0,
                'status' => Batch::STATUS_PENDING,
            ]);

            event(new \App\Events\Batch\BatchCreated($batch));

            // Create batch steps from process snapshot (skipped if no snapshot)
            if (!empty($workOrder->process_snapshot)) {
                $this->createBatchStepsFromSnapshot($batch, $workOrder->process_snapshot);
            }

            return $batch;
        });
    }

    /**
     * Create batch steps from work order process snapshot.
     *
     * @param Batch $batch
     * @param array $processSnapshot
     * @return void
     */
    protected function createBatchStepsFromSnapshot(Batch $batch, array $processSnapshot): void
    {
        $steps = $processSnapshot['steps'] ?? [];

        foreach ($steps as $stepData) {
            BatchStep::create([
                'batch_id' => $batch->id,
                'step_number' => $stepData['step_number'],
                'name' => $stepData['name'],
                'instruction' => $stepData['instruction'] ?? null,
                'status' => BatchStep::STATUS_PENDING,
            ]);
        }
    }

    /**
     * Update work order status based on batches and issues.
     *
     * @param WorkOrder $workOrder
     * @return void
     */
    public function updateWorkOrderStatus(WorkOrder $workOrder): void
    {
        $oldStatus = $workOrder->status;
        $newStatus = $oldStatus;

        // Check if blocked by issues
        if ($workOrder->isBlocked()) {
            $newStatus = WorkOrder::STATUS_BLOCKED;
        } elseif ($workOrder->isComplete()) {
            $newStatus = WorkOrder::STATUS_DONE;
        } else {
            // Check if any batch is in progress
            $hasInProgressBatch = $workOrder->batches()
                ->where('status', Batch::STATUS_IN_PROGRESS)
                ->exists();

            if ($hasInProgressBatch) {
                $newStatus = WorkOrder::STATUS_IN_PROGRESS;
            } elseif ($oldStatus !== WorkOrder::STATUS_PENDING && !in_array($oldStatus, WorkOrder::TERMINAL_STATUSES)) {
                $newStatus = WorkOrder::STATUS_PENDING;
            }
        }

        if ($newStatus !== $oldStatus) {
            $workOrder->update([
                'status' => $newStatus,
                'completed_at' => ($newStatus === WorkOrder::STATUS_DONE) ? now() : $workOrder->completed_at,
            ]);

            event(new \App\Events\WorkOrder\WorkOrderUpdated($workOrder, ['status' => $newStatus]));

            if ($newStatus === WorkOrder::STATUS_DONE) {
                event(new \App\Events\WorkOrder\WorkOrderCompleted($workOrder));
            }

            if ($newStatus === WorkOrder::STATUS_BLOCKED) {
                event(new \App\Events\WorkOrder\WorkOrderBlocked($workOrder));
            } elseif ($oldStatus === WorkOrder::STATUS_BLOCKED) {
                event(new \App\Events\WorkOrder\WorkOrderUnblocked($workOrder));
            }
        }
    }

    /**
     * Get work orders for a specific user's assigned lines.
     *
     * @param \App\Models\User $user
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getWorkOrdersForUser($user, array $filters = [])
    {
        $query = WorkOrder::forUser($user)
            ->with(['line', 'productType', 'batches.steps']);

        // Apply filters
        if (isset($filters['status'])) {
            $query->status($filters['status']);
        }

        if (isset($filters['line_id'])) {
            $query->forLine($filters['line_id']);
        }

        // Default ordering
        $query->byPriority();

        return $query->get();
    }
}
