<?php

namespace App\Services\Schedule\Finite;

use App\Enums\ScheduledOperationStatus;
use App\Models\BatchStep;
use App\Models\ScheduledOperation;
use App\Models\ScheduleSimulation;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Services\Schedule\SchedulePlannerService;
use App\Services\WorkOrder\BatchService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The what-if workflow around {@see FiniteScheduler}: generate a draft, review
 * it, then apply or discard it.
 *
 * A draft writes nothing but its own rows. Applying goes through the planner's
 * own update path ({@see SchedulePlannerService::updateOrder()}), so the change
 * log, undo, WorkOrderScheduled events and the work-order audit trail all see
 * an applied schedule exactly as they would a drag on the board. Only one draft
 * is kept: generating a new one discards the previous.
 */
class ScheduleSimulationService
{
    public function __construct(
        private readonly FiniteScheduler $scheduler,
        private readonly SchedulePlannerService $planner,
        private readonly BatchService $batches,
    ) {}

    public function currentDraft(): ?ScheduleSimulation
    {
        return ScheduleSimulation::where('status', ScheduleSimulation::STATUS_DRAFT)->latest('id')->first();
    }

    public function generate(SchedulingRequest $request, User $user): ScheduleSimulation
    {
        $result = $this->scheduler->schedule($request);

        return DB::transaction(function () use ($request, $user, $result) {
            ScheduleSimulation::where('status', ScheduleSimulation::STATUS_DRAFT)
                ->get()
                ->each(fn (ScheduleSimulation $draft) => $this->discard($draft));

            $simulation = ScheduleSimulation::create([
                'status' => ScheduleSimulation::STATUS_DRAFT,
                'horizon_start' => $request->start,
                'horizon_end' => $request->end,
                'parameters' => $request->toArray(),
                'summary' => [
                    'counts' => $result['counts'],
                    'work_orders' => $result['work_orders'],
                    'resources' => $result['resources'],
                    'workforce' => $result['workforce'],
                    'inputs_fingerprint' => $result['inputs_fingerprint'],
                ],
                'created_by_id' => $user->id,
            ]);

            foreach ($result['operations'] as $operation) {
                $simulation->operations()->create($this->attributes($operation));
            }

            return $simulation;
        });
    }

