<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\InspectionPlan;
use App\Models\Material;
use App\Models\MaterialType;
use Illuminate\Http\Request;

class InspectionPlanController extends Controller
{
    public function index()
    {
        $plans = InspectionPlan::with(['material', 'materialType'])->orderBy('name')->get();

        return view('admin.inspection-plans.index', compact('plans'));
    }

    public function create()
    {
        return view('admin.inspection-plans.form', [
            'plan' => new InspectionPlan(['criteria' => []]),
            'materials' => Material::orderBy('name')->get(),
            'materialTypes' => MaterialType::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);
        InspectionPlan::create($validated);

        return redirect()->route('admin.inspection-plans.index')->with('success', __('Inspection plan created.'));
    }

    public function edit(InspectionPlan $inspectionPlan)
    {
        return view('admin.inspection-plans.form', [
            'plan' => $inspectionPlan,
            'materials' => Material::orderBy('name')->get(),
            'materialTypes' => MaterialType::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, InspectionPlan $inspectionPlan)
    {
        $inspectionPlan->update($this->validated($request));

        return redirect()->route('admin.inspection-plans.index')->with('success', __('Inspection plan updated.'));
    }

    public function destroy(InspectionPlan $inspectionPlan)
    {
        $inspectionPlan->delete();

        return redirect()->route('admin.inspection-plans.index')->with('success', __('Inspection plan deleted.'));
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'scope' => 'required|string|in:material,material_type,generic',
            'material_id' => 'nullable|integer|exists:materials,id',
            'material_type_id' => 'nullable|integer|exists:material_types,id',
            'criteria' => 'required|array|min:1',
            'criteria.*.name' => 'required|string|max:150',
            'criteria.*.type' => 'required|string|in:visual,measurement,functional,pass_fail',
            'criteria.*.required' => 'nullable|boolean',
            'criteria.*.unit' => 'nullable|string|max:30',
            'criteria.*.spec_min' => 'nullable|numeric',
            'criteria.*.spec_max' => 'nullable|numeric',
            'is_active' => 'nullable|boolean',
        ]);

        // Enforce scope coherence based on the radio choice.
        $scope = $validated['scope'];
        if ($scope === 'material') {
            abort_unless($validated['material_id'] ?? null, 422, 'Pick a material when scope = material.');
            $validated['material_type_id'] = null;
        } elseif ($scope === 'material_type') {
            abort_unless($validated['material_type_id'] ?? null, 422, 'Pick a material type when scope = material_type.');
            $validated['material_id'] = null;
        } else {
            $validated['material_id'] = null;
            $validated['material_type_id'] = null;
        }

        unset($validated['scope']);
        $validated['is_active'] = $validated['is_active'] ?? false;

        // Normalize criteria booleans (HTML form sends nothing when unchecked).
        $validated['criteria'] = array_map(function ($c) {
            $c['required'] = isset($c['required']) ? (bool) $c['required'] : false;

            return $c;
        }, $validated['criteria']);

        return $validated;
    }
}
