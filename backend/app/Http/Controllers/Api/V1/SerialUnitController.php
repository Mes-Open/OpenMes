<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BindUnitComponentApiRequest;
use App\Http\Requests\Api\V1\RecordSerialUnitStepRequest;
use App\Http\Requests\Api\V1\StoreSerialUnitRequest;
use App\Http\Requests\BlockUnitRequest;
use App\Http\Requests\UnblockUnitRequest;
use App\Models\BatchStep;
use App\Models\ScrapReason;
use App\Models\SerialUnit;
use App\Services\Traceability\BindingException;
use App\Services\Traceability\SerialTraceService;
use App\Support\UnitSerialisation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            ->when($request->query('serial_no'), fn ($q, $s) => $q->where('serial_no', app(UnitSerialisation::class)->normalize($s)))
            ->when($request->query('psn'), fn ($q, $s) => $q->where('psn', app(UnitSerialisation::class)->normalize($s)))
            ->when($request->query('search'), fn ($q, $s) => $q->where(
                fn ($qq) => $qq->where('serial_no', 'like', "%{$s}%")->orWhere('psn', 'like', "%{$s}%")
            ))
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json(['data' => $units]);
    }

    public function show(SerialUnit $serialUnit): JsonResponse
    {
        return response()->json(['data' => $this->serials->getHistory($serialUnit)]);
    }

    public function store(StoreSerialUnitRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (empty($data['serial_no'])) {
            try {
                $unit = $this->serials->startUnit($data['psn'], $request->user(), ['work_order_id' => $data['work_order_id'] ?? null]);
            } catch (BindingException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return response()->json(['data' => $unit], 201);
        }

        $unit = $this->serials->registerUnit($data['serial_no'], $data);

        return response()->json(['data' => $unit], 201);
    }

    /** Hold a non-conforming unit with an error code (a scrap reason). */
    public function block(BlockUnitRequest $request, SerialUnit $serialUnit): JsonResponse
    {
        try {
            $unit = $this->serials->blockUnit($serialUnit, $request->user(), ScrapReason::findOrFail($request->validated('scrap_reason_id')), $request->validated('note'));
        } catch (BindingException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $unit]);
    }

    /** Release a held unit - supervisors and admins. */
    public function unblock(UnblockUnitRequest $request, SerialUnit $serialUnit): JsonResponse
    {
        try {
            $unit = $this->serials->unblockUnit($serialUnit, $request->user(), $request->validated('note'));
        } catch (BindingException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $unit]);
    }

    public function recordStep(RecordSerialUnitStepRequest $request, SerialUnit $serialUnit): JsonResponse
    {
        $data = $request->validated();

        $step = isset($data['batch_step_id']) ? BatchStep::find($data['batch_step_id']) : null;

        $entry = $this->serials->recordStep($serialUnit, $request->user(), $step, $data);

        return response()->json([
            'message' => __('Unit step recorded'),
            'data' => $entry->load(['workstation:id,name,code', 'operator:id,name']),
        ], 201);
    }

    /** Scan a component onto a unit (a sub-assembly serial, a lot, or a bare identifier). */
    public function bindComponent(BindUnitComponentApiRequest $request, SerialUnit $serialUnit): JsonResponse
    {
        $data = $request->validated();
        try {
            $component = $this->serials->bindComponent($serialUnit, $data['identifier'], $request->user(), [
                'material_id' => $data['material_id'] ?? null,
                'quantity' => $data['quantity'] ?? 1,
                'batch_step_id' => $data['batch_step_id'] ?? null,
                'workstation_id' => $data['workstation_id'] ?? null,
            ]);
        } catch (BindingException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => __('Component bound'), 'data' => $component->load('material:id,code,name')], 201);
    }
}
