<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AssignSerialRequest;
use App\Http\Requests\Api\V1\CompleteUnitStepRequest;
use App\Http\Requests\Api\V1\RegisterUnitRequest;
use App\Http\Requests\Api\V1\StartUnitStepRequest;
use App\Models\Batch;
use App\Models\SerialUnit;
use App\Models\UnitStep;
use App\Services\Unit\UnitProgressionService;
use Illuminate\Http\JsonResponse;

/**
 * Phase 1 API surface for unit-level (serial) execution (#290) — no operator
 * UI yet, exercised via this API / feature tests / Tinker while the data
 * model and interlock logic are validated.
 */
class UnitStepController extends Controller
{
    public function __construct(private readonly UnitProgressionService $progression) {}

    public function register(RegisterUnitRequest $request): JsonResponse
    {
        $batch = Batch::findOrFail($request->validated('batch_id'));

        try {
            $unit = $this->progression->registerUnit(
                $batch,
                $request->validated('serial_no'),
                $request->boolean('auto_generate'),
            );

            return response()->json([
                'data' => $unit->load('history'),
                'unit_steps' => UnitStep::where('serial_unit_id', $unit->id)->orderBy('step_number')->get(),
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function assignSerial(AssignSerialRequest $request, SerialUnit $serialUnit): JsonResponse
    {
        try {
            $unit = $this->progression->assignSerial(
                $serialUnit,
                $request->validated('serial_no'),
                $request->boolean('auto_generate'),
            );

            return response()->json(['data' => $unit]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function start(StartUnitStepRequest $request, UnitStep $unitStep): JsonResponse
    {
        try {
            $step = $this->progression->startUnitStep($unitStep, $request->user());

            return response()->json(['data' => $step]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function complete(CompleteUnitStepRequest $request, UnitStep $unitStep): JsonResponse
    {
        try {
            $step = $this->progression->completeUnitStep($unitStep, $request->user());

            return response()->json(['data' => $step->load('batch')]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
