<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quality\CompleteInspectionRequest;
use App\Http\Requests\Quality\InspectionDispositionRequest;
use App\Http\Requests\Quality\RecordInspectionResultRequest;
use App\Http\Requests\Quality\StoreInspectionRequest;
use App\Models\Inspection;
use App\Models\InspectionPlan;
use App\Models\InspectionResult;
use App\Models\Material;
use App\Services\Quality\DispositionService;
use App\Services\Quality\InboundInspectionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Inbound inspections over the API. Every action is authorised by
 * InspectionPolicy — through the Form Requests for writes, authorize() for
 * reads — the same policy the operator screen uses, so a token cannot do
 * anything the panel would refuse.
 */
class InspectionController extends Controller
{
    public function __construct(private InboundInspectionService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Inspection::class);

        $request->validate([
            'material_id' => 'nullable|integer|exists:materials,id',
            'lot_number' => 'nullable|string|max:100',
            'status' => 'nullable|string|in:pending,pass,fail,conditional_pass',
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'limit' => 'nullable|integer|min:1|max:200',
        ]);

        $query = Inspection::with(['material', 'plan', 'inspector', 'issue']);

        if ($request->filled('material_id')) {
            $query->where('material_id', $request->integer('material_id'));
        }
        if ($request->filled('lot_number')) {
            $query->where('lot_number', $request->input('lot_number'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('started_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('started_at', '<=', $request->input('date_to'));
        }

        $records = $query->orderByDesc('started_at')->limit($request->integer('limit', 50))->get();

        return response()->json(['data' => $records]);
    }

    public function show(Inspection $inspection): JsonResponse
    {
        $this->authorize('view', $inspection);

        return response()->json([
            'data' => $inspection->load(['results', 'material', 'plan', 'inspector', 'issue']),
        ]);
    }

    public function store(StoreInspectionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $material = Material::findOrFail($validated['material_id']);
        $plan = isset($validated['inspection_plan_id'])
            ? InspectionPlan::findOrFail($validated['inspection_plan_id'])
            : null;

        $inspection = $this->service->start(
            $material,
            $validated['lot_number'],
            $validated['quantity_received'] ?? null,
            $plan,
            $request->user(),
            $validated['supplier_lot_ref'] ?? null,
            $validated['source_container_no'] ?? null,
        );

        return response()->json(['message' => 'Inspection started', 'data' => $inspection], 201);
    }

    public function recordResult(RecordInspectionResultRequest $request, Inspection $inspection, InspectionResult $result): JsonResponse
    {
        if ($result->inspection_id !== $inspection->id) {
            abort(404);
        }

        try {
            $updated = $this->service->recordResult($result, $request->validated());
        } catch (\DomainException $e) {
            abort(422, $e->getMessage());
        }

        return response()->json(['data' => $updated]);
    }

    public function complete(CompleteInspectionRequest $request, Inspection $inspection): JsonResponse
    {
        $validated = $request->validated();

        // The user decides whether completing also puts the lot on record:
        // only someone holding the disposition right releases material.
        $completed = $this->service->complete($inspection, $validated['notes'] ?? null, $request->user());

        return response()->json(['message' => 'Inspection completed', 'data' => $completed]);
    }

    /**
     * Apply a quality disposition to a (typically completed) inspection, so
     * mobile + tablet can release / scrap / quarantine without leaving the
     * app. Needs the disposition right (InspectionPolicy::disposition).
     */
    public function disposition(
        InspectionDispositionRequest $request,
        Inspection $inspection,
        DispositionService $disposition,
    ): JsonResponse {
        $validated = $request->validated();

        try {
            $updated = $disposition->apply(
                $inspection,
                $validated['disposition'],
                $validated['notes'] ?? null,
                $request->user(),
            );
        } catch (\DomainException|\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Disposition applied',
            'data' => $updated->fresh(['results', 'material', 'plan', 'inspector', 'issue']),
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Inspection::class);

        $request->validate([
            'days' => 'nullable|integer|min:1|max:365',
            'material_id' => 'nullable|integer|exists:materials,id',
        ]);

        $days = $request->integer('days', 30);
        $since = Carbon::now()->subDays(max(0, $days - 1))->startOfDay();

        $base = Inspection::where('started_at', '>=', $since);
        if ($request->filled('material_id')) {
            $base->where('material_id', $request->integer('material_id'));
        }

        $total = (clone $base)->whereIn('status', ['pass', 'fail', 'conditional_pass'])->count();
        $passed = (clone $base)->where('status', 'pass')->count();
        $failed = (clone $base)->where('status', 'fail')->count();
        $conditional = (clone $base)->where('status', 'conditional_pass')->count();
        $pending = (clone $base)->where('status', 'pending')->count();

        return response()->json([
            'data' => [
                'window_days' => $days,
                'total_completed' => $total,
                'pass_count' => $passed,
                'fail_count' => $failed,
                'conditional_pass_count' => $conditional,
                'pending_count' => $pending,
                'pass_rate' => $total > 0 ? round(($passed / $total) * 100, 1) : null,
            ],
        ]);
    }
}
