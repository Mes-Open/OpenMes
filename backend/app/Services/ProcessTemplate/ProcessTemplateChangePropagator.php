<?php

namespace App\Services\ProcessTemplate;

use App\Enums\ChangeEffectivePoint;
use App\Jobs\RefreshNotStartedOrders;
use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\BatchStepDocument;
use App\Models\MaterialAllocation;
use App\Models\ProcessTemplate;
use App\Models\ScheduledOperation;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderSnapshot;
use App\Models\Workstation;
use App\Scopes\TenantScope;
use App\Services\WorkOrder\WorkOrderService;
use App\Services\WorkOrder\WorkOrderSnapshotService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Carries an edit of a process template (steps, checklist items, outputs, media
 * and BOM lines) to the work orders that have not started yet.
 *
 * - An order IN PROGRESS keeps its process snapshot untouched: what the shop floor
 *   was told to build does not change under it.
 * - An order NOT STARTED yet is moved onto the edited template: its steps and its
 *   BOM are rebuilt from its BOM selection the way an order is created
 *   (WorkOrderService::buildProcessSnapshot()) and appended as the next #182
 *   version (reason `process_template_edited`), and the steps of its batches are
 *   regenerated from it. A material therefore follows the template step it is
 *   consumed at, and a line added to or removed from the template reaches the
 *   order. The revision and engineering documents stay as released. Nothing has
 *   to be undone: materials are reserved only when a step starts, and an order
 *   with any material allocation counts as started.
 * - New orders snapshot the template as it is, so they need nothing from here.
 *
 * "Not started" = status PENDING or ACCEPTED, nothing produced, no material
 * allocated, and no batch with any execution (a batch past PENDING, or a step
 * started, done, or skipped by somebody). Anything else — including a PENDING
 * order whose first step was started before its status caught up — counts as in
 * progress.
 *
 * Trigger: model events on the template rows call markDirty(), which queues a
 * RefreshNotStartedOrders job once the edit commits (unique per template).
 */
class ProcessTemplateChangePropagator
{
    /** Order statuses in which an order may still be moved onto an edited template. */
    public const NOT_STARTED_STATUSES = [WorkOrder::STATUS_PENDING, WorkOrder::STATUS_ACCEPTED];

    public function __construct(
        private readonly WorkOrderService $workOrders,
        private readonly WorkOrderSnapshotService $snapshots,
    ) {}

    /**
     * Note that a template was edited. The refresh is queued after the current
     * transaction commits (at once when there is none); a rolled-back edit queues
     * nothing.
     */
    public function markDirty(?int $templateId): void
    {
        if (! $templateId) {
            return;
        }

        $userId = auth()->id();
        DB::afterCommit(fn () => RefreshNotStartedOrders::dispatch($templateId, $userId));
    }

    /**
     * Move every not-started order built on this template (as its primary
     * process) onto the template as it is now.
     *
     * @return int Number of orders moved.
     */
    public function refreshNotStarted(ProcessTemplate $template, ?User $user = null): int
    {
        $moved = 0;

        foreach ($this->candidates($template)->pluck('id') as $id) {
            $moved += DB::transaction(function () use ($id, $template, $user) {
                // Same lock createBatch() and change requests take, so no batch is
                // created from — and no change applied over — a half-written snapshot.
                $workOrder = WorkOrder::whereKey($id)->lockForUpdate()->first();
                if (! $workOrder || ! in_array($workOrder->status, self::NOT_STARTED_STATUSES, true)) {
                    return 0;
                }

                // Lock the batches and their steps too, then decide: a step started
                // concurrently either committed first (seen here, order skipped) or
                // waits until this refresh is done.
                $batches = Batch::where('work_order_id', $workOrder->id)->lockForUpdate()->get();
                BatchStep::whereIn('batch_id', $batches->modelKeys())->lockForUpdate()->get();
                if (! $this->isNotStarted($workOrder, $batches)) {
                    return 0;
                }

                $current = $template->fresh();
                if (! $current) {
                    return 0; // template deleted meanwhile: nothing to move onto
                }

                $previous = $workOrder->process_snapshot ?? [];
                $snapshot = $this->rebuild($workOrder, $previous, $current);
                if ($snapshot === null || $snapshot == $previous) {
                    return 0;
                }

                $version = $this->snapshots->createVersion(
                    $workOrder,
                    $snapshot,
                    ChangeEffectivePoint::Immediate,
                    user: $user,
                    reason: WorkOrderSnapshot::REASON_TEMPLATE_EDITED,
                );

                foreach ($batches->where('status', Batch::STATUS_PENDING) as $batch) {
                    $this->regenerate($batch, $previous, $snapshot, $version->version);
                }

                return 1;
            });
        }

        return $moved;
    }

