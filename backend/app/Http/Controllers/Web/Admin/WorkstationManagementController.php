<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Models\Worker;
use App\Models\Workstation;
use Illuminate\Http\Request;

class WorkstationManagementController extends Controller
{
    /**
     * Display workstations for a specific line
     */
    public function index(Line $line)
    {
        $workstations = $line->workstations()
            ->withCount(['templateSteps', 'workers'])
            ->orderBy('code')
            ->get();

        return view('admin.workstations.index', compact('line', 'workstations'));
    }

    /**
     * Show the form for creating a new workstation
     */
    public function create(Line $line)
    {
        return view('admin.workstations.create', compact('line'));
    }

    /**
     * Store a newly created workstation
     */
    public function store(Request $request, Line $line)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:workstations,code',
            'name' => 'required|string|max:255',
            'workstation_type' => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ]);

        $validated['line_id'] = $line->id;
        $validated['is_active'] = $request->boolean('is_active', true);

        Workstation::create($validated);

        return redirect()->route('admin.lines.workstations.index', $line)
            ->with('success', 'Workstation created successfully.');
    }

    /**
     * Show the form for editing a workstation
     */
    public function edit(Line $line, Workstation $workstation)
    {
        if ($workstation->line_id !== $line->id) {
            abort(404);
        }

        $workers = Worker::active()->orderBy('name')->with('workstation')->get();

        return view('admin.workstations.edit', compact('line', 'workstation', 'workers'));
    }

    /**
     * Update the specified workstation
     */
    public function update(Request $request, Line $line, Workstation $workstation)
    {
        // Ensure workstation belongs to this line
        if ($workstation->line_id !== $line->id) {
            abort(404);
        }

        $validated = $request->validate([
            'code'             => 'required|string|max:50|unique:workstations,code,' . $workstation->id,
            'name'             => 'required|string|max:255',
            'workstation_type' => 'nullable|string|max:100',
            'is_active'        => 'boolean',
            'worker_ids'       => 'nullable|array',
            'worker_ids.*'     => 'exists:workers,id',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $workstation->update($validated);

        // Update worker assignments
        $workerIds = $request->input('worker_ids', []);
        // Un-assign workers no longer selected (only those currently at THIS workstation)
        Worker::where('workstation_id', $workstation->id)
            ->whereNotIn('id', $workerIds)
            ->update(['workstation_id' => null]);
        // Assign selected workers (may move them from another workstation)
        if (!empty($workerIds)) {
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

        $workstation->delete();

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

        $workstation->update(['is_active' => !$workstation->is_active]);

        $status = $workstation->is_active ? 'activated' : 'deactivated';

        return redirect()->route('admin.lines.workstations.index', $line)
            ->with('success', "Workstation {$status} successfully.");
    }
}
