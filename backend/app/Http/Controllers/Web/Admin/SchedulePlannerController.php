<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\StoreMaintenanceEventRequest;
use App\Models\FlowerLot;
use App\Models\FlowerLotAllocation;
use App\Models\ScheduleChangeLog;
use App\Models\WorkOrder;
use App\Services\Schedule\SchedulePlannerService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SchedulePlannerController extends Controller
{
    public function __construct(private readonly SchedulePlannerService $planner) {}

    public function index(Request $request)
    {
        if ($request->input('view_mode') === 'hourly') {
            return redirect()->route('admin.schedule', [...$request->query(), 'view_mode' => 'daily']);
        }

        $board = $this->planner->board([
            'view_mode' => $request->input('view_mode'),
            'start_date' => $request->input('start_date'),
            'line_id' => $request->input('line_id'),
        ]);

        if ($board['viewMode'] === 'hourly') {
            return redirect()->route('admin.schedule', [...$request->query(), 'view_mode' => 'daily']);
        }

        return Inertia::render('admin/schedule/Planner', [
            ...$board,
            'flowerSchedule' => $this->flowerSchedule($board['rangeStart'], $board['rangeEnd']),
            // For the "+ New order" modal (shares the create page's form).
            'productTypes' => \App\Models\ProductType::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'customers' => \App\Models\Customer::active()->orderBy('name')->get(['id', 'name', 'tier']),
            'customFields' => app(\App\Services\CustomFieldService::class)->clientConfig('work_order'),
        ]);
    }

    public function updateOrder(\App\Http\Requests\Api\V1\ScheduleUpdateOrderRequest $request, WorkOrder $workOrder)
    {
        // Presence, not value, decides what gets written: only() drops keys the
        // request never carried, matching the service's partial-update contract.
        $result = $this->planner->updateOrder(
            $workOrder,
            $request->only([
                'line_id', 'due_date', 'week_number', 'shift_number', 'end_date',
                'end_shift_number', 'planned_start_at', 'planned_end_at', 'extra_placements',
            ]),
            $request->boolean('force_conflict'),
        );

        if ($result['conflict']) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'conflict' => true,
                    'message' => $result['message'],
                ], 409);
            }

            return back()->with('error', $result['message']);
        }

        $warnings = $result['warnings'];

        $message = __('Work order updated successfully.');
        if (! empty($warnings)) {
            $message .= ' '.__('Warnings:').' '.implode('; ', $warnings);
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'warnings' => $warnings,
                'order' => [
                    'id' => $workOrder->id,
                    'order_no' => $workOrder->order_no,
                    'line_id' => $workOrder->line_id,
                    'due_date' => $workOrder->due_date?->format('Y-m-d'),
                    'week_number' => $workOrder->week_number,
                ],
            ]);
        }

        return back()->with('success', __('Work order updated successfully.'));
    }

    public function resizeOrder(\App\Http\Requests\Web\Admin\ScheduleResizeOrderRequest $request, WorkOrder $workOrder)
    {
        // Minute-level resize: when both `planned_start_at` and
        // `planned_end_at` are present we treat the request as a minute-level
        // move/resize and bypass the legacy shift-level branch.
        $minuteLevel = $request->filled('planned_start_at') && $request->filled('planned_end_at');

        $input = $request->validated();

        $result = $this->planner->resizeOrder($workOrder, $input, $request->boolean('force_conflict'));

        if ($result['conflict']) {
            return response()->json([
                'success' => false,
                'conflict' => true,
                'message' => $result['message'],
            ], 409);
        }

        if ($minuteLevel) {
            return response()->json([
                'success' => true,
                'message' => __('Work order span updated.'),
                'order' => [
                    'id' => $workOrder->id,
                    'order_no' => $workOrder->order_no,
                    'planned_start_at' => $workOrder->planned_start_at?->toIso8601String(),
                    'planned_end_at' => $workOrder->planned_end_at?->toIso8601String(),
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => __('Work order span updated.'),
            'order' => [
                'id' => $workOrder->id,
                'order_no' => $workOrder->order_no,
                'due_date' => $workOrder->due_date?->format('Y-m-d'),
                'shift_number' => $workOrder->shift_number,
                'end_date' => $workOrder->end_date?->format('Y-m-d'),
                'end_shift_number' => $workOrder->end_shift_number,
            ],
        ]);
    }

    /**
     * Place a maintenance event on the planner (the "Add maintenance" modal). A
     * defined maintenance schedule can pre-fill it, or an ad-hoc title/type.
     */
    public function storeMaintenance(StoreMaintenanceEventRequest $request)
    {
        $this->planner->createMaintenanceEvent($request->validated());

        return back()->with('success', __('Maintenance added to the planner.'));
    }

    public function checkUpdates(Request $request)
    {
        $lastUpdated = WorkOrder::max('updated_at');

        $response = [
            'last_updated' => $lastUpdated ? Carbon::parse($lastUpdated)->toIso8601String() : null,
        ];

        // Live tracking: return real-time data for a specific work order
        if ($request->filled('track')) {
            $wo = WorkOrder::with(['productType', 'line', 'batches.steps.workstation'])
                ->find($request->track);

            if ($wo) {
                $planned = (float) $wo->planned_qty;
                $produced = (float) $wo->produced_qty;
                $percent = $planned > 0 ? min(100, round(($produced / $planned) * 100, 1)) : 0;

                // Current batch step info
                $currentStep = null;
                foreach ($wo->batches as $batch) {
                    $step = $batch->steps->firstWhere('status', 'in_progress')
                        ?? $batch->steps->firstWhere('status', 'pending');
                    if ($step) {
                        $currentStep = [
                            'name' => $step->name ?? $step->workstation?->name ?? '-',
                            'status' => $step->status,
                            'batch_number' => $batch->batch_number,
                        ];
                        break;
                    }
                }

                $isOverdue = $wo->due_date
                    && $wo->due_date->lt(today())
                    && ! in_array($wo->status, WorkOrder::TERMINAL_STATUSES);

                $response['tracked_order'] = [
                    'id' => $wo->id,
                    'order_no' => $wo->order_no,
                    'status' => $wo->status,
                    'line' => $wo->line?->name ?? '-',
                    'product' => $wo->productType?->name ?? '-',
                    'planned_qty' => $planned,
                    'produced_qty' => $produced,
                    'progress_percent' => $percent,
                    'is_overdue' => $isOverdue,
                    'current_step' => $currentStep,
                    'updated_at' => $wo->updated_at->toIso8601String(),
                ];
            }
        }

        return response()->json($response);
    }

    /**
     * The last planner edits, newest first — the backlog rail's Changes tab.
     */
    public function changes()
    {
        return response()->json(['changes' => $this->planner->recentChanges()]);
    }

    /**
     * Revert one edit: restore the order's placement snapshot from before it.
     * The revert is itself logged (action 'undo'), so it can be undone too.
     */
    public function undoChange(ScheduleChangeLog $change)
    {
        if (! $this->planner->undoChange($change)) {
            return response()->json(['success' => false, 'message' => __('Work order no longer exists.')], 410);
        }

        return response()->json(['success' => true, 'message' => __('Change undone.')]);
    }

    /**
     * Flower windows and occupied scenting slots for the planner board.
     *
     * Arrival blocks span from arrived_at to usable_until; allocation blocks
     * span from allocated_at to the batch's planned end. A lot whose window
     * already closed is rendered red, as is an allocation that overlaps another
     * allocation on the same batch.
     */
    private function flowerSchedule(string $rangeStart, string $rangeEnd): array
    {
        $start = Carbon::parse($rangeStart)->startOfDay();
        $end = Carbon::parse($rangeEnd)->endOfDay();

        $lots = FlowerLot::query()
            ->with(['allocations.batch.workOrder.productType'])
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('arrived_at', [$start, $end])
                    ->orWhere(function ($inner) use ($start, $end) {
                        $inner->whereNotNull('usable_until')
                            ->where('usable_until', '>=', $start)
                            ->where('arrived_at', '<=', $end);
                    });
            })
            ->orderBy('usable_until')
            ->orderBy('id')
            ->get();

        $arrivals = $lots->map(function (FlowerLot $lot) {
            $status = $lot->effectiveStatus();

            return [
                'id' => $lot->id,
                'code' => $lot->code,
                'variety' => $lot->variety,
                'origin' => $lot->origin,
                'weight_kg' => (float) $lot->weight_kg,
                'remaining_weight' => $lot->remainingWeight(),
                'arrived_at' => $lot->arrived_at?->toIso8601String(),
                'usable_from' => $lot->usable_from?->toIso8601String(),
                'usable_until' => $lot->usable_until?->toIso8601String(),
                'status' => $status,
                'status_label' => $lot->statusLabel(),
                'urgency' => $lot->urgency(),
                'remaining_seconds' => $lot->remainingSeconds(),
                'opening_rate' => $lot->opening_rate !== null ? (float) $lot->opening_rate : null,
                'has_allocation' => $lot->allocations->isNotEmpty(),
            ];
        })->values();

        $occupancies = $lots
            ->flatMap(function (FlowerLot $lot) {
                return $lot->allocations->map(function (FlowerLotAllocation $allocation) use ($lot) {
                    $batch = $allocation->batch;
                    $plannedEnd = $batch?->workOrder?->planned_end_at ?? $allocation->allocated_at?->copy()->addHours(12);
                    $startAt = $allocation->allocated_at;
                    $endAt = $plannedEnd && $startAt && $plannedEnd->greaterThan($startAt)
                        ? $plannedEnd
                        : $startAt?->copy()->addHours(12);

                    return [
                        'id' => $allocation->id,
                        'flower_lot_id' => $lot->id,
                        'flower_code' => $lot->code,
                        'weight_kg' => (float) $allocation->weight_kg,
                        'seep_count' => (int) $allocation->seep_count,
                        'start_at' => $startAt?->toIso8601String(),
                        'end_at' => $endAt?->toIso8601String(),
                        'window_until' => $lot->usable_until?->toIso8601String(),
                        'batch_id' => $batch?->id,
                        'batch_number' => $batch?->batch_number,
                        'product' => $batch?->workOrder?->productType?->name,
                        'conflict' => false,
                        'conflict_reason' => null,
                    ];
                });
            })
            ->filter(fn ($row) => $row['start_at'] && $row['end_at'])
            ->values();

        // Two allocations on the same batch that overlap are a scheduling
        // conflict: the same scenting bed cannot be occupied twice.
        $conflictCount = 0;
        $occupancies = $occupancies->map(function ($row) use ($occupancies, &$conflictCount) {
            if (! $row['batch_id']) {
                return $row;
            }

            $overlaps = $occupancies->first(function ($other) use ($row) {
                return $other['id'] !== $row['id']
                    && $other['batch_id'] === $row['batch_id']
                    && $row['start_at'] < $other['end_at']
                    && $row['end_at'] > $other['start_at'];
            });

            if ($overlaps) {
                $row['conflict'] = true;
                $row['conflict_reason'] = '同一批次存在重叠的付窨占用。';
                $conflictCount++;
            }

            if (! $row['conflict'] && $row['window_until'] && $row['start_at'] > $row['window_until']) {
                $row['conflict'] = true;
                $row['conflict_reason'] = '付窨开始时间晚于鲜花可用截止。';
                $conflictCount++;
            }

            return $row;
        })->values();

        $arrivals = $arrivals->map(function ($row) use ($occupancies) {
            $row['has_conflict'] = $occupancies->contains(fn ($occ) => $occ['flower_lot_id'] === $row['id'] && $occ['conflict']);

            return $row;
        })->values();

        return [
            'arrivals' => $arrivals->all(),
            'occupancies' => $occupancies->all(),
            'summary' => [
                'arrivals' => $arrivals->count(),
                'available' => $arrivals->where('status', FlowerLot::STATUS_AVAILABLE)->count(),
                'expired' => $arrivals->where('status', FlowerLot::STATUS_EXPIRED)->count(),
                'occupancies' => $occupancies->count(),
                'conflicts' => $conflictCount,
            ],
        ];
    }
}
