<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkstationRequest;
use App\Http\Requests\UpdateWorkstationRequest;
use App\Models\Line;
use App\Models\Worker;
use App\Models\Workstation;
use App\Services\CustomFieldService;
use App\Services\Production\OperatorScreens;
use Inertia\Inertia;

class WorkstationManagementController extends Controller
{
    /**
     * Display workstations for a specific line
     */
    public function index(Line $line, CustomFieldService $cf, OperatorScreens $screens)
    {
        $workstations = $line->workstations()
            ->withCount(['templateSteps', 'workers'])
            ->orderBy('code')
            ->get();
        $derived = $screens->derivedFor($workstations);

        return Inertia::render('admin/workstations/Index', [
            'workers' => $this->workerOptions(),
            'customFields' => $cf->clientConfig('workstation'),
            'line' => $line->only('id', 'name', 'code'),
            'workstations' => $workstations->map(fn ($ws) => array_merge(
                $ws->only('id', 'code', 'name', 'workstation_type', 'operator_screens', 'unit_label_actions', 'is_active', 'custom_fields'),
                [
                    'template_steps_count' => $ws->template_steps_count,
                    'workers_count' => $ws->workers_count,
                    // What the routing gives the bench, shown beside the manual choice.
                    'derived_screens' => $derived[$ws->id],
                ]
            ))->values(),
        ]);
    }

    /**
     * Show the form for creating a new workstation
     */
    public function create(Line $line, CustomFieldService $cf)
    {
        return Inertia::render('admin/workstations/Create', [
            'line' => $line->only('id', 'name', 'code'),
            'customFields' => $cf->clientConfig('workstation'),
        ]);
    }

    /**
     * Store a newly created workstation
     */
    public function store(StoreWorkstationRequest $request, Line $line, CustomFieldService $cf)
    {
        $validated = $request->validated();
        // An empty list means "follow the routing".
        if (array_key_exists('operator_screens', $validated)) {
            $validated['operator_screens'] = $validated['operator_screens'] ?: null;
        }
        if (array_key_exists('unit_label_actions', $validated)) {
            $validated['unit_label_actions'] = $validated['unit_label_actions'] ?: null;
        }

        $validated['line_id'] = $line->id;
        $validated['is_active'] = $request->boolean('is_active', true);
        unset($validated['custom_field_files']);
        if ($cf->touched($request)) {
            $validated['custom_fields'] = $cf->fromRequest($request, 'workstation') ?: null;
        }

        Workstation::create($validated);

        return redirect()->route('admin.lines.workstations.index', $line)
            ->with('success', 'Workstation created successfully.');
    }

    /**
     * Show the form for editing a workstation
     */
    public function edit(Line $line, Workstation $workstation, CustomFieldService $cf, OperatorScreens $screens)
    {
        if ($workstation->line_id !== $line->id) {
            abort(404);
        }

        return Inertia::render('admin/workstations/Edit', [
            'line' => $line->only('id', 'name', 'code'),
            'workstation' => [...$workstation->only('id', 'code', 'name', 'workstation_type', 'operator_screens', 'unit_label_actions', 'is_active', 'custom_fields'), 'derived_screens' => $screens->derived($workstation)],
            'customFields' => $cf->clientConfig('workstation'),
            'workers' => $this->workerOptions(),
        ]);
    }

    private function workerOptions()
    {
        $workers = Worker::active()->orderBy('name')->with('workstation')
            ->when(Worker::hasModuleRelation('crew'), fn ($query) => $query->with('crew'))
            ->get();

        return $workers->map(fn ($w) => [
            'id' => $w->id,
            'name' => $w->name,
            'code' => $w->code,
            'workstation_id' => $w->workstation_id,
            'workstation_name' => $w->workstation?->name,
            'crew_name' => Worker::hasModuleRelation('crew') ? $w->crew?->name : null,
        ])->values();
    }

    /**
     * Update the specified workstation
     */
    public function update(UpdateWorkstationRequest $request, Line $line, Workstation $workstation, CustomFieldService $cf)
    {
        // Ensure workstation belongs to this line
        if ($workstation->line_id !== $line->id) {
            abort(404);
        }

        $validated = $request->validated();
        // An empty list means "follow the routing"; a request without the key
        // (a script editing the name) leaves the bench's choice alone.
        if (array_key_exists('operator_screens', $validated)) {
            $validated['operator_screens'] = $validated['operator_screens'] ?: null;
        }
        if (array_key_exists('unit_label_actions', $validated)) {
            $validated['unit_label_actions'] = $validated['unit_label_actions'] ?: null;
        }

        $validated['is_active'] = $request->boolean('is_active');
        unset($validated['custom_field_files']);
        if ($cf->touched($request)) {
            $validated['custom_fields'] = $cf->fromRequest($request, 'workstation', $workstation->custom_fields) ?: null;
        }

        $workstation->update($validated);

        // Update worker assignments
        $workerIds = $request->input('worker_ids', []);
        // Un-assign workers no longer selected (only those currently at THIS workstation)
        Worker::where('workstation_id', $workstation->id)
            ->whereNotIn('id', $workerIds)
            ->update(['workstation_id' => null]);
        // Assign selected workers (may move them from another workstation)
        if (! empty($workerIds)) {
            Worker::whereIn('id', $workerIds)->update(['workstation_id' => $workstation->id]);
        }

        return redirect()->route('admin.lines.workstations.index', $line)
            ->with('success', 'Workstation updated successfully.');
    }

    /**
     * Remove the specified workstation
     */
    public function destroy(Line $line, Workstation $workstation)
    {
        // Ensure workstation belongs to this line
        if ($workstation->line_id !== $line->id) {
            abort(404);
        }

        // Check if workstation has template steps
        if ($workstation->templateSteps()->count() > 0) {
            return redirect()->route('admin.lines.workstations.index', $line)
                ->with('error', 'Cannot delete workstation with existing template steps. Deactivate it instead.');
        }

        try {
            $workstation->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->route('admin.lines.workstations.index', $line)
                ->with('error', 'Cannot delete: this workstation is still referenced elsewhere. Deactivate it instead.');
        }

        return redirect()->route('admin.lines.workstations.index', $line)
            ->with('success', 'Workstation deleted successfully.');
    }

    /**
     * Toggle workstation active status
     */
    public function toggleActive(Line $line, Workstation $workstation)
    {
        // Ensure workstation belongs to this line
        if ($workstation->line_id !== $line->id) {
            abort(404);
        }

        $workstation->update(['is_active' => ! $workstation->is_active]);

        $status = $workstation->is_active ? 'activated' : 'deactivated';

        return redirect()->route('admin.lines.workstations.index', $line)
            ->with('success', "Workstation {$status} successfully.");
    }
}
