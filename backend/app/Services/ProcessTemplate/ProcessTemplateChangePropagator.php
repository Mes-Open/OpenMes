<?php

namespace App\Services\ProcessTemplate;

use App\Enums\ChangeEffectivePoint;
use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\ProcessTemplate;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderSnapshot;
use App\Services\WorkOrder\WorkOrderService;
use App\Services\WorkOrder\WorkOrderSnapshotService;
use Illuminate\Support\Facades\DB;

/**
 * Carries an edit of a process template (steps, checklist items, outputs, media)
 * to the work orders that have not started yet.
 *
 * - An order IN PROGRESS keeps its process snapshot untouched: what the shop floor
 *   was told to build does not change under it.
 * - An order NOT STARTED yet is moved onto the edited template: its snapshot is
 *   rebuilt from its own template selection and appended as the next #182 version
 *   (reason `process_template_edited`), and the steps of its batches are
 *   regenerated from it.
 * - New orders snapshot the template as it is, so they need nothing from here.
 *
 * "Not started" = status PENDING or ACCEPTED, nothing produced, and no batch with
 * any execution (a batch past PENDING, or a step started, done, or skipped by
 * somebody). Anything else — including a PENDING order whose first step was
 * started before its status caught up — counts as in progress.
 *
 * Edits are collected per request (model events on the template rows call
 * markDirty()) and applied once when the request / command terminates, so a step
 * delete that cascades to its checklist and outputs and then renumbers the rest
 * produces one version, built from the final shape.
 */
class ProcessTemplateChangePropagator
{
    /** Order statuses in which an order may still be moved onto an edited template. */
    public const NOT_STARTED_STATUSES = [WorkOrder::STATUS_PENDING, WorkOrder::STATUS_ACCEPTED];

    /** Snapshot keys rebuilt from the template; every other key (revision, documents, …) is kept. */
    private const REBUILT_KEYS = ['template_id', 'template_name', 'template_version', 'steps', 'bom', 'bom_template_ids', 'bom_templates'];

    /** @var array<int, true> */
    private array $dirty = [];

    private bool $registered = false;

    private bool $flushing = false;

    public function __construct(
        private readonly WorkOrderService $workOrders,
        private readonly WorkOrderSnapshotService $snapshots,
    ) {}

    /**
     * Drop edits noted before the current request began. A request applies only
     * its own edits; anything written outside a request lifecycle (seeders, test
     * set-up) is applied by an explicit flush() or not at all.
     */
    public function forgetPending(): void
    {
        $this->dirty = [];
    }

    /** Note that a template was edited; applied when the request terminates (or on flush()). */
    public function markDirty(?int $templateId): void
    {
        if (! $templateId || $this->flushing) {
            return;
        }

        $this->dirty[$templateId] = true;

        if (! $this->registered) {
            $this->registered = true;
            app()->terminating(fn () => $this->flush());
        }
    }

    /**
     * Apply every pending template edit now.
     *
     * @return int Number of orders moved onto an edited template.
     */
    public function flush(): int
    {
        $ids = array_keys($this->dirty);
        $this->dirty = [];
        $moved = 0;

        $this->flushing = true;
        try {
            foreach ($ids as $id) {
                $template = ProcessTemplate::find($id);
                if (! $template) {
                    continue;
                }
                try {
                    $moved += $this->refreshNotStarted($template);
                } catch (\Throwable $e) {
                    // One template failing must not keep the others from refreshing,
                    // nor fail a request whose own write already succeeded.
                    report($e);
                }
            }
        } finally {
            $this->flushing = false;
        }

        return $moved;
    }

