<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\PersonnelClass;
use App\Models\Skill;
use App\Models\Worker;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PersonnelClassController extends Controller
{
    /**
     * Display a listing of personnel classes.
     */
    public function index()
    {
        $counts = PersonnelClass::withCount('workers')
            ->get(['id'])
            ->mapWithKeys(fn ($p) => [$p->id => $p->workers_count]);

        return Inertia::render('admin/personnel-classes/Index', [
            'counts' => $counts,
            'skillNames' => Skill::pluck('name', 'id'),
        ]);
    }

    /**
     * Display a single personnel class with workers + required skills.
     */
    public function show(PersonnelClass $personnelClass)
    {
        $workers        = $personnelClass->workers()->orderBy('name')->get();
        $requiredSkills = $personnelClass->requiredSkills();
        $reqLevels      = $personnelClass->default_required_cert_level ?? [];

        return Inertia::render('admin/personnel-classes/Show', [
            'personnelClass' => [
                'id'          => $personnelClass->id,
                'code'        => $personnelClass->code,
                'name'        => $personnelClass->name,
                'description' => $personnelClass->description,
                'is_active'   => $personnelClass->is_active,
                'created_at'  => $personnelClass->created_at?->format('d M Y'),
                'updated_at'  => $personnelClass->updated_at?->diffForHumans(),
            ],
            'workers' => $workers->map(fn ($w) => [
                'id'        => $w->id,
                'code'      => $w->code,
                'name'      => $w->name,
                'qualified' => $personnelClass->workerMeetsRequirements($w),
            ]),
            'requiredSkills' => $requiredSkills->map(fn ($s) => [
                'id'        => $s->id,
                'name'      => $s->name,
                'code'      => $s->code,
                'min_level' => $reqLevels[$s->id] ?? 'operator',
            ]),
        ]);
    }

    /**
     * Show the form for creating a new personnel class.
     */
    public function create()
    {
        return Inertia::render('admin/personnel-classes/Create', [
            'skills' => Skill::orderBy('name')->get(['id', 'name']),
            'levels' => PersonnelClass::LEVELS,
        ]);
    }

    /**
     * Store a newly created personnel class.
     */
    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);
        $validated['is_active'] = $request->boolean('is_active', true);

        PersonnelClass::create($validated);

        return redirect()->route('admin.personnel-classes.index')
            ->with('success', __('Personnel class created successfully.'));
    }

    /**
     * Show the form for editing the specified personnel class.
     */
    public function edit(PersonnelClass $personnelClass)
    {
        return Inertia::render('admin/personnel-classes/Edit', [
            'personnelClass' => $personnelClass->only(
                'id', 'code', 'name', 'description', 'required_skill_ids', 'default_required_cert_level', 'is_active'
            ),
            'skills' => Skill::orderBy('name')->get(['id', 'name']),
            'levels' => PersonnelClass::LEVELS,
        ]);
    }

    /**
     * Update the specified personnel class.
     */
    public function update(Request $request, PersonnelClass $personnelClass)
    {
        $validated = $this->validatePayload($request, $personnelClass);
        $validated['is_active'] = $request->boolean('is_active', false);

        $personnelClass->update($validated);

        return redirect()->route('admin.personnel-classes.index')
            ->with('success', __('Personnel class updated successfully.'));
    }

    /**
     * Remove the specified personnel class.
     *
     * Guards against silent unlink — if any worker is still in the class,
     * require explicit `force=1` to detach them (sets workers.personnel_class_id
     * to NULL) before deletion.
     */
    public function destroy(Request $request, PersonnelClass $personnelClass)
    {
        $workerCount = $personnelClass->workers()->count();

        if ($workerCount > 0 && ! $request->boolean('force')) {
            return back()
                ->with('error', __(
                    'Cannot delete — :n worker(s) are assigned. Re-submit with force=1 to detach them.',
                    ['n' => $workerCount]
                ))
                ->setStatusCode(422);
        }

        if ($workerCount > 0) {
            Worker::where('personnel_class_id', $personnelClass->id)
                ->update(['personnel_class_id' => null]);
        }

        $personnelClass->delete();

        return redirect()->route('admin.personnel-classes.index')
            ->with('success', __('Personnel class deleted successfully.'));
    }

    // ── Shared validation + form data ─────────────────────────────────────

    private function formData(): array
    {
        return [
            'skills' => Skill::query()->orderBy('name')->get(),
            'levels' => PersonnelClass::LEVELS,
        ];
    }

    private function validatePayload(Request $request, ?PersonnelClass $personnelClass = null): array
    {
        $tenantId         = $request->user()?->tenant_id;
        $personnelClassId = $personnelClass?->id;

        $rules = [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('personnel_classes', 'code')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId))
                    ->ignore($personnelClassId),
            ],
            'name'                          => ['required', 'string', 'max:255'],
            'description'                   => ['nullable', 'string', 'max:4000'],
            'required_skill_ids'            => ['nullable', 'array'],
            'required_skill_ids.*'          => ['integer', 'exists:skills,id'],
            'default_required_cert_level'   => ['nullable', 'array'],
            'default_required_cert_level.*' => [Rule::in(PersonnelClass::LEVELS)],
        ];

        $validated = $request->validate($rules);

        if (empty($validated['required_skill_ids'])) {
            $validated['required_skill_ids']          = null;
            $validated['default_required_cert_level'] = null;
        } else {
            // Drop level entries whose skill is not in the required list.
            $allowed = array_flip(array_map('intval', $validated['required_skill_ids']));
            $levels  = $validated['default_required_cert_level'] ?? [];
            $clean   = [];
            foreach ($levels as $skillId => $level) {
                if (isset($allowed[(int) $skillId])) {
                    $clean[(int) $skillId] = $level;
                }
            }
            $validated['default_required_cert_level'] = $clean ?: null;
        }

        return $validated;
    }
}
