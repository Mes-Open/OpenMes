<?php

namespace App\Http\Controllers\Web\Operator;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\StartStepRequest;
use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\BatchStepChecklistCompletion;
use App\Models\BatchStepDocument;
use App\Models\TemplateStepChecklistItem;
use App\Models\WorkOrder;
use App\Services\Lot\BatchReleaseService;
use App\Services\Lot\LotService;
use App\Services\Material\MaterialAllocationService;
use App\Services\Production\PackagingChecklistService;
use App\Services\Production\ProcessConfirmationService;
use App\Services\Production\QualityCheckService;
use App\Services\WorkOrder\BatchService;
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
        protected BatchService $batchService,
        protected MaterialAllocationService $allocationService,
    ) {}

    /**
     * Read-only proposal for the WO-time lot-picking modal shown before a step
     * starts. Returns the materials this step start would lot-pick, each with the
     * proposed split + candidate lots. Empty list → the UI skips the modal.
     */
    public function pickPreview(Request $request, BatchStep $batchStep)
    {
        if (! $this->stepBelongsToSelectedLine($request, $batchStep)) {
            return response()->json(['message' => 'This step does not belong to the selected line.'], 403);
        }

        return response()->json([
            'materials' => $this->allocationService->pickPreviewForStep($batchStep),
        ]);
    }

    /**
     * Start a batch step (React replacement for the old Livewire BatchStepList).
     * Delegates to BatchService::startStep, which also allocates BOM materials.
     * Optionally accepts operator-chosen lot picks (WO-time "suggest + override").
     */
    public function startStep(StartStepRequest $request, BatchStep $batchStep)
    {
        if (! $this->stepBelongsToSelectedLine($request, $batchStep)) {
            return back()->with('error', 'This step does not belong to the selected line.');
        }

        try {
            $picksByMaterial = $this->reshapePicks($request->validated()['picks'] ?? []);
            $this->batchService->startStep($batchStep, $request->user(), $picksByMaterial);

            return back()->with('success', 'Step started. Materials have been allocated.');
        } catch (InsufficientStockException|\DomainException $e) {
            return back()->withErrors(['picks' => $e->getMessage()])->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Reshape the validated picks payload into a material-keyed map for the
     * allocation service: [material_id => [['material_lot_id'=>, 'picked_qty'=>], ...]].
     *
     * @param  array<int, array{material_id: int, lots: array<int, array{material_lot_id: int, picked_qty: float}>}>  $picks
     * @return array<int, array<int, array{material_lot_id: int, picked_qty: float}>>
     */
    private function reshapePicks(array $picks): array
    {
        $out = [];
        foreach ($picks as $row) {
            $materialId = (int) ($row['material_id'] ?? 0);
            if ($materialId <= 0) {
                continue;
            }
            foreach ($row['lots'] ?? [] as $lot) {
                $out[$materialId][] = [
                    'material_lot_id' => (int) $lot['material_lot_id'],
                    'picked_qty' => (float) $lot['picked_qty'],
                ];
            }
        }

        return $out;
    }

    /**
     * Complete a batch step.
     */
    public function completeStep(Request $request, BatchStep $batchStep)
    {
        if (! $this->stepBelongsToSelectedLine($request, $batchStep)) {
            return back()->with('error', 'This step does not belong to the selected line.');
        }

        try {
            $this->batchService->completeStep($batchStep, $request->user());

            return back()->with('success', 'Step completed.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Validate a mandatory document attached to a step (shop-floor document
     * control). Records who validated it and when; once validated, the step's
     * completion gate clears. Idempotent.
     */
    public function validateDocument(Request $request, BatchStepDocument $batchStepDocument)
    {
        $batchStepDocument->loadMissing('batchStep');
        $step = $batchStepDocument->batchStep;

        if (! $step || ! $this->stepBelongsToSelectedLine($request, $step)) {
            return back()->with('error', 'This document does not belong to the selected line.');
        }

        $batchStepDocument->markValidated($request->user());

        return back()->with('success', 'Document validated.');
    }

    /**
     * Stream a step document's uploaded file to the operator so they can read it
     * before validating (line-scoped). Range-enabled via response()->file().
     */
    public function showDocumentFile(Request $request, BatchStepDocument $batchStepDocument)
    {
        $batchStepDocument->loadMissing('batchStep');
        $step = $batchStepDocument->batchStep;

        if (! $step || ! $this->stepBelongsToSelectedLine($request, $step)) {
            abort(403);
        }
        abort_unless($batchStepDocument->file_path && \Illuminate\Support\Facades\Storage::exists($batchStepDocument->file_path), 404);

        return response()->file(\Illuminate\Support\Facades\Storage::path($batchStepDocument->file_path), [
            'Content-Type' => $batchStepDocument->mime_type ?? 'application/octet-stream',
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /**
     * Toggle a work-instruction checklist item on a step: tick it (recording who
     * and when) or un-tick it. The item is defined on the step's template; we
     * verify it belongs to this step's template and step number before recording.
     */
    public function toggleChecklistItem(Request $request, BatchStep $batchStep, TemplateStepChecklistItem $checklistItem)
    {
        if (! $this->stepBelongsToSelectedLine($request, $batchStep)) {
            return back()->with('error', 'This step does not belong to the selected line.');
        }

        // Anti-IDOR: the item must belong to this step's template and step number.
        $templateId = $batchStep->batch?->workOrder?->process_snapshot['template_id'] ?? null;
        $checklistItem->loadMissing('templateStep:id,step_number');
        if ($checklistItem->process_template_id !== $templateId
            || $checklistItem->templateStep?->step_number !== $batchStep->step_number) {
            return back()->with('error', 'This checklist item does not belong to this step.');
        }

        $existing = BatchStepChecklistCompletion::where('batch_step_id', $batchStep->id)
            ->where('checklist_item_id', $checklistItem->id)
            ->first();

        if ($existing) {
            $existing->delete();

            return back()->with('success', 'Checklist item unchecked.');
        }

        BatchStepChecklistCompletion::create([
            'batch_step_id' => $batchStep->id,
            'checklist_item_id' => $checklistItem->id,
            'checked_by_id' => $request->user()->id,
            'checked_at' => now(),
        ]);

        return back()->with('success', 'Checklist item checked.');
    }

    /**
     * Skip an optional or variant step. Reason is optional and stored for audit.
     */
    public function skipStep(Request $request, BatchStep $batchStep)
    {
        if (! $this->stepBelongsToSelectedLine($request, $batchStep)) {
            return back()->with('error', 'This step does not belong to the selected line.');
        }

        $validated = $request->validate([
            'skip_reason' => 'nullable|string|max:255',
        ]);

        try {
            $this->batchService->skipStep($batchStep, $request->user(), $validated['skip_reason'] ?? null);

            return back()->with('success', 'Step skipped.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Choose a variant within its group (activates this step, skips siblings).
     */
    public function chooseVariant(Request $request, BatchStep $batchStep)
    {
        if (! $this->stepBelongsToSelectedLine($request, $batchStep)) {
            return back()->with('error', 'This step does not belong to the selected line.');
        }

        try {
            $this->batchService->chooseVariant($batchStep, $request->user());

            return back()->with('success', 'Variant selected.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /** Guard: the step's work order must be on the operator's selected line. */
    private function stepBelongsToSelectedLine(Request $request, BatchStep $batchStep): bool
    {
        $lineId = $request->session()->get('selected_line_id');
        $batchStep->loadMissing('batch.workOrder');

        return $lineId && $batchStep->batch?->workOrder?->line_id == $lineId;
    }

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
            'pallet_id' => 'nullable|integer|exists:pallets,id',
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

        // Optional pallet link (#106): the pallet must belong to the batch's work order.
        $pallet = null;
        if ($request->filled('pallet_id')) {
            $pallet = \App\Models\Pallet::find($request->input('pallet_id'));
            if ($pallet && $pallet->work_order_id !== $batch->work_order_id) {
                return back()->with('error', __('That pallet belongs to a different work order.'));
            }
        }

        $check = $this->qcService->performCheck($batch, $request->user(), $samples, $request->input('production_quantity'), null, null, $pallet);

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