    /**
     * How the template's open orders split for the admin warning: by its own
     * process (in progress / not started) and orders that use it only as an
     * additional BOM (never updated from here).
     *
     * @return array{in_progress: int, not_started: int, secondary_bom: int}
     */
    public function orderCounts(ProcessTemplate $template): array
    {
        $orders = $this->openOrders($template)->with('batches:id,work_order_id,status,produced_qty')->get();
        $executed = $this->executedBatchIds($orders->flatMap->batches->pluck('id')->all());
        $allocated = $this->allocatedOrderIds($orders->modelKeys());

        $counts = ['in_progress' => 0, 'not_started' => 0, 'secondary_bom' => 0];
        foreach ($orders as $workOrder) {
            $counts[$this->notStarted($workOrder, $workOrder->batches, $executed, $allocated) ? 'not_started' : 'in_progress']++;
        }

        $counts['secondary_bom'] = WorkOrder::query()
            ->whereNotIn('status', WorkOrder::TERMINAL_STATUSES)
            ->whereHas('bomTemplates', fn ($q) => $q->where('process_templates.id', $template->id))
            ->whereNotIn('id', $orders->modelKeys())
            ->count();

        return $counts;
    }

    /**
     * Whether the order may still be moved onto an edited template.
     *
     * @param  iterable<Batch>|null  $batches  Already-loaded (locked) batches, when the caller has them.
     */
    public function isNotStarted(WorkOrder $workOrder, ?iterable $batches = null): bool
    {
        $batches = collect($batches ?? $workOrder->batches);

        return $this->notStarted(
            $workOrder,
            $batches,
            $this->executedBatchIds($batches->pluck('id')->all()),
            $this->allocatedOrderIds([$workOrder->id]),
        );
    }