    /**
     * Move every not-started order built on this template (as its primary
     * process) onto the template as it is now.
     *
     * @return int Number of orders moved.
     */
    public function refreshNotStarted(ProcessTemplate $template): int
    {
        $user = auth()->user();
        $moved = 0;

        foreach ($this->candidates($template)->pluck('id') as $id) {
            $moved += DB::transaction(function () use ($id, $user) {
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

                $snapshot = $this->rebuild($workOrder);
                if ($snapshot === null || $snapshot == ($workOrder->process_snapshot ?? [])) {
                    return 0;
                }

                $version = $this->snapshots->createVersion(
                    $workOrder,
                    $snapshot,
                    ChangeEffectivePoint::Immediate,
                    user: $user instanceof User ? $user : null,
                    reason: WorkOrderSnapshot::REASON_TEMPLATE_EDITED,
                );

                foreach ($batches->where('status', Batch::STATUS_PENDING) as $batch) {
                    // Per-model delete: BatchStep soft-deletes with audit and cascades
                    // to its documents in the model event (as change requests do).
                    foreach ($batch->steps as $step) {
                        $step->delete();
                    }
                    $this->workOrders->regenerateBatchSteps($batch, $snapshot);
                    $batch->update(['snapshot_version' => $version->version]);
                }

                return 1;
            });
        }

        return $moved;
    }

    /**
     * How the template's open orders split for the admin warning.
     *
     * @return array{in_progress: int, not_started: int}
     */
    public function orderCounts(ProcessTemplate $template): array
    {
        $counts = ['in_progress' => 0, 'not_started' => 0];

        foreach ($this->openOrders($template)->with('batches')->get() as $workOrder) {
            $counts[$this->isNotStarted($workOrder) ? 'not_started' : 'in_progress']++;
        }

        return $counts;
    }

    /**
     * Whether the order may still be moved onto an edited template.
     *
     * @param  iterable<Batch>|null  $batches  Already-loaded (locked) batches, when the caller has them.
     */
    public function isNotStarted(WorkOrder $workOrder, ?iterable $batches = null): bool
    {
        if (! in_array($workOrder->status, self::NOT_STARTED_STATUSES, true)
            || (float) $workOrder->produced_qty > 0) {
            return false;
        }

        foreach ($batches ?? $workOrder->batches as $batch) {
            if ($batch->status === Batch::STATUS_CANCELLED) {
                continue;
            }
            if ($batch->status !== Batch::STATUS_PENDING || $batch->hasExecution()) {
                return false;
            }
        }

        return true;
    }

    /** Non-terminal orders whose process (primary template) is this template. */
    private function openOrders(ProcessTemplate $template)
    {
        return WorkOrder::query()
            ->whereNotIn('status', WorkOrder::TERMINAL_STATUSES)
            ->where('process_snapshot->template_id', $template->id);
    }

    private function candidates(ProcessTemplate $template)
    {
        return $this->openOrders($template)->whereIn('status', self::NOT_STARTED_STATUSES);
    }

    /**
     * The order's snapshot rebuilt from its own template selection (primary first,
     * then the other selected BOMs), keeping what was frozen at release that is not
     * template structure — the revision block, engineering documents and anything
     * else attached to the snapshot.
     *
     * @return array<string, mixed>|null Null when the selection no longer resolves.
     */
    private function rebuild(WorkOrder $workOrder): ?array
    {
        $current = $workOrder->process_snapshot ?? [];
        // An order released without any process steps has no routing to refresh;
        // giving it one now would be a different order, not an edited one.
        if (empty($current['steps'])) {
            return null;
        }

        $templateIds = $workOrder->bomTemplates()->pluck('process_templates.id')->all();
        if ($templateIds === []) {
            $templateIds = [$current['template_id']];
        }

        try {
            $built = $this->workOrders->buildProcessSnapshot($workOrder->product_type_id, $templateIds);
        } catch (\InvalidArgumentException) {
            // A selected template was deleted or moved to another product: keep
            // the order as it is rather than guess a different process.
            return null;
        }
        if ($built === null || empty($built['steps'])) {
            return null;
        }

        return array_merge(array_diff_key($current, array_flip(self::REBUILT_KEYS)), $built);
    }
}