    /**
     * Commit a draft: every fully feasible order gets its minute plan (and a
     * line, if it had none) through the planner, its batch steps get the
     * stations the scheduler picked, and the operation rows become the
     * committed plan. Orders the scheduler could not place keep their current
     * placement; their infeasible operations are still committed, so the
     * reason stays visible.
     *
     * @return array{applied: int, skipped: int, warnings: list<string>}
     *
     * @throws \DomainException when the draft is no longer a draft or the data it was calculated from has changed
     */
    public function apply(ScheduleSimulation $simulation, User $user): array
    {
        if (! $simulation->isDraft()) {
            throw new \DomainException(__('Only a draft schedule can be applied.'));
        }

        $operations = $simulation->operations()->get()->groupBy('work_order_id');
        $orders = WorkOrder::with('batches.steps')->whereIn('id', $operations->keys())->get()->keyBy('id');

        if ($orders->count() !== $operations->count()) {
            throw new \DomainException(__('Some work orders in this schedule no longer exist. Generate it again.'));
        }
        // Each order must still be exactly what the draft was calculated from.
        // Drafts saved before fingerprints existed fall back to the timestamp.
        $fingerprints = collect($simulation->summary['work_orders'] ?? [])->pluck('fingerprint', 'work_order_id');
        $changed = $orders->filter(fn (WorkOrder $o) => $fingerprints->get($o->id) !== null
            ? $fingerprints->get($o->id) !== $o->schedulingFingerprint()
            : $o->updated_at && $o->updated_at->greaterThan($simulation->created_at));
        if ($changed->isNotEmpty()) {
            throw new \DomainException(__('Work orders changed after this schedule was generated (:orders). Generate it again.', [
                'orders' => $changed->pluck('order_no')->implode(', '),
            ]));
        }
        // …and so must everything else it read: work planned around, shifts,
        // maintenance, changeover rules, line efficiency, stations.
        $inputs = $simulation->summary['inputs_fingerprint'] ?? null;
        if ($inputs !== null
            && $inputs !== $this->scheduler->inputsFingerprint($operations->keys()->all(), $simulation->horizon_start, $simulation->horizon_end)) {
            throw new \DomainException(__('Planned work, shifts, maintenance or changeover rules changed after this schedule was generated. Generate it again.'));
        }

        return DB::transaction(function () use ($simulation, $user, $operations, $orders) {
            $applied = 0;
            $skipped = 0;
            $warnings = [];
            $crossLineSteps = 0;

            foreach ($operations as $workOrderId => $rows) {
                $order = $orders->get($workOrderId);
                $feasible = $rows->every(fn (ScheduledOperation $row) => $row->status->isScheduled());

                // Move the order first: moving it drops its old committed
                // operations (ScheduledOperationInvalidationObserver), and the
                // new ones are committed right after.
                if ($feasible) {
                    $result = $this->planner->updateOrder($order, $this->placementFor($order, $rows), force: true);
                    // A routing that crosses lines is what the scheduler just
                    // planned around, so one line per step would only bury the
                    // warnings that need attention. Those are counted instead.
                    $crossLine = $result['cross_line_warnings'] ?? [];
                    $crossLineSteps += count($crossLine);
                    $warnings = [...$warnings, ...array_diff($result['warnings'] ?? [], $crossLine)];
                }

                ScheduledOperation::committed()->where('work_order_id', $workOrderId)->delete();
                $committed = $rows->map(fn (ScheduledOperation $row) => ScheduledOperation::create(
                    ['schedule_simulation_id' => null] + $row->only(array_diff($row->getFillable(), ['schedule_simulation_id']))
                ));

                if (! $feasible) {
                    $skipped++;

                    continue;
                }

                $warnings = [...$warnings, ...$this->assignStations($order->fresh('batches.steps'), $committed, $user)];
                $applied++;
            }

            $simulation->update([
                'status' => ScheduleSimulation::STATUS_APPLIED,
                'applied_by_id' => $user->id,
                'applied_at' => now(),
            ]);

            if ($crossLineSteps > 0) {
                $warnings[] = __('Steps running on a workstation of another line than their work order: :count.', ['count' => $crossLineSteps]);
            }

            return ['applied' => $applied, 'skipped' => $skipped, 'warnings' => array_values(array_unique($warnings))];
        });
    }

    public function discard(ScheduleSimulation $simulation): void
    {
        if (! $simulation->isDraft()) {
            throw new \DomainException(__('Only a draft schedule can be discarded.'));
        }

        DB::transaction(function () use ($simulation) {
            $simulation->operations()->delete();
            $simulation->update(['status' => ScheduleSimulation::STATUS_DISCARDED, 'discarded_at' => now()]);
        });
    }

    /**
     * A planner overrides the station an operation runs on — in a draft before
     * applying it, or in the committed plan. The time window is kept; the new
     * station must be free for it. Committed overrides also move the batch
     * step, through the same pool-dispatch path a supervisor uses by hand.
     *
     * @throws ValidationException
     */
    public function overrideWorkstation(ScheduledOperation $operation, Workstation $station, User $user): ScheduledOperation
    {
        if ($operation->schedule_simulation_id !== null && ! $operation->simulation?->isDraft()) {
            throw ValidationException::withMessages(['workstation_id' => __('This schedule is no longer a draft.')]);
        }
        if (! $operation->status->isScheduled()) {
            throw ValidationException::withMessages(['workstation_id' => __('Only a scheduled operation can be moved to another workstation.')]);
        }

        if ($this->stationBusy($operation, $station)) {
            throw ValidationException::withMessages(['workstation_id' => __('The workstation is already booked in this window.')]);
        }

        // The window was fitted to the old station's shifts and maintenance; the
        // new one must offer the same working time in it, or the operation
        // would run through its maintenance or outside its line's shifts.
        $calendar = ResourceCalendar::forHorizon($operation->planned_start_at, $operation->planned_end_at);
        $from = ResourceCalendar::toMinutes($operation->planned_start_at);
        $to = ResourceCalendar::toMinutes($operation->planned_end_at);
        $needed = $operation->line_id
            ? ResourceCalendar::workingMinutesBetween($calendar->windows((int) $operation->line_id, $operation->workstation_id), $from, $to)
            : 0;
        $offered = ResourceCalendar::workingMinutesBetween($calendar->windows((int) $station->line_id, $station->id), $from, $to);
        if ($offered < $needed) {
            throw ValidationException::withMessages(['workstation_id' => __('The workstation is not available for the whole window (shift or maintenance).')]);
        }

        return DB::transaction(function () use ($operation, $station, $user) {
            $operation->update([
                'workstation_id' => $station->id,
                'line_id' => $station->line_id,
                'resource_source' => ScheduledOperation::SOURCE_MANUAL,
            ]);

            if ($operation->schedule_simulation_id === null && $operation->batch_step_id !== null) {
                $step = BatchStep::find($operation->batch_step_id);
                if ($step && in_array($step->status, [BatchStep::STATUS_PENDING, BatchStep::STATUS_READY], true)) {
                    try {
                        $this->batches->assignWorkstation($step, $station->id, $user);
                    } catch (\Exception $e) {
                        throw ValidationException::withMessages(['workstation_id' => $e->getMessage()]);
                    }
                }
            }

            return $operation->fresh();
        });
    }

