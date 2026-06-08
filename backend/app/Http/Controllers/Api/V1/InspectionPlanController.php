<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\InspectionPlan;
use App\Services\Quality\InspectionPlanVersionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InspectionPlanController extends Controller
{
    public function __construct(private InspectionPlanVersionService $versions) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'material_id' => 'nullable|integer|exists:materials,id',
            'material_type_id' => 'nullable|integer|exists:material_types,id',
            'active' => 'nullable|boolean',
        ]);

        $query = InspectionPlan::with(['material', 'materialType']);

        if ($request->filled('material_id')) {
            $query->where('material_id', $request->integer('material_id'));
        }
        if ($request->filled('material_type_id')) {
            $query->where('material_type_id', $request->integer('material_type_id'));
        }
        if ($request->boolean('active')) {
            $query->active();
        }

        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    public function show(InspectionPlan $inspectionPlan): JsonResponse
    {
        return response()->json(['data' => $inspectionPlan->load(['material', 'materialType'])]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatedPayload($request);

        $plan = InspectionPlan::create([
            ...$validated,
            'version' => 1,
            'published_at' => null,
            'root_id' => null,
            'is_active' => false,
        ]);

        return response()->json(['message' => __('Inspection plan created'), 'data' => $plan], 201);
    }

    /**
     * Draft → update in place. Published → create the next draft version.
     */
    public function update(Request $request, InspectionPlan $inspectionPlan): JsonResponse
    {
        $validated = $this->validatedPayload($request, $inspectionPlan->id);

        if ($inspectionPlan->isDraft()) {
            $inspectionPlan->update($validated);

            return response()->json(['message' => __('Inspection plan updated'), 'data' => $inspectionPlan->fresh()]);
        }

        $newVersion = $this->versions->createNewVersion($inspectionPlan, $validated);

        return response()->json([
            'message' => __('Created version :v as a draft from the published plan.', ['v' => $newVersion->version]),
            'data' => $newVersion,
        ], 201);
    }

    public function publish(InspectionPlan $inspectionPlan): JsonResponse
    {
        $this->versions->publish($inspectionPlan);

        return response()->json(['message' => __('Inspection plan published'), 'data' => $inspectionPlan->fresh()]);
    }

    public function destroy(InspectionPlan $inspectionPlan): JsonResponse
    {
        if ($inspectionPlan->isPublished() && $inspectionPlan->inspections()->exists()) {
            return response()->json(['message' => __('Cannot delete a published version that has recorded inspections.')], 422);
        }

        $inspectionPlan->delete();

        return response()->json(['message' => __('Inspection plan deleted')]);
    }

    private function validatedPayload(Request $request, ?int $id = null): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'material_id' => 'nullable|integer|exists:materials,id',
            'material_type_id' => 'nullable|integer|exists:material_types,id',
            'criteria' => 'required|array|min:1',
            'criteria.*.name' => 'required|string|max:150',
            'criteria.*.type' => 'required|string|in:visual,measurement,functional,pass_fail',
            'criteria.*.required' => 'nullable|boolean',
            'criteria.*.unit' => 'nullable|string|max:30',
            'criteria.*.spec_min' => 'nullable|numeric',
            'criteria.*.spec_max' => 'nullable|numeric',
        ]);

        // Exactly-one rule: either tie to a material, a material_type, or be generic.
        // Both set is invalid (ambiguous scope).
        if (! empty($validated['material_id']) && ! empty($validated['material_type_id'])) {
            abort(422, __('A plan can target either a material OR a material type, not both.'));
        }

        // Force measurement specs to be coherent if both provided.
        foreach ($validated['criteria'] as $i => $c) {
            if (($c['type'] ?? null) === 'measurement'
                && isset($c['spec_min'], $c['spec_max'])
                && $c['spec_min'] > $c['spec_max']) {
                abort(422, __('Criterion #:index: spec_min cannot exceed spec_max.', ['index' => $i]));
            }
        }

        return $validated;
    }
}
