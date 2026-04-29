<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\ProcessConfirmation;
use App\Services\Production\ProcessConfirmationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProcessConfirmationController extends Controller
{
    public function __construct(private ProcessConfirmationService $service) {}

    public function index(Batch $batch): JsonResponse
    {
        $confirmations = $batch->processConfirmations()->with('confirmedBy')->get();

        return response()->json(['data' => $confirmations]);
    }

    public function store(Request $request, Batch $batch): JsonResponse
    {
        $validated = $request->validate([
            'confirmation_type' => 'required|string|in:parameters,drying,custom',
            'batch_step_id' => 'nullable|exists:batch_steps,id',
            'notes' => 'nullable|string',
            'value' => 'nullable|string|max:100',
        ]);

        $step = isset($validated['batch_step_id']) ? BatchStep::find($validated['batch_step_id']) : null;

        if ($validated['confirmation_type'] === ProcessConfirmation::TYPE_DRYING) {
            if (! $validated['value'] || ! is_numeric($validated['value'])) {
                return response()->json(['message' => 'Drying hours value is required.'], 422);
            }

            try {
                $confirmation = $this->service->confirmDrying($batch, $request->user(), (int) $validated['value'], $step);
            } catch (\RuntimeException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        } else {
            $confirmation = $this->service->confirm(
                $batch,
                $request->user(),
                $validated['confirmation_type'],
                $step,
                $validated['notes'] ?? null,
                $validated['value'] ?? null,
            );
        }

        return response()->json([
            'message' => 'Process confirmed',
            'data' => $confirmation->load('confirmedBy'),
        ], 201);
    }

    public function status(Batch $batch): JsonResponse
    {
        return response()->json([
            'data' => [
                'confirmed_today' => $this->service->isConfirmedToday($batch),
                'total_confirmations' => $this->service->getConfirmationsCount($batch),
            ],
        ]);
    }
}
