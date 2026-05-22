<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProcessSegment;
use App\Models\Skill;
use App\Models\WorkstationType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProcessSegmentController extends Controller
{
    /**
     * Display a listing of process segments.
     */
    public function index(Request $request)
    {
        $query = ProcessSegment::query()
            ->with(['workstationType'])
            ->withCount('templateSteps')
            ->orderBy('segment_type')
            ->orderBy('code');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($type = $request->input('segment_type')) {
            $query->where('segment_type', $type);
        }

        if ($wsTypeId = $request->input('workstation_type_id')) {
            $query->where('workstation_type_id', $wsTypeId);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->input('is_active') === '1');
        }

        $segments         = $query->paginate(25)->withQueryString();
        $workstationTypes = WorkstationType::query()->active()->orderBy('name')->get();

        return view('admin.process-segments.index', compact('segments', 'workstationTypes'));
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

        return view('admin.process-segments.show', [
            'segment'        => $processSegment,
            'usingSteps'     => $usingSteps,
            'requiredSkills' => $requiredSkills,
        ]);
    }

    /**
     * Show the form for creating a new process segment.
     */
    public function create()
    {
        return view('admin.process-segments.create', $this->formData());
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
        return view('admin.process-segments.edit', array_merge(
            $this->formData(),
            ['segment' => $processSegment]
        ));
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

    private function formData(): array
    {
        return [
            'workstationTypes' => WorkstationType::query()->active()->orderBy('name')->get(),
            'skills'           => Skill::query()->orderBy('name')->get(),
            'segmentTypes'     => ProcessSegment::TYPES,
        ];
    }

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