    /**
     * @param  array<int, true>  $executed  batch ids with execution
     * @param  array<int, true>  $allocated  order ids with a material allocation
     */
    private function notStarted(WorkOrder $workOrder, iterable $batches, array $executed, array $allocated): bool
    {
        if (! in_array($workOrder->status, self::NOT_STARTED_STATUSES, true)
            || (float) $workOrder->produced_qty > 0
            || isset($allocated[$workOrder->id])) {
            return false;
        }

        foreach ($batches as $batch) {
            if ($batch->status === Batch::STATUS_CANCELLED) {
                continue;
            }
            if ($batch->status !== Batch::STATUS_PENDING || (float) $batch->produced_qty > 0 || isset($executed[$batch->id])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Batches with an executed step, in one query (same rule as Batch::hasExecution()).
     *
     * @param  array<int, int>  $batchIds
     * @return array<int, true>
     */
    private function executedBatchIds(array $batchIds): array
    {
        if ($batchIds === []) {
            return [];
        }

        return BatchStep::whereIn('batch_id', $batchIds)
            ->where(fn ($q) => $q
                ->whereIn('status', [BatchStep::STATUS_IN_PROGRESS, BatchStep::STATUS_DONE])
                ->orWhere(fn ($qq) => $qq->where('status', BatchStep::STATUS_SKIPPED)->whereNotNull('completed_by_id')))
            ->distinct()
            ->pluck('batch_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }

    /**
     * Orders with any material allocation (reserved, consumed or returned), on the
     * order or on one of its batches: their BOM is in use, so it is not rebuilt.
     *
     * @param  array<int, int>  $orderIds
     * @return array<int, true>
     */
    private function allocatedOrderIds(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        // Unscoped: a guard against touching an order whose BOM is in use must not
        // miss an allocation because of the tenant context the job runs in.
        $allocations = fn () => MaterialAllocation::withoutGlobalScope(TenantScope::class);

        $direct = $allocations()->whereIn('work_order_id', $orderIds)->distinct()->pluck('work_order_id');
        $viaBatch = Batch::withoutGlobalScopes()->whereIn('work_order_id', $orderIds)
            ->whereIn('id', $allocations()->select('batch_id'))
            ->distinct()
            ->pluck('work_order_id');

        return $direct->merge($viaBatch)
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }

    /** Non-terminal orders whose process (primary template) is this template. */
    private function openOrders(ProcessTemplate $template): Builder
    {
        return WorkOrder::query()
            ->whereNotIn('status', WorkOrder::TERMINAL_STATUSES)
            ->where('process_snapshot->template_id', $template->id);
    }

    private function candidates(ProcessTemplate $template): Builder
    {
        return $this->openOrders($template)->whereIn('status', self::NOT_STARTED_STATUSES);
    }

    /**
     * The snapshot rebuilt from the order's BOM selection as it is now — steps from
     * this (primary) template, BOM merged from every selected template, exactly as
     * at order creation. The revision and engineering documents stay as released.
     *
     * @param  array<string, mixed>  $current
     * @return array<string, mixed>|null Null when there is nothing to refresh.
     */
    private function rebuild(WorkOrder $workOrder, array $current, ProcessTemplate $template): ?array
    {
        // An order released without any process steps has no routing to refresh;
        // giving it one now would be a different order, not an edited one.
        if (empty($current['steps'])) {
            return null;
        }

        $selected = $current['bom_template_ids'] ?? $workOrder->bomTemplates()->pluck('process_templates.id')->all();
        $templateIds = array_values(array_unique(array_map('intval', [$template->id, ...$selected])));

        try {
            $fresh = $this->workOrders->buildProcessSnapshot($workOrder->product_type_id, $templateIds);
        } catch (\InvalidArgumentException $e) {
            // A selected BOM was deleted or moved to another product: the order
            // cannot be rebuilt the way it was released, so it stays as it is.
            Log::warning('Process template edited: a not-started order was left as released because its BOM selection can no longer be built', [
                'work_order_id' => $workOrder->id,
                'process_template_id' => $template->id,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }

        if (empty($fresh['steps'])) {
            return null; // every step removed: keep the order runnable as released
        }

        return array_merge($current, $fresh);
    }

    /**
     * Replace a pending batch's steps with the new snapshot's, keeping what was
     * planned on a step that is still in the process — its manual workstation
     * assignment, its scheduled operations and its step documents — and dropping
     * (with a log line) what was planned on a step that is gone.
     *
     * Steps are matched by the template step they were frozen from, so a reordered
     * step keeps its planning; by step number for snapshots without that id.
     *
     * @param  array<string, mixed>  $previous
     * @param  array<string, mixed>  $snapshot
     */
    private function regenerate(Batch $batch, array $previous, array $snapshot, int $version): void
    {
        $oldSteps = $batch->steps()->get();
        $documents = BatchStepDocument::whereIn('batch_step_id', $oldSteps->modelKeys())->get();

        // Per-model delete: BatchStep soft-deletes with audit and cascades to its
        // documents in the model event (as change requests do). The partial unique
        // index on (batch_id, step_number) needs the old rows gone first.
        foreach ($oldSteps as $step) {
            $step->delete();
        }
        $this->workOrders->regenerateBatchSteps($batch, $snapshot);
        $batch->update(['snapshot_version' => $version]);

        $newSteps = $batch->steps()->get()->keyBy('step_number');
        $target = $this->stepMatcher($previous, $snapshot, $newSteps);

        foreach ($oldSteps as $old) {
            $new = $target($old);
            $context = ['work_order_id' => $batch->work_order_id, 'batch_id' => $batch->id, 'batch_step_id' => $old->id, 'step_number' => $old->step_number];

            $operations = ScheduledOperation::where('batch_step_id', $old->id);
            $stepDocuments = $documents->where('batch_step_id', $old->id);

            if (! $new) {
                if ($operations->exists() || $stepDocuments->isNotEmpty() || $old->assigned_by_id) {
                    Log::info('Process template edited: planning of a step that is no longer in the process was dropped', $context);
                }
                $operations->delete();

                continue;
            }

            if ($old->assigned_by_id && $this->workstationFits($old->workstation_id, $new)) {
                $new->update([
                    'workstation_id' => $old->workstation_id,
                    'assigned_by_id' => $old->assigned_by_id,
                    'assigned_at' => $old->assigned_at,
                ]);
            } elseif ($old->assigned_by_id) {
                Log::info('Process template edited: a manual workstation assignment no longer fits its step and was dropped', $context);
            }

            $operations->update(['batch_step_id' => $new->id, 'step_number' => $new->step_number, 'step_name' => $new->name]);

            // The step delete above soft-deleted these with it; bring them back on
            // the new step (query-builder update: no second audit/cascade round).
            if ($stepDocuments->isNotEmpty()) {
                BatchStepDocument::withTrashed()->whereKey($stepDocuments->modelKeys())
                    ->update(['batch_step_id' => $new->id, 'deleted_at' => null, 'deleted_by_id' => null]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $previous
     * @param  array<string, mixed>  $snapshot
     * @param  \Illuminate\Support\Collection<int, BatchStep>  $newSteps  keyed by step_number
     * @return \Closure(BatchStep): ?BatchStep
     */
    private function stepMatcher(array $previous, array $snapshot, $newSteps): \Closure
    {
        $oldTemplateStep = collect($previous['steps'] ?? [])->pluck('template_step_id', 'step_number');
        $newNumber = collect($snapshot['steps'] ?? [])->filter(fn ($s) => isset($s['template_step_id']))
            ->pluck('step_number', 'template_step_id');

        return function (BatchStep $old) use ($oldTemplateStep, $newNumber, $newSteps): ?BatchStep {
            $templateStepId = $oldTemplateStep[$old->step_number] ?? null;
            if ($templateStepId !== null) {
                $number = $newNumber[$templateStepId] ?? null;

                return $number === null ? null : $newSteps->get($number);
            }

            return $newSteps->get($old->step_number);
        };
    }

    private function workstationFits(?int $workstationId, BatchStep $step): bool
    {
        if (! $workstationId) {
            return false;
        }
        if (! $step->workstation_type_id) {
            return true;
        }

        return (int) Workstation::whereKey($workstationId)->value('workstation_type_id') === (int) $step->workstation_type_id;
    }
}
