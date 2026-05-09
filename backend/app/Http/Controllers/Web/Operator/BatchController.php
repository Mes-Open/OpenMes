<?php

namespace App\Http\Controllers\Web\Operator;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\WorkOrder;
use App\Services\Lot\BatchReleaseService;
use App\Services\Lot\LotService;
use App\Services\Production\PackagingChecklistService;
use App\Services\Production\ProcessConfirmationService;
use App\Services\Production\QualityCheckService;
use App\Services\WorkOrder\WorkOrderService;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    public function __construct(
        protected WorkOrderService $workOrderService,
        protected LotService $lotService,
        protected BatchReleaseService $releaseService,
        protected ProcessConfirmationService $confirmationService,
        protected QualityCheckService $qcService,
        protected PackagingChecklistService $checklistService,
    ) {}

    public function store(Request $request)
    {
        $request->validate([
            'work_order_id' => 'required|exists:work_orders,id',
            'target_qty' => 'required|numeric|min:0.01',
            'workstation_id' => 'nullable|exists:workstations,id',
            'lot_number' => 'nullable|string|max:50',
            'auto_lot' => 'nullable|boolean',
        ]);

        $workOrder = WorkOrder::find($request->input('work_order_id'));

        if ($workOrder->line_id != $request->session()->get('selected_line_id')) {
            return back()->with('error', 'This work order does not belong to the selected line.');
        }

        try {
            $lotNumber = $request->input('lot_number');

            if ($request->boolean('auto_lot') && ! $lotNumber) {
                $lotNumber = $this->lotService->generateLot($workOrder->productType);
            }

            $this->workOrderService->createBatch(
                $workOrder,
                $request->input('target_qty'),
                $request->input('workstation_id'),
                $lotNumber,
            );

            return redirect()->route('operator.work-order.detail', $workOrder)
                ->with('success', 'Batch created'.($lotNumber ? " (LOT: {$lotNumber})" : ''));
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to create batch: '.$e->getMessage())->withInput();
        }
    }

    public function confirmParameters(Request $request, Batch $batch)
    {
        $request->validate([
            'confirmation_type' => 'required|in:parameters,drying,custom',
            'value' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        try {
            if ($request->input('confirmation_type') === 'drying') {
                $this->confirmationService->confirmDrying($batch, $request->user(), (int) $request->input('value'));
            } else {
                $this->confirmationService->confirm($batch, $request->user(), $request->input('confirmation_type'), null, $request->input('notes'), $request->input('value'));
            }

            return back()->with('success', 'Process confirmed successfully.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function qualityCheck(Request $request, Batch $batch)
    {
        $request->validate([
            'production_quantity' => 'nullable|numeric|min:0',
            'samples' => 'required|array|min:1',
            'samples.*.sample_number' => 'required|integer',
            'samples.*.parameter_name' => 'required|string',
            'samples.*.parameter_type' => 'required|in:measurement,pass_fail',
            'samples.*.value_numeric' => 'nullable|numeric',
            'samples.*.value_boolean' => 'nullable',
            'samples.*.is_passed' => 'nullable',
        ]);

        $samples = collect($request->input('samples'))->map(fn ($s) => [
            'sample_number' => $s['sample_number'],
            'parameter_name' => $s['parameter_name'],
            'parameter_type' => $s['parameter_type'],
            'value_numeric' => $s['value_numeric'] ?? null,
            'value_boolean' => isset($s['value_boolean']) ? (bool) $s['value_boolean'] : null,
            'is_passed' => isset($s['is_passed']) ? (bool) $s['is_passed'] : null,
        ])->toArray();

        $check = $this->qcService->performCheck($batch, $request->user(), $samples, $request->input('production_quantity'));

        return back()->with($check->all_passed ? 'success' : 'warning',
            $check->all_passed ? 'Quality check passed.' : 'Quality check recorded — some samples failed.');
    }

    public function packagingChecklist(Request $request, Batch $batch)
    {
        $request->validate([
            'udi_readable' => 'required',
            'packaging_condition' => 'required',
            'labels_readable' => 'required',
            'label_matches_product' => 'required',
            'notes' => 'nullable|string',
        ]);

        try {
            $checklist = $this->checklistService->submit($batch, $request->user(), [
                'udi_readable' => $request->boolean('udi_readable'),
                'packaging_condition' => $request->boolean('packaging_condition'),
                'labels_readable' => $request->boolean('labels_readable'),
                'label_matches_product' => $request->boolean('label_matches_product'),
                'notes' => $request->input('notes'),
            ]);

            return back()->with($checklist->fresh()->all_passed ? 'success' : 'warning',
                $checklist->fresh()->all_passed ? 'Packaging checklist passed.' : 'Packaging checklist — some items failed.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function release(Request $request, Batch $batch)
    {
        $request->validate([
            'release_type' => 'required|in:for_production,for_sale',
            'scrap_qty' => 'nullable|numeric|min:0',
        ]);

        if ($request->filled('scrap_qty')) {
            $batch->update(['scrap_qty' => $request->input('scrap_qty')]);
        }

        try {
            $released = $this->releaseService->release($batch, $request->user(), $request->input('release_type'));

            $msg = "Batch released (LOT: {$released->lot_number})";
            if ($released->expiry_date) {
                $msg .= " — Expiry: {$released->expiry_date->format('Y-m-d')}";
            }

            return back()->with('success', $msg);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
