<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkerRequest;
use App\Http\Requests\UpdateWorkerRequest;
use App\Models\Crew;
use App\Models\PersonnelClass;
use App\Models\Skill;
use App\Models\WageGroup;
use App\Models\Worker;
use App\Services\CustomFieldService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkerController extends Controller
{
    /**
     * Display a listing of workers.
     */
    public function index(Request $request)
    {
        return Inertia::render('admin/workers/Index', [
            'crewNames' => Crew::pluck('name', 'id'),
            'wageGroupNames' => WageGroup::pluck('name', 'id'),
            'personnelClassNames' => PersonnelClass::pluck('name', 'id'),
        ]);
    }

    /**
     * Display the certifications page for a worker.
     */
    public function show(Worker $worker, CustomFieldService $cf)
    {
        $worker->load(['crew', 'wageGroup', 'personnelClass', 'skills']);
        $skills = Skill::orderBy('name')->get();

        $today = now()->startOfDay()->toDateString();
        $soonCut = now()->copy()->addDays(30)->startOfDay()->toDateString();

        $certifications = $worker->skills->map(function ($skill) use ($today, $soonCut) {
            $until = $skill->pivot->certified_until;
            $status = 'valid';
            if ($until) {
                if ($until < $today) {
                    $status = 'expired';
                } elseif ($until <= $soonCut) {
                    $status = 'expiring';
                }
            }

            return [
                'skill_id' => $skill->id,
                'skill_name' => $skill->name,
                'skill_code' => $skill->code,
                'cert_level' => $skill->pivot->cert_level ?? 'operator',
                'certified_from' => $skill->pivot->certified_from,
                'certified_until' => $skill->pivot->certified_until,
                'cert_notes' => $skill->pivot->cert_notes,
                'status' => $status,
            ];
        });

        return Inertia::render('admin/workers/Show', [
            'worker' => [
                'id'               => $worker->id,
                'code'             => $worker->code,
                'name'             => $worker->name,
                'email'            => $worker->email,
                'is_active'        => $worker->is_active,
                'crew'             => $worker->crew ? ['name' => $worker->crew->name] : null,
                'wageGroup'        => $worker->wageGroup ? ['name' => $worker->wageGroup->name] : null,
                'personnelClass'   => $worker->personnelClass ? ['name' => $worker->personnelClass->name] : null,
                'custom_fields'    => $worker->custom_fields,
            ],
            'certifications' => $certifications,
            'skills'         => $skills->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'code' => $s->code]),
            'levels'         => PersonnelClass::LEVELS,
            'customFields'   => $cf->clientConfig('worker'),
        ]);
    }

    /**
     * Show the form for creating a new worker.
     */
    public function create(CustomFieldService $cf)
    {
        return Inertia::render('admin/workers/Create', [
            'crews' => Crew::active()->orderBy('name')->get(['id', 'name']),
            'wageGroups' => WageGroup::active()->orderBy('name')->get(['id', 'name']),
            'personnelClasses' => PersonnelClass::active()->orderBy('name')->get(['id', 'name']),
            'skills'           => Skill::orderBy('name')->get(['id', 'name']),
            'customFields'     => $cf->clientConfig('worker'),
        ]);
    }

    /**
     * Store a newly created worker.
     */
    public function store(StoreWorkerRequest $request, CustomFieldService $cf)
    {
        // StoreWorkerRequest validates the worker fields (incl. pay_type/pay_rate/
        // pay_currency from develop) AND the custom fields (MergesCustomFieldRules).
        $validated = $request->validated();

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['is_logistics'] = $request->boolean('is_logistics');
        unset($validated['custom_field_files']);
        if ($cf->touched($request)) {
            $validated['custom_fields'] = $cf->fromRequest($request, 'worker') ?: null;
        }

        $worker = Worker::create($validated);

        $worker->skills()->sync(
            collect($request->input('skills', []))->mapWithKeys(fn ($s) => [$s['id'] => ['level' => $s['level'] ?? 1]])
        );

        return redirect()->route('admin.workers.index')
            ->with('success', 'Worker created successfully.');
    }

    /**
     * Show the form for editing a worker.
     */
    public function edit(Worker $worker, CustomFieldService $cf)
    {
        $worker->load('skills');

        return Inertia::render('admin/workers/Edit', [
            'worker' => [
                'id' => $worker->id,
                'code' => $worker->code,
                'name' => $worker->name,
                'email' => $worker->email,
                'phone' => $worker->phone,
                'crew_id' => $worker->crew_id,
                'wage_group_id' => $worker->wage_group_id,
                'personnel_class_id' => $worker->personnel_class_id,
                'pay_type'           => $worker->pay_type,
                'pay_rate'           => $worker->pay_rate,
                'pay_currency'       => $worker->pay_currency,
                'is_active'          => $worker->is_active,
                'is_logistics'       => $worker->is_logistics,
                'custom_fields'      => $worker->custom_fields,
                'skills'             => $worker->skills->map(fn ($s) => [
                    'id'    => $s->id,
                    'level' => $s->pivot->level ?? 1,
                ]),
            ],
            'crews' => Crew::active()->orderBy('name')->get(['id', 'name']),
            'wageGroups' => WageGroup::active()->orderBy('name')->get(['id', 'name']),
            'personnelClasses' => PersonnelClass::active()->orderBy('name')->get(['id', 'name']),
            'skills'           => Skill::orderBy('name')->get(['id', 'name']),
            'customFields'     => $cf->clientConfig('worker'),
        ]);
    }

    /**
     * Update the specified worker.
     */
    public function update(UpdateWorkerRequest $request, Worker $worker, CustomFieldService $cf)
    {
        $validated = $request->validated();

        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_logistics'] = $request->boolean('is_logistics');
        unset($validated['custom_field_files']);
        if ($cf->touched($request)) {
            $validated['custom_fields'] = $cf->fromRequest($request, 'worker', $worker->custom_fields) ?: null;
        }

        $worker->update($validated);

        // Preserve certification metadata: update the legacy proficiency level
        // without detaching existing rows (which would wipe cert_level etc.).
        $worker->skills()->syncWithoutDetaching(
            collect($request->input('skills', []))->mapWithKeys(fn ($s) => [$s['id'] => ['level' => $s['level'] ?? 1]])
        );

        return redirect()->route('admin.workers.index')
            ->with('success', 'Worker updated successfully.');
    }

    /**
     * Remove the specified worker.
     */
    public function destroy(Worker $worker)
    {
        $worker->skills()->detach();
        $worker->delete();

        return redirect()->route('admin.workers.index')
            ->with('success', 'Worker deleted successfully.');
    }

    /**
     * Toggle worker active status.
     */
    public function toggleActive(Worker $worker)
    {
        $worker->update(['is_active' => ! $worker->is_active]);

        $status = $worker->is_active ? 'activated' : 'deactivated';

        return redirect()->route('admin.workers.index')
            ->with('success', "Worker {$status} successfully.");
    }

    /**
     * Attach (or update) a certification on the worker's skill pivot.
     *
     * Idempotent — using syncWithoutDetaching, calling with the same skill_id
     * refreshes the certification window rather than creating duplicates.
     */
    public function attachSkill(Request $request, Worker $worker)
    {
        $validated = $request->validate([
            'skill_id' => 'required|exists:skills,id',
            'cert_level' => 'required|in:trainee,operator,expert,trainer',
            'certified_from' => 'nullable|date',
            'certified_until' => 'nullable|date|after_or_equal:certified_from',
            'cert_notes' => 'nullable|string|max:1000',
        ]);

        $worker->skills()->syncWithoutDetaching([
            $validated['skill_id'] => [
                'cert_level' => $validated['cert_level'],
                'certified_from' => $validated['certified_from'] ?? now()->toDateString(),
                'certified_until' => $validated['certified_until'] ?? null,
                'certified_by_id' => $request->user()?->id,
                'cert_notes' => $validated['cert_notes'] ?? null,
            ],
        ]);

        return back()->with('success', __('Certification recorded.'));
    }

    /**
     * Detach a certification from the worker.
     */
    public function detachSkill(Worker $worker, Skill $skill)
    {
        $worker->skills()->detach($skill->id);

        return back()->with('success', __('Certification removed.'));
    }
}
