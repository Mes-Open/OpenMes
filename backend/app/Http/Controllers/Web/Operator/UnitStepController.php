<?php

namespace App\Http\Controllers\Web\Operator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\CompleteUnitStepRequest;
use App\Http\Requests\Operator\RegisterUnitRequest;
use App\Http\Requests\Operator\StartUnitStepRequest;
use App\Models\Batch;
use App\Models\UnitStep;
use App\Services\Unit\UnitProgressionService;
use Illuminate\Http\Request;

/**
 * Operator-facing unit-level (serial) execution (#290) — the Unit-mode
 * counterpart to BatchController's start/completeStep, driving
 * UnitProgressionService from the same operator session/line-selection
 * flow instead of the Phase 1 API token routes.
 */
class UnitStepController extends Controller
{
    public function __construct(protected UnitProgressionService $progression) {}

    public function register(RegisterUnitRequest $request)
    {
        $batch = Batch::findOrFail($request->validated('batch_id'));

        if (! $this->batchBelongsToSelectedLine($request, $batch)) {
            return back()->with('error', __('This batch does not belong to the selected line.'));
        }

        try {
            $unit = $this->progression->registerUnit($batch, $request->validated('serial_no'));

            return back()->with('success', __('Unit :serial registered.', ['serial' => $unit->serial_no]));
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function start(StartUnitStepRequest $request, UnitStep $unitStep)
    {
        if (! $this->stepBelongsToSelectedLine($request, $unitStep)) {
            return back()->with('error', __('This step does not belong to the selected line.'));
        }

        try {
            $this->progression->startUnitStep($unitStep, $request->user());

            return back()->with('success', __('Unit step started.'));
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function complete(CompleteUnitStepRequest $request, UnitStep $unitStep)
    {
        if (! $this->stepBelongsToSelectedLine($request, $unitStep)) {
            return back()->with('error', __('This step does not belong to the selected line.'));
        }

        try {
            $this->progression->completeUnitStep($unitStep, $request->user());

            return back()->with('success', __('Unit step completed.'));
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    private function batchBelongsToSelectedLine(Request $request, Batch $batch): bool
    {
        $lineId = $request->session()->get('selected_line_id');
        $batch->loadMissing('workOrder');

        return $lineId && $batch->workOrder?->line_id == $lineId;
    }

    private function stepBelongsToSelectedLine(Request $request, UnitStep $unitStep): bool
    {
        $lineId = $request->session()->get('selected_line_id');
        $unitStep->loadMissing('batch.workOrder');

        return $lineId && $unitStep->batch?->workOrder?->line_id == $lineId;
    }
}
