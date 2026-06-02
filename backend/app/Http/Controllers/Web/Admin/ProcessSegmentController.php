<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProcessSegment;
use App\Models\Skill;
use App\Models\WorkstationType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProcessSegmentController extends Controller
{
    /**
     * Display a listing of process segments.
     */
    public function index(Request $request)
    {
        $workstationTypeNames = WorkstationType::pluck('name', 'id');

        return Inertia::render('admin/process-segments/Index', [
            'workstationTypeNames' => $workstationTypeNames,
        ]);
    }

    /**
     * Display a single process segment with cross-reference.
     */
    public function show(ProcessSegment $processSegment)
    {
        $processSegment->load(['workstationType', 'createdBy']);

        $usingSteps = $processSegment->templateSteps()
            ->with(['processTemplate.productType', 'workstation'])
            ->orderBy('step_number')
            ->get();

        $requiredSkills = $processSegment->requiredSkills();

        return Inertia::render('admin/process-segments/Show', [
            'segment' => [
                'id'                          => $processSegment->id,
                'code'                        => $processSegment->code,
                'name'                        => $processSegment->name,
                'description'                 => $processSegment->description,
                'segment_type'                => $processSegment->segment_type,
                'is_active'                   => $processSegment->is_active,
                'estimated_duration_minutes'  => $processSegment->estimated_duration_minutes,
                'required_operators'          => $processSegment->required_operators,
                'standard_instruction'        => $processSegment->standard_instruction,
                'parameters'                  => $processSegment->parameters,
                'workstation_type_name'       => $processSegment->workstationType?->name,
                'created_at'                  => $processSegment->created_at?->format('d M Y'),
                'updated_at'                  => $processSegment->updated_at?->diffForHumans(),
                'created_by_name'             => $processSegment->createdBy?->name,
            ],
            'usingSteps' => $usingSteps->map(fn ($step) => [
                'id'               => $step->id,
                'step_number'      => $step->step_number,
                'name'             => $step->name,
                'workstation_name' => $step->workstation?->name,
                'template_name'    => $step->processTemplate?->name,
                'product_type_name'=> $step->processTemplate?->productType?->name,
                'template_url'     => ($step->processTemplate && $step->processTemplate->productType)
                    ? "/admin/product-types/{$step->processTemplate->productType->id}/process-templates/{$step->processTemplate->id}"
                    : null,
            ]),
            'requiredSkills' => $requiredSkills->map(fn ($s) => [
                'id'   => $s->id,
                'code' => $s->code,
                'name' => $s->name,
            ]),
        ]);
    }

    /**
     * Show the form for creating a new process segment.
     */
    public function create()
    {
        return Inertia::render('admin/process-segments/Create', [
            'workstationTypes' => WorkstationType::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'skills'           => Skill::orderBy('name')->get(['id', 'name']),
            'segmentTypes'     => ProcessSegment::TYPES,
        ]);
    }

    /**
     * Store a newly created process segment.
     */
    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);
        $validated['created_by_id'] = $request->user()?->id;
        $validated['is_active']     = $request->boolean('is_active', true);

        ProcessSegment::create($validated);

        return redirect()->route('admin.process-segments.index')
            ->with('success', __('Process segment created successfully.'));
    }

    /**
     * Show the form for editing the specified process segment.
     */
    public function edit(ProcessSegment $processSegment)
    {
        return Inertia::render('admin/process-segments/Edit', [
            'segment' => $processSegment->only(
                'id', 'code', 'name', 'description', 'segment_type', 'workstation_type_id',
                'estimated_duration_minutes', 'required_operators', 'standard_instruction', 'required_skill_ids'
            ),
            'parameters_raw'   => $processSegment->parameters ? json_encode($processSegment->parameters, JSON_PRETTY_PRINT) : '',
            'workstationTypes' => WorkstationType::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'skills'           => Skill::orderBy('name')->get(['id', 'name']),
            'segmentTypes'     => ProcessSegment::TYPES,
        ]);
    }

    /**
     * Update the specified process segment.
     */
    public function update(Request $request, ProcessSegment $processSegment)
    {
        $validated = $this->validatePayload($request, $processSegment);
        $validated['is_active'] = $request->boolean('is_active', false);

        $processSegment->update($validated);

        return redirect()->route('admin.process-segments.show', $processSegment)
            ->with('success', __('Process segment updated successfully.'));
    }

    /**
     * Remove the specified process segment.
     *
     * Guards against deletion when any TemplateStep still references it — a
     * silent FK nullOnDelete would erase the link from work-order recipes.
     */
    public function destroy(ProcessSegment $processSegment)
    {
        $usage = $processSegment->templateSteps()->count();

        if ($usage > 0) {
            return redirect()->route('admin.process-segments.index')
                ->with('error', __('Cannot delete — used by :n template step(s).', ['n' => $usage]));
        }

        $processSegment->delete();

        return redirect()->route('admin.process-segments.index')
            ->with('success', __('Process segment deleted successfully.'));
    }

    // ── Shared validation + form data ─────────────────────────────────────

    private function validatePayload(Request $request, ?ProcessSegment $segment = null): array
    {
        $tenantId = $request->user()?->tenant_id;
        $segmentId = $segment?->id;

        $rules = [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('process_segments', 'code')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId))
                    ->ignore($segmentId),
            ],
            'name'                       => ['required', 'string', 'max:255'],
            'description'                => ['nullable', 'string', 'max:4000'],
            'segment_type'               => ['required', Rule::in(ProcessSegment::TYPES)],
            'workstation_type_id'        => ['nullable', 'integer', 'exists:workstation_types,id'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'required_operators'         => ['required', 'integer', 'min:1', 'max:50'],
            'standard_instruction'       => ['nullable', 'string', 'max:8000'],
            'required_skill_ids'         => ['nullable', 'array'],
            'required_skill_ids.*'       => ['integer', 'exists:skills,id'],
            'parameters_raw'             => ['nullable', 'string', 'max:8000'],
        ];

        $validated = $request->validate($rules);

        // Parse JSON parameters from the textarea.
        $raw = trim((string) ($validated['parameters_raw'] ?? ''));
        unset($validated['parameters_raw']);
        if ($raw === '') {
            $validated['parameters'] = null;
        } else {
            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                abort(redirect()->back()->withInput()->withErrors([
                    'parameters_raw' => __('Parameters must be a valid JSON object.'),
                ]));
            }
            $validated['parameters'] = $decoded;
        }

        // Normalise empty skill list to null for storage.
        if (empty($validated['required_skill_ids'])) {
            $validated['required_skill_ids'] = null;
        }

        return $validated;
    }
}