    /**
     * Whether the station is taken in the operation's window by anything the
     * scheduler itself works around: other operations of the same plan (the
     * draft, or the committed plan), committed operations of orders outside a
     * draft, and the whole-line window of an order planned by hand without
     * operations. Orders a draft replans do not count with their old plan.
     */
    private function stationBusy(ScheduledOperation $operation, Workstation $station): bool
    {
        $draftId = $operation->schedule_simulation_id;
        $draftOrders = $draftId === null
            ? collect()
            : ScheduledOperation::where('schedule_simulation_id', $draftId)->distinct()->pluck('work_order_id');
        $placed = [ScheduledOperationStatus::Scheduled->value, ScheduledOperationStatus::ScheduledLate->value];
        $overlaps = fn ($q) => $q->where('planned_start_at', '<', $operation->planned_end_at)
            ->where('planned_end_at', '>', $operation->planned_start_at);

        $operationClash = ScheduledOperation::where('id', '!=', $operation->id)
            ->where('workstation_id', $station->id)
            ->whereIn('status', $placed)
            ->where($overlaps)
            ->whereHas('workOrder', fn ($q) => $q->whereIn('status', WorkOrder::ACTIVE_STATUSES))
            ->where(fn ($q) => $draftId === null
                ? $q->whereNull('schedule_simulation_id')
                : $q->where('schedule_simulation_id', $draftId)
                    ->orWhere(fn ($c) => $c->whereNull('schedule_simulation_id')->whereNotIn('work_order_id', $draftOrders)))
            ->exists();
        if ($operationClash) {
            return true;
        }

        $withOperations = ScheduledOperation::committed()->whereIn('status', $placed)->distinct()->select('work_order_id');

        return WorkOrder::whereIn('status', WorkOrder::ACTIVE_STATUSES)
            ->where('line_id', $station->line_id)
            ->where('id', '!=', $operation->work_order_id)
            ->whereNotIn('id', $draftOrders)
            ->whereNotIn('id', $withOperations)
            ->whereNotNull('planned_start_at')
            ->where($overlaps)
            ->exists();
    }

    /**
     * The order's new minute plan: first operation start to last operation end.
     * An order that has started keeps its start — the model refuses to move a
     * started order's start into the future, and it has already begun anyway.
     *
     * @param  \Illuminate\Support\Collection<int, ScheduledOperation>  $rows
     * @return array<string, mixed>
     */
    private function placementFor(WorkOrder $order, $rows): array
    {
        $start = $rows->min('planned_start_at');
        $end = $rows->max('planned_end_at');

        $started = ! in_array($order->status, [WorkOrder::STATUS_PENDING, WorkOrder::STATUS_ACCEPTED], true)
            || (float) $order->produced_qty > 0
            || $order->batches->contains(fn ($b) => $b->steps->contains(fn ($s) => $s->started_at !== null));

        $input = ['planned_end_at' => $end->toDateTimeString()];
        if (! $started) {
            $input['planned_start_at'] = $start->toDateTimeString();
        } elseif ($order->planned_start_at === null) {
            $firstStart = $order->batches->flatMap->steps->pluck('started_at')->filter()->min();
            $input['planned_start_at'] = Carbon::parse($firstStart ?? now()->subMinute())->toDateTimeString();
        }

        if ($order->line_id === null) {
            $input['line_id'] = $this->mainLine($rows);
        }

        // An order placed on the board by shift carries an end date for that
        // span. The minute plan replaces the span, so its end date follows the
        // plan — a stale one before the new start would be refused outright.
        if ($order->end_date !== null) {
            $input['end_date'] = Carbon::parse($input['planned_end_at'])->toDateString();
        }

        // A zero-length plan (everything left is already done) is no plan.
        $from = $input['planned_start_at'] ?? $order->planned_start_at?->toDateTimeString();
        if ($from !== null && Carbon::parse($input['planned_end_at'])->lte(Carbon::parse($from))) {
            $input['planned_end_at'] = Carbon::parse($from)->addMinute()->toDateTimeString();
        }

        return $input;
    }

