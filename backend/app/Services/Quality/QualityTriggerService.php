<?php

namespace App\Services\Quality;

use App\Enums\DowntimeKind;
use App\Models\Batch;
use App\Models\Issue;
use App\Models\IssueType;
use App\Models\Pallet;
use App\Models\ProductionDowntime;
use App\Models\QualityCheck;
use App\Models\QualityControlTask;
use App\Models\QualityControlTrigger;
use App\Models\User;
use App\Services\IssueService;
use App\Services\Production\QualityCheckService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Evaluates configurable quality-control triggers (#105) against production
 * events and creates QualityControlTasks (the "a control is due" prompt). Also
 * records the result of a task (reusing QualityCheckService) and raises a
 * non-conformance Issue when a control fails.
 *
 * Firing is invoked synchronously from the production lifecycle:
 *   - in_production / every_n_units → BatchService
 *   - after_downtime / after_setup → DowntimeService
 *   - every_n_minutes              → FireDueQualityTriggersCommand (scheduler)
 *   - roaming                      → created manually via the controller
 */
class QualityTriggerService
{
    public function __construct(
        private QualityCheckService $qualityCheckService,
        private IssueService $issueService,
    ) {}

    /**
     * Batch just entered production — fire every active in_production trigger
     * whose scope matches, once per batch.
     */
    public function fireInProduction(Batch $batch): void
    {
        $triggers = QualityControlTrigger::active()
            ->ofType(QualityControlTrigger::TYPE_IN_PRODUCTION)
            ->get();

        foreach ($triggers as $trigger) {
            if (! $trigger->matchesBatch($batch)) {
                continue;
            }
            if ($this->batchTaskExists($trigger, $batch)) {
                continue;
            }
            $this->createTaskForBatch($trigger, $batch, __('Batch entered production'));
        }
    }

    /**
     * Batch produced quantity changed — for every active every_n_units trigger,
     * ensure one task exists per N cumulative units produced on the work order.
     */
    public function fireForUnits(Batch $batch): void
    {
        $workOrder = $batch->workOrder;
        if ($workOrder === null) {
            return;
        }

        $triggers = QualityControlTrigger::active()
            ->ofType(QualityControlTrigger::TYPE_EVERY_N_UNITS)
            ->where('threshold_n', '>', 0)
            ->get();

        $produced = (float) $workOrder->produced_qty;

        foreach ($triggers as $trigger) {
            if (! $trigger->matchesBatch($batch)) {
                continue;
            }

            $shouldHave = (int) floor($produced / $trigger->threshold_n);
            $existing = QualityControlTask::where('quality_control_trigger_id', $trigger->id)
                ->where('work_order_id', $workOrder->id)
                ->count();

            // Create one task per N-unit mark still missing. No cap — a later
            // fireForUnits() may never run (e.g. final batch), so the backlog
            // must be cleared now or required controls would be lost.
            $toCreate = max($shouldHave - $existing, 0);
            for ($i = 0; $i < $toCreate; $i++) {
                $unitMark = ($existing + $i + 1) * $trigger->threshold_n;
                $this->createTaskForBatch(
                    $trigger,
                    $batch,
                    __('Every :n units (reached :mark)', ['n' => $trigger->threshold_n, 'mark' => $unitMark]),
                );
            }
        }
    }

    /**
     * A downtime just closed — fire after_setup (changeover) or after_downtime
     * (unplanned loss) triggers for the line, respecting the minimum-minutes
     * threshold. Planned downtime never triggers a control.
     */
    public function fireAfterDowntime(ProductionDowntime $downtime): void
    {
        $downtime->loadMissing('reason');
        $kind = $downtime->reason?->kind;
        $kind = $kind instanceof DowntimeKind ? $kind : DowntimeKind::tryFrom((string) $kind);

        $type = match ($kind) {
            DowntimeKind::Changeover => QualityControlTrigger::TYPE_AFTER_SETUP,
            DowntimeKind::Unplanned => QualityControlTrigger::TYPE_AFTER_DOWNTIME,
            default => null, // Planned (or unknown) → no control
        };

        if ($type === null) {
            return;
        }

        $durationMinutes = (int) ($downtime->duration_minutes ?? 0);

        $triggers = QualityControlTrigger::active()->ofType($type)->get();

        // A control is recorded against the batch that was running on this line.
        // No active batch → nothing to check (and the resulting task couldn't be
        // completed), so we don't fire.
        $activeBatch = $this->activeBatchForLine($downtime->line_id, $downtime->workstation_id);
        if ($activeBatch === null) {
            return;
        }

        foreach ($triggers as $trigger) {
            if ($trigger->line_id !== null && $trigger->line_id !== $downtime->line_id) {
                continue;
            }
            if ($trigger->workstation_id !== null && $trigger->workstation_id !== $downtime->workstation_id) {
                continue;
            }
            if ($trigger->product_type_id !== null
                && $trigger->product_type_id !== $activeBatch->workOrder?->product_type_id) {
                continue;
            }
            if ($trigger->downtime_min_minutes !== null && $durationMinutes < $trigger->downtime_min_minutes) {
                continue;
            }

            $reason = $type === QualityControlTrigger::TYPE_AFTER_SETUP
                ? __('After setup / changeover')
                : __('After downtime (:min min)', ['min' => $durationMinutes]);

            $this->createTask($trigger, [
                'line_id' => $downtime->line_id,
                'workstation_id' => $downtime->workstation_id ?? $activeBatch->workstation_id,
                'batch_id' => $activeBatch->id,
                'work_order_id' => $activeBatch->work_order_id,
            ], $reason);
        }
    }

    /**
     * Scheduler tick — for every active every_n_minutes trigger, fire on each
     * matching in-progress batch whose last task is older than N minutes.
     *
     * @return int number of tasks created
     */
    public function fireDueTimeTriggers(): int
    {
        $triggers = QualityControlTrigger::active()
            ->ofType(QualityControlTrigger::TYPE_EVERY_N_MINUTES)
            ->where('threshold_n', '>', 0)
            ->get();

        if ($triggers->isEmpty()) {
            return 0;
        }

        $created = 0;
        $batches = Batch::where('status', Batch::STATUS_IN_PROGRESS)->with('workOrder')->get();

        foreach ($triggers as $trigger) {
            foreach ($batches as $batch) {
                if (! $trigger->matchesBatch($batch)) {
                    continue;
                }

                $lastFiredAt = QualityControlTask::where('quality_control_trigger_id', $trigger->id)
                    ->where('batch_id', $batch->id)
                    ->max('fired_at');

                $cutoff = Carbon::now()->subMinutes($trigger->threshold_n);
                if ($lastFiredAt !== null && Carbon::parse($lastFiredAt)->greaterThan($cutoff)) {
                    continue;
                }

                $this->createTaskForBatch(
                    $trigger,
                    $batch,
                    __('Every :n minutes', ['n' => $trigger->threshold_n]),
                );
                $created++;
            }
        }

        return $created;
    }

    /**
     * Manually raise a roaming / ad-hoc control task for a roaming trigger.
     * A batch is required so the control can be recorded and gated like any
     * other (a control with nothing to record against would be a dead task).
     */
    public function createRoamingTask(QualityControlTrigger $trigger, array $links = [], ?string $reason = null): QualityControlTask
    {
        if (($links['batch_id'] ?? null) === null) {
            throw new \DomainException('A roaming quality control must target a batch.');
        }

        return $this->createTask($trigger, $links, $reason ?? __('Roaming check'));
    }

    /**
     * Record the result of a control task by performing the underlying
     * QualityCheck against its batch. A failing result raises a non-conformance
     * Issue (blocking when the trigger is blocking).
     */
    public function performTask(QualityControlTask $task, User $user, array $samples, ?float $productionQuantity = null, ?string $notes = null, ?Pallet $pallet = null): QualityControlTask
    {
        if ($task->batch_id === null) {
            throw new \DomainException('This control is not tied to a batch and cannot record a quality check.');
        }

        return DB::transaction(function () use ($task, $user, $samples, $productionQuantity, $notes, $pallet) {
            // Lock the row so two concurrent perform/skip calls can't both pass
            // the open-state check and commit conflicting terminal actions.
            $task = QualityControlTask::whereKey($task->getKey())->lockForUpdate()->firstOrFail();
            if (! $task->isOpen()) {
                throw new \DomainException('This control has already been completed.');
            }
            $task->loadMissing(['batch', 'trigger']);

            $check = $this->qualityCheckService->performCheck(
                $task->batch,
                $user,
                $samples,
                $productionQuantity,
                $task->trigger->template,
                $notes,
                $pallet,
            );

            $issueId = null;
            if (! $check->all_passed) {
                $issueId = $this->raiseFailureIssue($task, $check, $user);
            }

            $task->update([
                'status' => QualityControlTask::STATUS_DONE,
                'quality_check_id' => $check->id,
                'issue_id' => $issueId,
                'completed_at' => now(),
                'completed_by_id' => $user->id,
            ]);

            return $task->fresh(['qualityCheck', 'issue']);
        });
    }

    public function skipTask(QualityControlTask $task, User $user): QualityControlTask
    {
        return DB::transaction(function () use ($task, $user) {
            $task = QualityControlTask::whereKey($task->getKey())->lockForUpdate()->firstOrFail();
            if (! $task->isOpen()) {
                throw new \DomainException('This control has already been completed.');
            }

            $task->update([
                'status' => QualityControlTask::STATUS_SKIPPED,
                'completed_at' => now(),
                'completed_by_id' => $user->id,
            ]);

            return $task->fresh();
        });
    }

    /** Has this batch already got a task for this trigger? (in_production dedupe) */
    private function batchTaskExists(QualityControlTrigger $trigger, Batch $batch): bool
    {
        return QualityControlTask::where('quality_control_trigger_id', $trigger->id)
            ->where('batch_id', $batch->id)
            ->exists();
    }

    private function activeBatchForLine(?int $lineId, ?int $workstationId): ?Batch
    {
        if ($lineId === null) {
            return null;
        }

        return Batch::where('status', Batch::STATUS_IN_PROGRESS)
            ->when($workstationId !== null, fn ($q) => $q->where('workstation_id', $workstationId))
            ->whereHas('workOrder', fn ($q) => $q->where('line_id', $lineId))
            ->with('workOrder')
            ->latest('started_at')
            ->first();
    }

    private function createTaskForBatch(QualityControlTrigger $trigger, Batch $batch, string $reason): QualityControlTask
    {
        return $this->createTask($trigger, [
            'batch_id' => $batch->id,
            'work_order_id' => $batch->work_order_id,
            'workstation_id' => $batch->workstation_id,
            'line_id' => $batch->workOrder?->line_id,
        ], $reason);
    }

    private function createTask(QualityControlTrigger $trigger, array $links, string $reason): QualityControlTask
    {
        return QualityControlTask::create(array_merge([
            'quality_control_trigger_id' => $trigger->id,
            'status' => QualityControlTask::STATUS_DUE,
            'due_reason' => $reason,
            'fired_at' => now(),
        ], $links));
    }

    private function raiseFailureIssue(QualityControlTask $task, QualityCheck $check, User $user): ?int
    {
        $blocking = (bool) $task->trigger->is_blocking;
        $issueTypeId = $this->resolveIssueTypeId($blocking);
        if ($issueTypeId === null) {
            return null;
        }

        $issue = $this->issueService->createIssue([
            'work_order_id' => $task->work_order_id,
            'issue_type_id' => $issueTypeId,
            'source' => Issue::SOURCE_IN_PROCESS,
            'title' => __('In-process QC fail: :reason', ['reason' => $task->due_reason ?? __('quality control')]),
            'description' => __('Quality control ":trigger" failed during production (check #:check).', [
                'trigger' => $task->trigger->name,
                'check' => $check->id,
            ]),
            'reported_by_id' => $user->id,
        ]);

        return $issue->id;
    }

    /**
     * Failing blocking controls raise a blocking non-conformance (stops the work
     * order, like inbound QC); non-blocking controls raise an informational one.
     */
    private function resolveIssueTypeId(bool $blocking): ?int
    {
        $codes = $blocking
            ? ['IN_PROCESS_QC_FAIL', 'QUALITY_ISSUE', 'OTHER']
            : ['QUALITY_ISSUE', 'OTHER'];

        foreach ($codes as $code) {
            $id = IssueType::where('code', $code)->value('id');
            if ($id !== null) {
                return $id;
            }
        }

        return null;
    }
}
