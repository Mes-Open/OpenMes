<?php

namespace App\Http\Controllers\Web\Operator;

use App\Http\Controllers\Controller;
use App\Models\IssueType;
use App\Models\LineStatus;
use App\Models\ScrapReason;
use App\Models\WorkOrder;
use App\Models\Workstation;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkOrderController extends Controller
{
    public function __construct(
        protected WorkOrderService $workOrderService
    ) {}

    /**
     * Show work order queue for selected line.
     */
    public function queue(Request $request)
    {
        $lineId = $request->session()->get('selected_line_id')
            ?? $request->query('line');

        // Workstation accounts auto-select their assigned line
        if (! $lineId && auth()->user()->account_type === 'workstation') {
            $lineId = auth()->user()->workstation?->line_id;
        }

        if (! $lineId) {
            return redirect()->route('operator.select-line');
        }

        // Persist in session for subsequent requests
        $request->session()->put('selected_line_id', $lineId);

        // Get active and completed work orders for this line
        $activeWorkOrders = WorkOrder::where('line_id', $lineId)
            ->whereIn('status', WorkOrder::ACTIVE_STATUSES)
            ->with(['productType', 'batches.steps.workstation', 'lineStatus'])
            ->orderBy('priority', 'desc')
            ->orderBy('due_date', 'asc')
            ->get();

        $completedWorkOrders = WorkOrder::where('line_id', $lineId)
            ->where('status', WorkOrder::STATUS_DONE)
            ->with(['productType', 'batches', 'lineStatus'])
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();

        $line = \App\Models\Line::find($lineId);

        $settingRows = DB::table('system_settings')->get()->keyBy('key');
        $workflowMode = json_decode($settingRows['workflow_mode']->value ?? '"status"', true) ?? 'status';
        $trackingMode = json_decode($settingRows['production_tracking_mode']->value ?? '"per_operation"', true) ?? 'per_operation';
        $routingEnabled = json_decode($settingRows['workstation_routing_enabled']->value ?? 'false', true) ?? false;

        // Workstation filter: from query param, session, or workstation account.
        // Workstation accounts default to their own assigned workstation.
        $selectedWorkstationId = $request->query('workstation')
            ?? $request->session()->get('selected_workstation_id')
            ?? (auth()->user()->account_type === 'workstation' ? auth()->user()->workstation_id : null);
        if ($request->has('workstation')) {
            $request->session()->put('selected_workstation_id', $selectedWorkstationId);
        }
        // A workstation may belong to another line when routing spans lines, so
        // only constrain to the current line when routing is disabled.
        $selectedWorkstation = $selectedWorkstationId
            ? ($routingEnabled
                ? Workstation::find($selectedWorkstationId)
                : Workstation::where('id', $selectedWorkstationId)->where('line_id', $lineId)->first())
            : null;

        $lineStatuses = LineStatus::forLine($lineId)->get();

        $issueTypes = IssueType::where('is_active', true)->orderBy('name')->get();

        $doneStatusIds = $lineStatuses->where('is_done_status', true)->pluck('id')->values();

        // In per_operation/hybrid mode with selected workstation: filter to WOs with current step on this workstation
        $workstationQueue = collect();
        if (in_array($trackingMode, ['per_operation', 'hybrid']) && $selectedWorkstation) {
            // When routing is enabled, scan all active work orders (steps may route
            // across lines, e.g. a shared packing station); otherwise stay on this line.
            $queueSource = $routingEnabled
                ? WorkOrder::whereIn('status', WorkOrder::ACTIVE_STATUSES)
                    ->with(['productType', 'batches.steps.workstation'])
                    ->get()
                : $activeWorkOrders;

            $workstationQueue = $queueSource->filter(function ($wo) use ($selectedWorkstation) {
                foreach ($wo->batches as $batch) {
                    $currentStep = $batch->currentStep();
                    if ($currentStep && (int) $currentStep->workstation_id === (int) $selectedWorkstation->id) {
                        return true;
                    }
                }
                return false;
            })->values();
        }

        // Load available workstations for this line (for the workstation filter dropdown)
        $lineWorkstations = \App\Models\Workstation::where('line_id', $lineId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('operator.queue', compact(
            'activeWorkOrders', 'completedWorkOrders', 'line', 'selectedWorkstation',
            'lineStatuses', 'issueTypes', 'workflowMode', 'doneStatusIds',
            'trackingMode', 'workstationQueue', 'lineWorkstations'
        ));
    }

    /**
     * Update the line status (kanban status) of a work order.
     */
    public function updateLineStatus(Request $request, WorkOrder $workOrder)
    {
        $lineId = $request->session()->get('selected_line_id');

        if ($workOrder->line_id != $lineId) {
            return back()->with('error', 'This work order does not belong to the selected line.');
        }

        $validated = $request->validate([
            'line_status_id' => 'nullable|exists:line_statuses,id',
            'produced_qty' => 'nullable|numeric|min:0',
        ]);

        $updates = ['line_status_id' => $validated['line_status_id']];

        // In board_status mode: if the selected status is a "done" status, complete the work order
        if ($validated['line_status_id']) {
            $settingRows = DB::table('system_settings')->get()->keyBy('key');
            $workflowMode = json_decode($settingRows['workflow_mode']->value ?? '"status"', true) ?? 'status';

            if ($workflowMode === 'board_status') {
                $newStatus = LineStatus::find($validated['line_status_id']);
                if ($newStatus && $newStatus->is_done_status) {
                    $updates['status'] = WorkOrder::STATUS_DONE;
                    $updates['completed_at'] = now();
                    if (isset($validated['produced_qty'])) {
                        $updates['produced_qty'] = $validated['produced_qty'];
                    }
                }
            }
        }

        $workOrder->update($updates);

        return back()->with('success', 'Status updated.');
    }

    /**
     * JSON endpoint for polling — returns current queue counts.
     */
    public function check(Request $request)
    {
        $lineId = $request->session()->get('selected_line_id');
        if (!$lineId) {
            return response()->json(['active' => 0, 'workstation' => 0]);
        }

        $activeCount = WorkOrder::where('line_id', $lineId)
            ->whereIn('status', WorkOrder::ACTIVE_STATUSES)
            ->count();

        $workstationCount = 0;
        $wsId = $request->session()->get('selected_workstation_id');
        $settingRows = DB::table('system_settings')->get()->keyBy('key');
        $trackingMode = json_decode($settingRows['production_tracking_mode']->value ?? '"per_operation"', true) ?? 'per_operation';

        if ($wsId && in_array($trackingMode, ['per_operation', 'hybrid'])) {
            $workstationCount = WorkOrder::where('line_id', $lineId)
                ->whereIn('status', WorkOrder::ACTIVE_STATUSES)
                ->with('batches.steps')
                ->get()
                ->filter(function ($wo) use ($wsId) {
                    foreach ($wo->batches as $batch) {
                        $step = $batch->currentStep();
                        if ($step && $step->workstation_id == $wsId) {
                            return true;
                        }
                    }
                    return false;
                })->count();
        }

        return response()->json([
            'active' => $activeCount,
            'workstation' => $workstationCount,
            'timestamp' => now()->timestamp,
        ]);
    }

    /**
     * Show work order detail page.
     */
    public function show(Request $request, WorkOrder $workOrder)
    {
        $lineId = $request->session()->get('selected_line_id');

        // Verify work order belongs to selected line
        if ($workOrder->line_id != $lineId) {
            return redirect()->route('operator.queue')
                ->with('error', 'This work order does not belong to the selected line.');
        }

        $workOrder->load([
            'line',
            'productType',
            'batches.steps.startedBy',
            'batches.steps.completedBy',
            'batches.workstation',
            'batches.processConfirmations.confirmedBy',
            'batches.qualityChecks.samples',
            'batches.qualityChecks.checkedBy',
            'batches.packagingChecklist',
            'issues.issueType',
            'issues.reportedBy',
            'scrapEntries.scrapReason',
            'scrapEntries.reportedBy',
        ]);

        $issueTypes = IssueType::where('is_active', true)->orderBy('name')->get();

        $scrapReasons = ScrapReason::active()->ordered()->get();

        // Only show workstations from this line (not all system workstations)
        $workstations = $workOrder->line
            ? Workstation::where('line_id', $workOrder->line_id)->where('is_active', true)->orderBy('name')->get()
            : collect();

        // Auto-select workstation if operator is a workstation account
        $defaultWorkstationId = auth()->user()->workstation_id;

        return view('operator.work-order-detail', compact('workOrder', 'issueTypes', 'scrapReasons', 'workstations', 'defaultWorkstationId'));
    }
}
