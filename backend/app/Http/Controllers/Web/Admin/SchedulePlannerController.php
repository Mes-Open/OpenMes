<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\StoreMaintenanceEventRequest;
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
        $board = $this->planner->board([
            'view_mode' => $request->input('view_mode'),
            'start_date' => $request->input('start_date'),
            'line_id' => $request->input('line_id'),
        ]);

        return Inertia::render('admin/schedule/Planner', [
            ...$board,
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
}
