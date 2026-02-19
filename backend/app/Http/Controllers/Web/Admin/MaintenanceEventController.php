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

class MaintenanceEventController extends Controller
{
    /**
     * Display a listing of maintenance events.
     */
    public function index(Request $request)
    {
        $query = MaintenanceEvent::with(['tool', 'line', 'workstation', 'assignedTo'])
            ->orderBy('scheduled_at', 'desc');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($eventType = $request->input('event_type')) {
            $query->where('event_type', $eventType);
        }

        if ($lineId = $request->input('line_id')) {
            $query->where('line_id', $lineId);
        }

        $events = $query->paginate(25)->withQueryString();
        $lines  = Line::orderBy('name')->get();

        return view('admin.maintenance-events.index', compact('events', 'lines'));
    }

    /**
     * Show the form for creating a new maintenance event.
     */
    public function create()
    {
        $lines        = Line::active()->orderBy('name')->get();
        $workstations = Workstation::active()->orderBy('name')->get();
        $tools        = Tool::orderBy('name')->get();
        $costSources  = CostSource::active()->orderBy('name')->get();
        $users        = User::orderBy('name')->get();

        return view('admin.maintenance-events.create', compact(
            'lines', 'workstations', 'tools', 'costSources', 'users'
        ));
    }

    /**
     * Store a newly created maintenance event.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'event_type'       => 'required|string|in:planned,corrective,inspection',
            'tool_id'          => 'nullable|exists:tools,id',
            'line_id'          => 'nullable|exists:lines,id',
            'workstation_id'   => 'nullable|exists:workstations,id',
            'cost_source_id'   => 'nullable|exists:cost_sources,id',
            'assigned_to_id'   => 'nullable|exists:users,id',
            'scheduled_at'     => 'nullable|date',
            'description'      => 'nullable|string|max:2000',
            'actual_cost'      => 'nullable|numeric|min:0',
            'currency'         => 'nullable|string|max:10',
        ]);

        $validated['status'] = MaintenanceEvent::STATUS_PENDING;

        MaintenanceEvent::create($validated);

        return redirect()->route('admin.maintenance-events.index')
            ->with('success', 'Maintenance event created successfully.');
    }

    /**
     * Show the form for editing a maintenance event.
     */
    public function edit(MaintenanceEvent $maintenanceEvent)
    {
        $lines        = Line::active()->orderBy('name')->get();
        $workstations = Workstation::active()->orderBy('name')->get();
        $tools        = Tool::orderBy('name')->get();
        $costSources  = CostSource::active()->orderBy('name')->get();
        $users        = User::orderBy('name')->get();

        return view('admin.maintenance-events.edit', compact(
            'maintenanceEvent', 'lines', 'workstations', 'tools', 'costSources', 'users'
        ));
    }

    /**
     * Update the specified maintenance event.
     */
    public function update(Request $request, MaintenanceEvent $maintenanceEvent)
    {
        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'event_type'       => 'required|string|in:planned,corrective,inspection',
            'tool_id'          => 'nullable|exists:tools,id',
            'line_id'          => 'nullable|exists:lines,id',
            'workstation_id'   => 'nullable|exists:workstations,id',
            'cost_source_id'   => 'nullable|exists:cost_sources,id',
            'assigned_to_id'   => 'nullable|exists:users,id',
            'scheduled_at'     => 'nullable|date',
            'description'      => 'nullable|string|max:2000',
            'resolution_notes' => 'nullable|string|max:2000',
            'actual_cost'      => 'nullable|numeric|min:0',
            'currency'         => 'nullable|string|max:10',
        ]);

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