    /**
     * The line an order without one is placed on: where most of its scheduled
     * time runs, so a routing that crosses lines lands on its main line rather
     * than on wherever the first step happens to be. Ties go to the earlier line.
     *
     * @param  \Illuminate\Support\Collection<int, ScheduledOperation>  $rows
     */
    private function mainLine($rows): ?int
    {
        $minutes = [];
        foreach ($rows->sortBy('planned_start_at') as $row) {
            if ($row->line_id !== null) {
                $minutes[$row->line_id] = ($minutes[$row->line_id] ?? 0)
                    + $row->planned_start_at->diffInMinutes($row->planned_end_at);
            }
        }
        if ($minutes === []) {
            return null;
        }

        return array_search(max($minutes), $minutes, true);
    }

    /**
     * Give batch steps the stations the scheduler picked from their equipment
     * class, and link committed operations to batch steps created on apply.
     *
     * @param  \Illuminate\Support\Collection<int, ScheduledOperation>  $committed
     * @return list<string>
     */
    private function assignStations(WorkOrder $order, $committed, User $user): array
    {
        $warnings = [];
        $byNumber = $order->batches
            ->reject(fn ($b) => $b->status === \App\Models\Batch::STATUS_CANCELLED)
            ->flatMap->steps
            ->groupBy('step_number');

        foreach ($committed as $operation) {
            $steps = $byNumber->get($operation->step_number, collect());
            $open = $steps->first(fn (BatchStep $s) => in_array($s->status, [BatchStep::STATUS_PENDING, BatchStep::STATUS_READY], true));

            if ($operation->batch_step_id === null && $open) {
                $operation->updateQuietly(['batch_step_id' => $open->id]);
            }

            if ($operation->resource_source !== ScheduledOperation::SOURCE_AUTO || ! $operation->workstation_id) {
                continue;
            }

            foreach ($steps as $step) {
                if (! in_array($step->status, [BatchStep::STATUS_PENDING, BatchStep::STATUS_READY], true)
                    || (int) $step->workstation_id === (int) $operation->workstation_id) {
                    continue;
                }
                try {
                    $this->batches->assignWorkstation($step, (int) $operation->workstation_id, $user);
                } catch (\Exception $e) {
                    $warnings[] = __(':order step :step: :message', [
                        'order' => $order->order_no,
                        'step' => $operation->step_number,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $warnings;
    }

    /**
     * @param  array<string, mixed>  $operation  a FiniteScheduler result row
     * @return array<string, mixed>
     */
    private function attributes(array $operation): array
    {
        return [
            'work_order_id' => $operation['work_order_id'],
            'batch_step_id' => $operation['batch_step_id'],
            'step_number' => $operation['step_number'],
            'step_name' => $operation['step_name'],
            'sequence' => $operation['sequence'],
            'line_id' => $operation['line_id'],
            'workstation_id' => $operation['workstation_id'],
            'workstation_type_id' => $operation['workstation_type_id'],
            'resource_source' => $operation['resource_source'],
            'planned_start_at' => $operation['planned_start_at'],
            'planned_end_at' => $operation['planned_end_at'],
            'duration_minutes' => $operation['duration_minutes'],
            'setup_minutes' => $operation['setup_minutes'],
            'required_operators' => $operation['required_operators'],
            'status' => $operation['status'],
            'reasons' => $operation['reasons'],
        ];
    }
}
