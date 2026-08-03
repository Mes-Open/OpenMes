<?php

namespace Modules\ExampleShowcase;

use App\Events\Batch\BatchCreated;
use App\Events\BatchStep\StepCompleted;
use App\Events\BatchStep\StepStarted;
use App\Events\Machine\WorkstationStateChanged;
use App\Events\MachineMessageReceived;
use App\Events\Resource\ResourceChanged;
use App\Events\Schedule\WorkOrderScheduled;
use App\Events\User\UserAssignedToLine;
use App\Events\WorkOrder\WorkOrderCompleted;
use App\Events\WorkOrder\WorkOrderCreated;
use App\Events\WorkOrder\WorkOrderUpdated;
use Illuminate\Support\Facades\Log;

/**
 * Every event hook OpenMES fires, in one place — PrestaShop-style: one method
 * per hook (think hookActionOrderStatusUpdate()). The service provider wires each
 * domain event to the matching method here.
 *
 * IMPORTANT — stay non-intrusive: these bodies only READ the payload and log.
 * A module must never mutate core state from inside an event handler; doing so
 * turns an observer into a hidden participant in the flow and can corrupt the
 * very process it is watching (double counts, re-entrant events, broken
 * transactions). Replace the log calls with your own SIDE effects — notify a
 * webhook, push to an external ERP, write to your module's own tables — but
 * leave the core objects untouched.
 */
class Hooks
{
    private function log(string $hook, array $context = []): void
    {
        Log::channel('daily')->info("[ExampleShowcase] {$hook}", $context);
    }

    // ── Work orders ──────────────────────────────────────────────────────────

    /** Fired right after a work order is created (any source: UI, CSV, ERP API). */
    public function onWorkOrderCreated(WorkOrderCreated $e): void
    {
        $this->log('workOrderCreated', [
            'order_no' => $e->workOrder->order_no,
            'planned_qty' => $e->workOrder->planned_qty,
            'line_id' => $e->workOrder->line_id,
        ]);
    }

    /** Fired after an existing work order is edited. `changes` is the dirty set. */
    public function onWorkOrderUpdated(WorkOrderUpdated $e): void
    {
        $this->log('workOrderUpdated', [
            'order_no' => $e->workOrder->order_no,
            'changed' => array_keys($e->changes),
        ]);
    }

    /** Fired the first time a work order reaches DONE (idempotent upstream). */
    public function onWorkOrderCompleted(WorkOrderCompleted $e): void
    {
        $this->log('workOrderCompleted', [
            'order_no' => $e->workOrder->order_no,
            'produced_qty' => $e->workOrder->produced_qty,
        ]);
    }

    // ── Batches & steps ──────────────────────────────────────────────────────

    /** Fired when a production batch is created for a work order. */
    public function onBatchCreated(BatchCreated $e): void
    {
        $this->log('batchCreated', ['batch_id' => $e->batch->id]);
    }

    /** Fired when an operator (or machine) starts a batch step. */
    public function onStepStarted(StepStarted $e): void
    {
        $this->log('stepStarted', [
            'batch_step_id' => $e->batchStep->id,
            'name' => $e->batchStep->name,
        ]);
    }

    /** Fired when a batch step is completed. */
    public function onStepCompleted(StepCompleted $e): void
    {
        $this->log('stepCompleted', [
            'batch_step_id' => $e->batchStep->id,
            'name' => $e->batchStep->name,
        ]);
    }

    // ── Machine / shop-floor ─────────────────────────────────────────────────

    /** Fired when the signal pipeline transitions a workstation to a new state. */
    public function onWorkstationStateChanged(WorkstationStateChanged $e): void
    {
        $this->log('workstationStateChanged', [
            'workstation' => $e->workstation->code,
            'from' => $e->from,
            'to' => $e->to,
        ]);
    }

    /** Fired for every inbound MQTT message once it has been parsed/logged. */
    public function onMachineMessageReceived(MachineMessageReceived $e): void
    {
        $this->log('machineMessageReceived', [
            'topic' => $e->message->topic,
            'status' => $e->message->processing_status,
        ]);
    }

    // ── Users ────────────────────────────────────────────────────────────────

    /** Fired when a user is assigned to a production line. */
    public function onUserAssignedToLine(UserAssignedToLine $e): void
    {
        $this->log('userAssignedToLine', [
            'user_id' => $e->user->id,
            'line_id' => $e->line->id,
        ]);
    }

    // ── Generic CRUD + scheduling ────────────────────────────────────────────

    /**
     * Fired for every create/update/delete of a curated resource (work orders,
     * customers, materials, lines, …). Filter by model class and/or action.
     */
    public function onResourceChanged(ResourceChanged $e): void
    {
        $this->log('resourceChanged', [
            'type' => $e->type(),
            'id' => $e->model->getKey(),
            'action' => $e->action,
        ]);
    }

    /** Fired when a work order's placement changes on the planner. */
    public function onWorkOrderScheduled(WorkOrderScheduled $e): void
    {
        $this->log('workOrderScheduled', [
            'order_no' => $e->workOrder->order_no,
            'changed' => array_keys($e->changes),
        ]);
    }
}
