<?php

namespace App\Services;

use App\Models\EventLog;

class EventLogService
{
    /**
     * Log an event.
     *
     * @param string $eventType Event type (e.g., 'WORK_ORDER_CREATED')
     * @param string|null $entityType Entity class name
     * @param int|null $entityId Entity ID
     * @param array $payload Additional data
     * @param int|null $userId User ID (defaults to current user)
     */
    public function log(
        string $eventType,
        ?string $entityType = null,
        ?int $entityId = null,
        array $payload = [],
        ?int $userId = null
    ): void {
        EventLog::create([
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'user_id' => $userId ?? auth()->id(),
            'payload' => $payload,
        ]);
    }

    /**
     * Log work order created event.
     */
    public function workOrderCreated(int $workOrderId, array $data): void
    {
        $this->log(
            'WORK_ORDER_CREATED',
            'App\Models\WorkOrder',
            $workOrderId,
            [
                'order_no' => $data['order_no'] ?? null,
                'line_id' => $data['line_id'] ?? null,
                'planned_qty' => $data['planned_qty'] ?? null,
            ]
        );
    }

    /**
     * Log batch created event.
     */
    public function batchCreated(int $batchId, int $workOrderId, array $data): void
    {
        $this->log(
            'BATCH_CREATED',
            'App\Models\Batch',
            $batchId,
            [
                'work_order_id' => $workOrderId,
                'batch_number' => $data['batch_number'] ?? null,
                'target_qty' => $data['target_qty'] ?? null,
            ]
        );
    }

    /**
     * Log step started event.
     */
    public function stepStarted(int $stepId, int $batchId, array $data): void
    {
        $this->log(
            'STEP_STARTED',
            'App\Models\BatchStep',
            $stepId,
            [
                'batch_id' => $batchId,
                'step_number' => $data['step_number'] ?? null,
                'step_name' => $data['name'] ?? null,
            ]
        );
    }

    /**
     * Log step completed event.
     */
    public function stepCompleted(int $stepId, array $data): void
    {
        $this->log(
            'STEP_COMPLETED',
            'App\Models\BatchStep',
            $stepId,
            [
                'duration_minutes' => $data['duration_minutes'] ?? null,
                'produced_qty' => $data['produced_qty'] ?? null,
            ]
        );
    }

    /**
     * Log work order completed event.
     */
    public function workOrderCompleted(int $workOrderId, array $data): void
    {
        $this->log(
            'WORK_ORDER_COMPLETED',
            'App\Models\WorkOrder',
            $workOrderId,
            [
                'produced_qty' => $data['produced_qty'] ?? null,
                'planned_qty' => $data['planned_qty'] ?? null,
            ]
        );
    }

    /**
     * Log issue created event.
     */
    public function issueCreated(int $issueId, int $workOrderId, array $data): void
    {
        $this->log(
            'ISSUE_CREATED',
            'App\Models\Issue',
            $issueId,
            [
                'work_order_id' => $workOrderId,
                'issue_type' => $data['issue_type'] ?? null,
                'is_blocking' => $data['is_blocking'] ?? false,
                'title' => $data['title'] ?? null,
            ]
        );
    }

    /**
     * Log issue resolved event.
     */
    public function issueResolved(int $issueId, array $data): void
    {
        $this->log(
            'ISSUE_RESOLVED',
            'App\Models\Issue',
            $issueId,
            [
                'resolution_notes' => $data['resolution_notes'] ?? null,
            ]
        );
    }

    /**
     * Log work order imported event.
     */
    public function workOrderImported(int $workOrderId, array $data): void
    {
        $this->log(
            'WORK_ORDER_IMPORTED',
            'App\Models\WorkOrder',
            $workOrderId,
            [
                'source' => 'CSV',
                'action' => $data['action'] ?? 'created', // created or updated
            ]
        );
    }
}
