<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkerRequest;
use App\Http\Requests\UpdateWorkerRequest;
use App\Models\Worker;
use App\Services\CustomFieldService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkerController extends Controller
{
    /**
     * Display a listing of workers.
     */
    private function workforce(): \App\Extension\Contracts\WorkforceProvider
    {
        return app(\App\Extension\Contracts\WorkforceProvider::class);
    }

    public function index(Request $request)
    {
        return Inertia::render('admin/workers/Index', [
            'crewNames' => collect($this->workforce()->crewOptions())->pluck('name', 'id'),
            'wageGroupNames' => collect($this->workforce()->wageGroupOptions())->pluck('name', 'id'),
            'personnelClassNames' => collect($this->workforce()->personnelClassOptions())->pluck('name', 'id'),
        ]);
    }

    /**
     * Display the certifications page for a worker.
     */
    public function show(Worker $worker, CustomFieldService $cf)
    {
        // crew, wageGroup, personnelClass and skills are attached by an optional
        // module. Loading them unconditionally is a fatal on an installation
        // without it, so the page simply shows fewer sections instead.
        $worker->load(array_values(array_filter(
            ['crew', 'wageGroup', 'personnelClass', 'skills'],
            fn (string $r) => \App\Models\Worker::hasModuleRelation($r),
        )));
        $skills = $this->workforce()->skillOptions();

        $today = now()->startOfDay()->toDateString();
        $soonCut = now()->copy()->addDays(30)->startOfDay()->toDateString();

        // Certifications hang off the module's skills table; without it the
        // relation does not exist and the section is simply absent.
        $certifications = ! $worker->relationLoaded('skills') ? collect() : $worker->skills->map(function ($skill) use ($today, $soonCut) {
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
                'id' => $worker->id,
                'code' => $worker->code,
                'name' => $worker->name,
                'email' => $worker->email,
                'is_active' => $worker->is_active,
                'crew' => $worker->crew ? ['name' => $worker->crew->name] : null,
                'wageGroup' => $worker->wageGroup ? ['name' => $worker->wageGroup->name] : null,
                'personnelClass' => $worker->personnelClass ? ['name' => $worker->personnelClass->name] : null,
                'custom_fields' => $worker->custom_fields,
            ],
            'certifications' => $certifications,
            'skills' => $skills,
            'levels' => $this->workforce()->certificationLevels(),
            'customFields' => $cf->clientConfig('worker'),
        ]);
    }

    /**
     * Show the form for creating a new worker.
     */
    public function create(CustomFieldService $cf)
    {
        return Inertia::render('admin/workers/Create', [
            'crews' => $this->workforce()->crewOptions(),
            'wageGroups' => $this->workforce()->wageGroupOptions(),
            'personnelClasses' => $this->workforce()->personnelClassOptions(),
            'skills' => $this->workforce()->skillOptions(),
            'customFields' => $cf->clientConfig('worker'),
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

        // The skills pivot belongs to the optional workforce module; without it
        // there is no relation to sync and nothing was submitted anyway.
        if (Worker::hasModuleRelation('skills')) {
            $worker->skills()->sync(
                collect($request->input('skills', []))->mapWithKeys(fn ($s) => [$s['id'] => ['level' => $s['level'] ?? 1]])
            );
        }

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
                'pay_type' => $worker->pay_type,
                'pay_rate' => $worker->pay_rate,
                'pay_currency' => $worker->pay_currency,
                'is_active' => $worker->is_active,
                'is_logistics' => $worker->is_logistics,
                'custom_fields' => $worker->custom_fields,
                'skills' => $worker->skills->map(fn ($s) => [
                    'id' => $s->id,
                    'level' => $s->pivot->level ?? 1,
                ]),
            ],
            'crews' => $this->workforce()->crewOptions(),
            'wageGroups' => $this->workforce()->wageGroupOptions(),
            'personnelClasses' => $this->workforce()->personnelClassOptions(),
            'skills' => $this->workforce()->skillOptions(),
            'customFields' => $cf->clientConfig('worker'),
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
        if (Worker::hasModuleRelation('skills')) {
            $worker->skills()->syncWithoutDetaching(
                collect($request->input('skills', []))->mapWithKeys(fn ($s) => [$s['id'] => ['level' => $s['level'] ?? 1]])
            );
        }

        return redirect()->route('admin.workers.index')
            ->with('success', 'Worker updated successfully.');
    }

    /**
     * Remove the specified worker.
     */
    public function destroy(Worker $worker)
    {
        if (Worker::hasModuleRelation('skills')) {
            $worker->skills()->detach();
        }

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
}
