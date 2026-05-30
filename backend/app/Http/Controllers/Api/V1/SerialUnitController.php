<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BatchStep;
use App\Models\SerialUnit;
use App\Services\Traceability\SerialTraceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Per-unit (serial) genealogy API. Read access for any authenticated user;
 * registration and step recording for operators on the shop floor.
 */
class SerialUnitController extends Controller
{
    public function __construct(private readonly SerialTraceService $serials) {}

    public function index(Request $request): JsonResponse
    {
        $units = SerialUnit::query()
            ->when($request->query('work_order_id'), fn ($q, $id) => $q->where('work_order_id', $id))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('search'), fn ($q, $s) => $q->where('serial_no', 'like', "%{$s}%"))
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json(['data' => $units]);
    }

    public function show(SerialUnit $serialUnit): JsonResponse
    {
        return response()->json(['data' => $this->serials->getHistory($serialUnit)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'serial_no' => ['required', 'string', 'max:100'],
            'work_order_id' => ['nullable', 'integer', 'exists:work_orders,id'],
            'batch_id' => ['nullable', 'integer', 'exists:batches,id'],
            'material_id' => ['nullable', 'integer', 'exists:materials,id'],
            'status' => ['nullable', Rule::in(SerialUnit::STATUSES)],
        ]);

        $unit = $this->serials->registerUnit($data['serial_no'], $data);

        return response()->json(['data' => $unit], 201);
    }

    public function recordStep(Request $request, SerialUnit $serialUnit): JsonResponse
    {
        $data = $request->validate([
            'batch_step_id' => ['nullable', 'integer', 'exists:batch_steps,id'],
            'workstation_id' => ['nullable', 'integer', 'exists:workstations,id'],
            'parameters' => ['nullable', 'array'],
            'result' => ['nullable', Rule::in(['pass', 'fail', 'rework'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $step = isset($data['batch_step_id']) ? BatchStep::find($data['batch_step_id']) : null;

        $entry = $this->serials->recordStep($serialUnit, $request->user(), $step, $data);

        return response()->json([
            'message' => __('Unit step recorded'),
            'data' => $entry->load(['workstation:id,name,code', 'operator:id,name']),
        ], 201);
    }
}
