<?php

namespace App\Services\Production;

use App\Models\BatchStep;
use App\Models\BatchStepTimeEntry;
use App\Models\ReworkOperation;
use App\Models\User;
use App\Models\Workstation;
use App\Models\WorkstationState;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Independent operator (labor) and machine time per batch step (#400, item 3).
 *
 * The rules that keep the two from being double-counted:
 *  - an operator has at most one open labor entry: clocking onto another step
 *    closes the open one at the same instant (switching jobs);
 *  - a workstation has at most one open machine entry: it cannot run for two
 *    steps at once;
 *  - an entry entered afterwards may not overlap another entry of the same
 *    operator (labor) or the same workstation (machine);
 *  - the step totals merge each operator's and each workstation's intervals
 *    before summing, so time is counted once even if overlapping rows exist.
 *
 * Labor and machine time never constrain each other, which is what lets a
 * machine run unattended while its operator works elsewhere. The step's own
 * start / completion and its confirmed elapsed, setup and run minutes are not
 * touched.
 *
 * Rework (#400, item 2): time spent reworking pieces is recorded on the step
 * being reworked but tagged with the rework operation. It counts towards the
 * rework's totals, never towards the step's own, so the original operation's
 * history stays as it was. The one-open-entry rules above still apply across
 * both, so an operator is never on a step and a rework at the same time.
 */
class StepTimeService
{
    /**
     * Clock an operator onto a running step. Idempotent for the same step; an
     * open entry on another step is closed at the same instant.
     *
     * @throws \DomainException
     */
    public function startLabor(BatchStep $step, User $operator, ?User $by = null, ?Carbon $at = null, ?ReworkOperation $rework = null): BatchStepTimeEntry
    {
        $at ??= now();
        $this->assertRunning($step, $rework);

        return $this->guardUnique(fn () => DB::transaction(function () use ($step, $operator, $by, $at, $rework) {
            $this->lockRunningRework($step, $rework);
            $open = BatchStepTimeEntry::open()
                ->where('kind', BatchStepTimeEntry::KIND_LABOR)
                ->where('user_id', $operator->id)
                ->lockForUpdate()
                ->first();

            if ($open && (int) $open->batch_step_id === (int) $step->id
                && (int) $open->rework_operation_id === (int) $rework?->id) {
                return $open;
            }

            if ($open) {
                $this->close($open, $at, $by ?? $operator);
            }

            $entry = BatchStepTimeEntry::create([
                'batch_step_id' => $step->id,
                'rework_operation_id' => $rework?->id,
                'kind' => BatchStepTimeEntry::KIND_LABOR,
                'user_id' => $operator->id,
                'started_at' => $at,
                'source' => BatchStepTimeEntry::SOURCE_OPERATOR,
                'created_by_id' => ($by ?? $operator)->id,
            ]);
            $this->recalculateFor($entry);

            return $entry;
        }), __('You are already clocked onto another step.'));
    }

    /**
     * Clock an operator off a step.
     *
     * @throws \DomainException when the operator is not clocked onto this step
     */
    public function stopLabor(BatchStep $step, User $operator, ?User $by = null, ?Carbon $at = null, ?ReworkOperation $rework = null): BatchStepTimeEntry
    {
        return DB::transaction(function () use ($step, $operator, $by, $at, $rework) {
            $open = $this->context($step->timeEntries(), $rework)->open()
                ->where('kind', BatchStepTimeEntry::KIND_LABOR)
                ->where('user_id', $operator->id)
                ->lockForUpdate()
                ->first();

            if (! $open) {
                throw new \DomainException(__('You are not clocked onto this step.'));
            }

            $this->close($open, $at ?? now(), $by ?? $operator);

            return $open;
        });
    }

    /**
     * Start machine time on a running step, on the given workstation or the
     * step's own. Idempotent for the same step.
     *
     * @throws \DomainException
     */
    public function startMachine(BatchStep $step, ?Workstation $workstation, User $by, ?Carbon $at = null, ?ReworkOperation $rework = null): BatchStepTimeEntry
    {
        $this->assertRunning($step, $rework);
        $workstation ??= $step->workstation;
        if (! $workstation) {
            throw new \DomainException(__('This step has no workstation. Choose the machine that runs it.'));
        }

        return $this->openMachine($step, $workstation, BatchStepTimeEntry::SOURCE_OPERATOR, $at ?? now(), $by, $rework);
    }

    /**
     * Stop the machine time running on this step (on one workstation, or all).
     *
     * @throws \DomainException when no machine time is running on the step
     */
    public function stopMachine(BatchStep $step, ?Workstation $workstation, User $by, ?Carbon $at = null, ?ReworkOperation $rework = null): Collection
    {
        return DB::transaction(function () use ($step, $workstation, $by, $at, $rework) {
            $open = $this->context($step->timeEntries(), $rework)->open()
                ->where('kind', BatchStepTimeEntry::KIND_MACHINE)
                ->when($workstation, fn ($q) => $q->where('workstation_id', $workstation->id))
                ->lockForUpdate()
                ->get();

            if ($open->isEmpty()) {
                throw new \DomainException(__('No machine time is running on this step.'));
            }

            $at ??= now();
            $open->each(fn (BatchStepTimeEntry $e) => $this->close($e, $at, $by));

            return $open;
        });
    }

    /**
     * A closed interval entered afterwards by a supervisor (a forgotten clock-on,
     * a machine without connectivity). Rejected when it overlaps time already
     * recorded for the same operator or workstation.
     *
     * @param  array{kind: string, user_id?: int|null, workstation_id?: int|null, started_at: mixed, ended_at: mixed, note?: string|null}  $data
     *
     * @throws \DomainException
     */
    public function addManual(BatchStep $step, array $data, User $by): BatchStepTimeEntry
    {
        $start = Carbon::parse($data['started_at']);
        $end = Carbon::parse($data['ended_at']);
        $labor = $data['kind'] === BatchStepTimeEntry::KIND_LABOR;

        return DB::transaction(function () use ($step, $data, $by, $start, $end, $labor) {
            $ownerColumn = $labor ? 'user_id' : 'workstation_id';
            $ownerId = (int) $data[$ownerColumn];

            $clash = BatchStepTimeEntry::query()
                ->where('kind', $data['kind'])
                ->where($ownerColumn, $ownerId)
                ->where('started_at', '<', $end)
                ->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>', $start))
                ->lockForUpdate()
                ->exists();

            if ($clash) {
                throw new \DomainException($labor
                    ? __('This operator already has time recorded in that period.')
                    : __('This machine already has time recorded in that period.'));
            }

            $entry = BatchStepTimeEntry::create([
                'batch_step_id' => $step->id,
                'kind' => $data['kind'],
                'user_id' => $labor ? $ownerId : null,
                'workstation_id' => $labor ? null : $ownerId,
                'started_at' => $start,
                'ended_at' => $end,
                'source' => BatchStepTimeEntry::SOURCE_MANUAL,
                'note' => $data['note'] ?? null,
                'created_by_id' => $by->id,
                'ended_by_id' => $by->id,
            ]);
            $this->recalculateFor($entry);

            return $entry;
        });
    }

    /** Remove an entry (soft delete, audited) and refresh its step's totals. */
    public function delete(BatchStepTimeEntry $entry): void
    {
        DB::transaction(function () use ($entry) {
            $entry->delete();
            $this->recalculateFor($entry);
        });
    }

    /** Close the step's own open time — it was completed at $at. Rework time is the rework's. */
    public function closeOpenForStep(BatchStep $step, ?User $by = null, ?Carbon $at = null): void
    {
        $at ??= now();
        $step->timeEntries()->open()->whereNull('rework_operation_id')->lockForUpdate()->get()
            ->each(fn (BatchStepTimeEntry $e) => $this->close($e, $at, $by));
    }

    /** Close the rework's open time — it was completed or cancelled. */
    public function closeOpenForRework(ReworkOperation $rework, ?User $by = null, ?Carbon $at = null): void
    {
        $at ??= now();
        $rework->timeEntries()->open()->lockForUpdate()->get()
            ->each(fn (BatchStepTimeEntry $e) => $this->close($e, $at, $by));
    }

    /**
     * Machine time from connectivity: a workstation entering RUNNING opens a
     * machine entry on the one step in progress there; leaving RUNNING closes
     * the entry the signal opened. Time an operator started by hand is left to
     * the operator. Does nothing when the station has no single running step.
     */
    public function onWorkstationStateChanged(Workstation $workstation, ?string $from, string $to, Carbon $at): void
    {
        try {
            if ($from === WorkstationState::RUNNING && $to !== WorkstationState::RUNNING) {
                BatchStepTimeEntry::open()
                    ->where('kind', BatchStepTimeEntry::KIND_MACHINE)
                    ->where('workstation_id', $workstation->id)
                    ->where('source', BatchStepTimeEntry::SOURCE_MACHINE)
                    ->get()
                    ->each(fn (BatchStepTimeEntry $e) => $this->close($e, $at, null));

                return;
            }

            if ($to !== WorkstationState::RUNNING || $from === WorkstationState::RUNNING) {
                return;
            }

            $steps = BatchStep::where('workstation_id', $workstation->id)
                ->where('status', BatchStep::STATUS_IN_PROGRESS)
                ->limit(2)
                ->get();
            if ($steps->count() !== 1) {
                return;
            }

            $this->openMachine($steps->first(), $workstation, BatchStepTimeEntry::SOURCE_MACHINE, $at, null);
        } catch (\Throwable $e) {
            // Time tracking must never break signal ingestion.
            Log::warning('Machine time entry not recorded from a state change', [
                'workstation_id' => $workstation->id,
                'from' => $from,
                'to' => $to,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Write the de-duplicated totals onto the step: each operator's (labor) and
     * each workstation's (machine) intervals merged, then summed. Open entries
     * are not counted until they close.
     */
    public function recalculate(BatchStep $step): void
    {
        $entries = $step->timeEntries()->whereNull('rework_operation_id')->whereNotNull('ended_at')->get();

        $step->forceFill(self::closedTotals($entries))->save();
    }

    /** The same totals for a rework, over the time tagged with it. */
    public function recalculateRework(ReworkOperation $rework): void
    {
        $entries = $rework->timeEntries()->whereNotNull('ended_at')->get();

        $rework->forceFill(self::closedTotals($entries))->save();
    }

    /**
     * @param  Collection<int, BatchStepTimeEntry>  $entries
     * @return array{actual_labor_minutes: int|null, actual_machine_minutes: int|null}
     */
    private static function closedTotals(Collection $entries): array
    {
        $minutes = function (string $kind, string $owner) use ($entries): ?int {
            $ofKind = $entries->where('kind', $kind);
            if ($ofKind->isEmpty()) {
                return null;
            }
            $seconds = $ofKind
                ->groupBy(fn (BatchStepTimeEntry $e) => $e->{$owner} ?? 'entry:'.$e->id)
                ->sum(fn (Collection $group) => self::mergedSeconds($group));

            return (int) round($seconds / 60);
        };

        return [
            'actual_labor_minutes' => $minutes(BatchStepTimeEntry::KIND_LABOR, 'user_id'),
            'actual_machine_minutes' => $minutes(BatchStepTimeEntry::KIND_MACHINE, 'workstation_id'),
        ];
    }

    /** Refresh the totals an entry counts towards: its rework's, else its step's. */
    private function recalculateFor(BatchStepTimeEntry $entry): void
    {
        if ($entry->rework_operation_id) {
            $rework = ReworkOperation::withTrashed()->find($entry->rework_operation_id);
            if ($rework) {
                $this->recalculateRework($rework);
            }

            return;
        }

        $this->recalculate($entry->batchStep);
    }

    /**
     * Seconds covered by a set of intervals, overlaps counted once.
     *
     * @param  Collection<int, BatchStepTimeEntry>  $entries
     */
    public static function mergedSeconds(Collection $entries, ?Carbon $until = null): int
    {
        $until ??= now();
        $intervals = $entries
            ->map(fn (BatchStepTimeEntry $e) => [$e->started_at->getTimestamp(), ($e->ended_at ?? $until)->getTimestamp()])
            ->filter(fn (array $i) => $i[1] > $i[0])
            ->sortBy(fn (array $i) => $i[0])
            ->values();

        $total = 0;
        $current = null;
        foreach ($intervals as [$start, $end]) {
            if ($current === null || $start > $current[1]) {
                if ($current !== null) {
                    $total += $current[1] - $current[0];
                }
                $current = [$start, $end];
            } else {
                $current[1] = max($current[1], $end);
            }
        }
        if ($current !== null) {
            $total += $current[1] - $current[0];
        }

        return $total;
    }

    /**
     * Totals for display, open entries counted up to now.
     *
     * @return array{labor_minutes: int, machine_minutes: int}
     */
    public function liveTotals(BatchStep $step): array
    {
        $entries = ($step->relationLoaded('timeEntries') ? $step->timeEntries : $step->timeEntries()->get())
            ->whereNull('rework_operation_id');

        return self::liveTotalsOf($entries);
    }

    /**
     * Totals for display of a rework, open entries counted up to now.
     *
     * @return array{labor_minutes: int, machine_minutes: int}
     */
    public function liveReworkTotals(ReworkOperation $rework): array
    {
        return self::liveTotalsOf($rework->relationLoaded('timeEntries') ? $rework->timeEntries : $rework->timeEntries()->get());
    }

    /**
     * @param  Collection<int, BatchStepTimeEntry>  $entries
     * @return array{labor_minutes: int, machine_minutes: int}
     */
    private static function liveTotalsOf(Collection $entries): array
    {
        $sum = fn (string $kind, string $owner) => (int) round($entries->where('kind', $kind)
            ->groupBy(fn (BatchStepTimeEntry $e) => $e->{$owner} ?? 'entry:'.$e->id)
            ->sum(fn (Collection $group) => self::mergedSeconds($group)) / 60);

        return [
            'labor_minutes' => $sum(BatchStepTimeEntry::KIND_LABOR, 'user_id'),
            'machine_minutes' => $sum(BatchStepTimeEntry::KIND_MACHINE, 'workstation_id'),
        ];
    }

    /**
     * One operation's times for costing and reporting: the standard, the
     * operator-confirmed split and the recorded labor / machine minutes.
     *
     * @return array<string, mixed>
     */
    public static function operation(BatchStep $step, ?\App\Models\Batch $batch = null): array
    {
        $batch ??= $step->batch;

        return [
            'batch_id' => $batch?->id,
            'batch_number' => $batch?->batch_number,
            'batch_step_id' => $step->id,
            'step_number' => $step->step_number,
            'name' => $step->name,
            'status' => $step->status,
            'workstation' => $step->workstation ? ['id' => $step->workstation->id, 'code' => $step->workstation->code, 'name' => $step->workstation->name] : null,
            'started_at' => $step->started_at?->toIso8601String(),
            'completed_at' => $step->completed_at?->toIso8601String(),
            'standard_setup_minutes' => $step->setup_time_minutes,
            'standard_run_minutes_per_unit' => $step->run_time_per_unit_minutes !== null ? (float) $step->run_time_per_unit_minutes : null,
            'duration_minutes' => $step->duration_minutes,
            'actual_elapsed_minutes' => $step->actual_elapsed_minutes,
            'actual_setup_minutes' => $step->actual_setup_minutes,
            'actual_run_minutes' => $step->actual_run_minutes,
            'actual_labor_minutes' => $step->actual_labor_minutes,
            'actual_machine_minutes' => $step->actual_machine_minutes,
        ];
    }

    private function openMachine(BatchStep $step, Workstation $workstation, string $source, Carbon $at, ?User $by, ?ReworkOperation $rework = null): BatchStepTimeEntry
    {
        return $this->guardUnique(fn () => DB::transaction(function () use ($step, $workstation, $source, $at, $by, $rework) {
            $this->lockRunningRework($step, $rework);
            $open = BatchStepTimeEntry::open()
                ->where('kind', BatchStepTimeEntry::KIND_MACHINE)
                ->where('workstation_id', $workstation->id)
                ->lockForUpdate()
                ->first();

            if ($open && (int) $open->batch_step_id === (int) $step->id
                && (int) $open->rework_operation_id === (int) $rework?->id) {
                return $open;
            }
            if ($open) {
                throw new \DomainException(__('Machine :machine is already running on another step.', ['machine' => $workstation->name]));
            }

            $entry = BatchStepTimeEntry::create([
                'batch_step_id' => $step->id,
                'rework_operation_id' => $rework?->id,
                'kind' => BatchStepTimeEntry::KIND_MACHINE,
                'workstation_id' => $workstation->id,
                'started_at' => $at,
                'source' => $source,
                'created_by_id' => $by?->id,
            ]);
            $this->recalculateFor($entry);

            return $entry;
        }), __('Machine :machine is already running on another step.', ['machine' => $workstation->name]));
    }

    private function close(BatchStepTimeEntry $entry, Carbon $at, ?User $by): void
    {
        // Never end before the start (a clock skew between terminals).
        $entry->update([
            'ended_at' => $at->lessThan($entry->started_at) ? $entry->started_at : $at,
            'ended_by_id' => $by?->id,
        ]);
        $this->recalculateFor($entry);
    }

    /**
     * Step time needs the step in progress; rework time needs the rework in
     * progress, on the step it reworks (which is usually finished already).
     *
     * @throws \DomainException
     */
    private function assertRunning(BatchStep $step, ?ReworkOperation $rework = null): void
    {
        if ($rework) {
            if ($rework->status !== ReworkOperation::STATUS_IN_PROGRESS || (int) $rework->target_batch_step_id !== (int) $step->id) {
                throw new \DomainException(__('Time can only be recorded on a rework in progress.'));
            }

            return;
        }

        if ($step->status !== BatchStep::STATUS_IN_PROGRESS) {
            throw new \DomainException(__('Time can only be recorded on a step in progress.'));
        }
    }

    /**
     * Re-check the rework under a row lock (taken before any time entry, the
     * same order ReworkService uses), so it can't be completed or cancelled
     * while time opens on it — that would leave an entry open on a finished
     * rework, holding its operator or machine.
     *
     * @throws \DomainException
     */
    private function lockRunningRework(BatchStep $step, ?ReworkOperation $rework): void
    {
        if ($rework) {
            $this->assertRunning($step, ReworkOperation::whereKey($rework->getKey())->lockForUpdate()->firstOrFail());
        }
    }

    /**
     * Entries of one context: the step's own, or one rework's.
     *
     * @template TRelation of \Illuminate\Database\Eloquent\Relations\Relation|\Illuminate\Database\Eloquent\Builder
     *
     * @param  TRelation  $query
     * @return TRelation
     */
    private function context($query, ?ReworkOperation $rework)
    {
        return $rework
            ? $query->where('rework_operation_id', $rework->id)
            : $query->whereNull('rework_operation_id');
    }

    /**
     * Two terminals racing to open time for the same operator or machine: the
     * partial unique index lets one through; the other gets a readable error.
     *
     * @template T
     *
     * @param  callable(): T  $fn
     * @return T
     */
    private function guardUnique(callable $fn, string $message): mixed
    {
        try {
            return $fn();
        } catch (UniqueConstraintViolationException) {
            throw new \DomainException($message);
        }
    }
}
