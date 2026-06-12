<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\CostSource;
use App\Models\Line;
use App\Models\MaintenanceEvent;
use App\Models\Tool;
use App\Models\User;
use App\Models\Workstation;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MaintenanceEventController extends Controller
{
    /**
     * Display a listing of maintenance events.
     */
    public function index(Request $request)
    {
        return Inertia::render('admin/maintenance-events/Index', [
            'toolNames'        => Tool::pluck('name', 'id'),
            'lineNames'        => Line::pluck('name', 'id'),
            'workstationNames' => Workstation::pluck('name', 'id'),
            'userNames'        => User::pluck('name', 'id'),
        ]);
    }

    /**
     * Show the form for creating a new maintenance event.
     */
    public function create()
    {
        return Inertia::render('admin/maintenance-events/Create', [
            'tools'        => Tool::orderBy('name')->get(['id', 'name']),
            'lines'        => Line::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'workstations' => Workstation::orderBy('name')->get(['id', 'name']),
            'costSources'  => CostSource::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'users'        => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Store a newly created maintenance event.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'event_type'       => 'required|string|in:planned,corrective,inspection',
            'tool_id'          => 'nullable|required_without_all:line_id,workstation_id|exists:tools,id',
            'line_id'          => 'nullable|required_without_all:tool_id,workstation_id|exists:lines,id',
            'workstation_id'   => 'nullable|required_without_all:tool_id,line_id|exists:workstations,id',
            'cost_source_id'   => 'nullable|exists:cost_sources,id',
            'assigned_to_id'   => 'nullable|exists:users,id',
            'scheduled_at'     => 'required|date',
            'scheduled_end_at' => 'nullable|date|after:scheduled_at',
            'description'      => 'nullable|string|max:2000',
            'actual_cost'      => 'nullable|numeric|min:0',
            'currency'         => 'nullable|string|max:10',
        ], [
            'tool_id.required_without_all'        => 'Select at least one of: Tool, Line, or Workstation.',
            'line_id.required_without_all'        => 'Select at least one of: Tool, Line, or Workstation.',
            'workstation_id.required_without_all' => 'Select at least one of: Tool, Line, or Workstation.',
        ]);

        $validated['status'] = MaintenanceEvent::STATUS_PENDING;
        // currency is NOT NULL DEFAULT 'PLN'; a cleared field arrives as null and
        // would trip the constraint, so restore the default.
        $validated['currency'] ??= 'PLN';

        MaintenanceEvent::create($validated);

        return redirect()->route('admin.maintenance-events.index')
            ->with('success', __('Maintenance event created successfully.'));
    }

    /**
     * Show the form for editing a maintenance event.
     */
    public function edit(MaintenanceEvent $maintenanceEvent)
    {
        return Inertia::render('admin/maintenance-events/Edit', [
            'event' => $maintenanceEvent->only(
                'id', 'title', 'event_type', 'tool_id', 'line_id', 'workstation_id',
                'cost_source_id', 'assigned_to_id', 'description', 'actual_cost', 'currency'
            ),
            'scheduled_at'     => $maintenanceEvent->scheduled_at?->format('Y-m-d\TH:i'),
            'scheduled_end_at' => $maintenanceEvent->scheduled_end_at?->format('Y-m-d\TH:i'),
            'tools'            => Tool::orderBy('name')->get(['id', 'name']),
            'lines'            => Line::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'workstations'     => Workstation::orderBy('name')->get(['id', 'name']),
            'costSources'      => CostSource::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'users'            => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Update the specified maintenance event.
     */
    public function update(Request $request, MaintenanceEvent $maintenanceEvent)
    {
        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'event_type'       => 'required|string|in:planned,corrective,inspection',
            'tool_id'          => 'nullable|required_without_all:line_id,workstation_id|exists:tools,id',
            'line_id'          => 'nullable|required_without_all:tool_id,workstation_id|exists:lines,id',
            'workstation_id'   => 'nullable|required_without_all:tool_id,line_id|exists:workstations,id',
            'cost_source_id'   => 'nullable|exists:cost_sources,id',
            'assigned_to_id'   => 'nullable|exists:users,id',
            'scheduled_at'     => 'required|date',
            'scheduled_end_at' => 'nullable|date|after:scheduled_at',
            'started_at'       => 'nullable|date',
            'completed_at'     => 'nullable|date|after_or_equal:started_at',
            'description'      => 'nullable|string|max:2000',
            'resolution_notes' => 'nullable|string|max:2000',
            'actual_cost'      => 'nullable|numeric|min:0',
            'currency'         => 'nullable|string|max:10',
        ], [
            'tool_id.required_without_all'        => 'Select at least one of: Tool, Line, or Workstation.',
            'line_id.required_without_all'        => 'Select at least one of: Tool, Line, or Workstation.',
            'workstation_id.required_without_all' => 'Select at least one of: Tool, Line, or Workstation.',
        ]);

        // currency is NOT NULL DEFAULT 'PLN' — coerce a cleared field.
        $validated['currency'] ??= 'PLN';

        $maintenanceEvent->update($validated);

        return redirect()->route('admin.maintenance-events.index')
            ->with('success', 'Maintenance event updated successfully.');
    }

    /**
     * Remove the specified maintenance event.
     */
    public function destroy(MaintenanceEvent $maintenanceEvent)
    {
        if (in_array($maintenanceEvent->status, [MaintenanceEvent::STATUS_IN_PROGRESS])) {
            return redirect()->route('admin.maintenance-events.index')
                ->with('error', 'Cannot delete a maintenance event that is currently in progress.');
        }

        $maintenanceEvent->delete();

        return redirect()->route('admin.maintenance-events.index')
            ->with('success', 'Maintenance event deleted successfully.');
    }

    /**
     * Transition event to in_progress.
     */
    public function start(MaintenanceEvent $maintenanceEvent)
    {
        if ($maintenanceEvent->status !== MaintenanceEvent::STATUS_PENDING) {
            return redirect()->route('admin.maintenance-events.index')
                ->with('error', 'Only pending events can be started.');
        }

        $maintenanceEvent->update([
            'status'     => MaintenanceEvent::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);

        return redirect()->route('admin.maintenance-events.index')
            ->with('success', 'Maintenance event started.');
    }

    /**
     * Transition event to completed.
     */
    public function complete(Request $request, MaintenanceEvent $maintenanceEvent)
    {
        if ($maintenanceEvent->status !== MaintenanceEvent::STATUS_IN_PROGRESS) {
            return redirect()->route('admin.maintenance-events.index')
                ->with('error', 'Only in-progress events can be completed.');
        }

        $validated = $request->validate([
            'resolution_notes' => 'nullable|string|max:2000',
            'actual_cost'      => 'nullable|numeric|min:0',
        ]);

        $maintenanceEvent->update(array_merge($validated, [
            'status'       => MaintenanceEvent::STATUS_COMPLETED,
            'completed_at' => now(),
        ]));

        return redirect()->route('admin.maintenance-events.index')
            ->with('success', 'Maintenance event marked as completed.');
    }

    /**
     * Transition event to cancelled.
     */
    public function cancel(MaintenanceEvent $maintenanceEvent)
    {
        if ($maintenanceEvent->status === MaintenanceEvent::STATUS_COMPLETED) {
            return redirect()->route('admin.maintenance-events.index')
                ->with('error', 'Completed maintenance events cannot be cancelled.');
        }

        $maintenanceEvent->update(['status' => MaintenanceEvent::STATUS_CANCELLED]);

        return redirect()->route('admin.maintenance-events.index')
            ->with('success', 'Maintenance event cancelled.');
    }
}
