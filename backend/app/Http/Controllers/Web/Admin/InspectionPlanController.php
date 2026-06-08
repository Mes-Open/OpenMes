<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\InspectionPlanRequest;
use App\Models\InspectionPlan;
use App\Models\Material;
use App\Models\MaterialType;
use App\Services\Quality\InspectionPlanVersionService;
use Inertia\Inertia;

class InspectionPlanController extends Controller
{
    public function __construct(private InspectionPlanVersionService $versions) {}

    public function index()
    {
        return Inertia::render('admin/inspection-plans/Index', [
            'materialNames' => Material::pluck('name', 'id'),
            'materialTypeNames' => MaterialType::pluck('name', 'id'),
        ]);
    }

    private function formData(): array
    {
        return [
            'materials' => Material::orderBy('name')->get(['id', 'name']),
            'materialTypes' => MaterialType::orderBy('name')->get(['id', 'name']),
        ];
    }

    public function create()
    {
        return Inertia::render('admin/inspection-plans/Create', $this->formData());
    }

    /**
     * Create a brand-new plan as version 1 — a draft until published.
     */
    public function store(InspectionPlanRequest $request)
    {
        InspectionPlan::create([
            ...$request->payload(),
            'version' => 1,
            'published_at' => null,
            'root_id' => null,
            'is_active' => false,
        ]);

        return redirect()->route('admin.inspection-plans.index')
            ->with('success', __('Inspection plan created as a draft. Publish it to use it for inspections.'));
    }

    public function edit(InspectionPlan $inspectionPlan)
    {
        $scope = $inspectionPlan->material_id ? 'material'
            : ($inspectionPlan->material_type_id ? 'material_type' : 'generic');

        $history = $inspectionPlan->versionGroup()
            ->get(['id', 'version', 'published_at', 'is_active', 'updated_at'])
            ->map(fn ($v) => [
                'id' => $v->id,
                'version' => $v->version,
                'is_draft' => $v->published_at === null,
                'is_active' => (bool) $v->is_active,
                'published_at' => $v->published_at?->toIso8601String(),
                'updated_at' => $v->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/inspection-plans/Edit', array_merge($this->formData(), [
            'plan' => [
                ...$inspectionPlan->only('id', 'name', 'description', 'material_id', 'material_type_id', 'criteria', 'is_active', 'version'),
                'scope' => $scope,
                'is_draft' => $inspectionPlan->isDraft(),
                'published_at' => $inspectionPlan->published_at?->toIso8601String(),
            ],
            'history' => $history,
        ]));
    }

    /**
     * Draft → edit in place. Published → spawn the next draft version
     * (the published version stays immutable for reproducibility).
     */
    public function update(InspectionPlanRequest $request, InspectionPlan $inspectionPlan)
    {
        if ($inspectionPlan->isDraft()) {
            $inspectionPlan->update($request->payload());

            return redirect()->route('admin.inspection-plans.index')
                ->with('success', __('Draft updated.'));
        }

        $newVersion = $this->versions->createNewVersion($inspectionPlan, $request->payload());

        return redirect()->route('admin.inspection-plans.edit', $newVersion)
            ->with('success', __('Created version :v as a draft from the published plan.', ['v' => $newVersion->version]));
    }

    /**
     * Publish a draft — makes it the live version and retires the previous one.
     */
    public function publish(InspectionPlan $inspectionPlan)
    {
        if ($inspectionPlan->isPublished()) {
            return back()->with('error', __('This version is already published.'));
        }

        $this->versions->publish($inspectionPlan);

        return redirect()->route('admin.inspection-plans.index')
            ->with('success', __('Inspection plan version :v published.', ['v' => $inspectionPlan->version]));
    }

    public function destroy(InspectionPlan $inspectionPlan)
    {
        // Published versions that have been used by inspections must stay for
        // historical reproducibility.
        if ($inspectionPlan->isPublished() && $inspectionPlan->inspections()->exists()) {
            return back()->with('error', __('Cannot delete a published version that has recorded inspections.'));
        }

        $inspectionPlan->delete();

        return redirect()->route('admin.inspection-plans.index')
            ->with('success', __('Inspection plan deleted.'));
    }
}
