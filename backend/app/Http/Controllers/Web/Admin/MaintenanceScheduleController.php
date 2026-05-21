<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\CostSource;
use App\Models\Line;
use App\Models\MaintenanceEvent;
use App\Models\MaintenanceSchedule;
use App\Models\Tool;
use App\Models\User;
use App\Models\Workstation;
use App\Services\Maintenance\GenerateMaintenanceEvents;
use Illuminate\Http\Request;

class MaintenanceScheduleController extends Controller
{
    /**
     * Display a listing of maintenance schedules.
     */
    public function index(Request $request)
    {
        $query = MaintenanceSchedule::query()
            ->with(['tool', 'line', 'workstation', 'assignedTo'])
            ->orderBy('is_active', 'desc')
            ->orderBy('next_due_at');

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($frequency = $request->input('frequency')) {
            $query->where('frequency', $frequency);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->input('is_active') === '1');
        }

        $schedules = $query->paginate(25)->withQueryString();

        return view('admin.maintenance-schedules.index', compact('schedules'));
    }

    /**
     * Show the form for creating a new schedule.
     */
    public function create()
    {
        return view('admin.maintenance-schedules.create', $this->formData());
    }

    /**
     * Store a newly created schedule.
     */
    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        $validated['created_by_id'] = $request->user()?->id;
        $validated['is_active']     = $request->boolean('is_active', true);

        MaintenanceSchedule::create($validated);

        return redirect()->route('admin.maintenance-schedules.index')
            ->with('success', __('Maintenance schedule created successfully.'));
    }

    /**
     * Show the form for editing a schedule.
     */
    public function edit(MaintenanceSchedule $maintenanceSchedule)
    {
        return view('admin.maintenance-schedules.edit', array_merge(
            $this->formData(),
            ['schedule' => $maintenanceSchedule]
        ));
    }

    /**
     * Update the specified schedule.
     */
    public function update(Request $request, MaintenanceSchedule $maintenanceSchedule)
    {
        $validated = $this->validatePayload($request);
        $validated['is_active'] = $request->boolean('is_active', false);

        $maintenanceSchedule->update($validated);

        return redirect()->route('admin.maintenance-schedules.index')
            ->with('success', __('Maintenance schedule updated successfully.'));
    }

    /**
     * Remove the specified schedule. Events already generated keep their
     * schedule_id nulled by the FK constraint.
     */
    public function destroy(MaintenanceSchedule $maintenanceSchedule)
    {
        $maintenanceSchedule->delete();

        return redirect()->route('admin.maintenance-schedules.index')
            ->with('success', __('Maintenance schedule deleted successfully.'));
    }

    /**
     * Force-generate an event for this schedule immediately, bypassing the
     * lead-time gate by setting next_due_at to now() if it's still in the future.
     */
    public function generateNow(MaintenanceSchedule $maintenanceSchedule, GenerateMaintenanceEvents $service)
    {
        if (! $maintenanceSchedule->is_active) {
            return redirect()->route('admin.maintenance-schedules.index')
                ->with('error', __('Cannot generate an event from an inactive schedule.'));
        }

        // Idempotent guard: if an event already exists for the current next_due_at, just refuse.
        $exists = MaintenanceEvent::query()
            ->where('schedule_id', $maintenanceSchedule->id)
            ->where('scheduled_at', $maintenanceSchedule->next_due_at)
            ->exists();

        if ($exists) {
            return redirect()->route('admin.maintenance-schedules.index')
                ->with('error', __('An event for this cycle already exists.'));
        }

        // Pull next_due_at to now() if it's in the future so isDue() returns true.
        if ($maintenanceSchedule->next_due_at && $maintenanceSchedule->next_due_at->isFuture()) {
            $maintenanceSchedule->forceFill(['next_due_at' => now()])->save();
        }

        $created = $service->run();

        return redirect()->route('admin.maintenance-schedules.index')
            ->with('success', __(':n event(s) generated.', ['n' => $created]));
    }

    /**
     * Shared form select options.
     *
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'lines'        => Line::active()->orderBy('name')->get(),
            'workstations' => Workstation::active()->orderBy('name')->get(),
            'tools'        => Tool::orderBy('name')->get(),
            'costSources'  => CostSource::active()->orderBy('name')->get(),
            'users'        => User::orderBy('name')->get(),
            'frequencies'  => MaintenanceSchedule::FREQUENCIES,
        ];
    }

    /**
     * Validate store/update payload. Mirrors event validation for targets +
     * adds recurrence fields.
     *
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'name'             => 'required|string|max:255',
            'description'      => 'nullable|string|max:2000',
            'tool_id'          => 'nullable|required_without_all:line_id,workstation_id|exists:tools,id',
            'line_id'          => 'nullable|required_without_all:tool_id,workstation_id|exists:lines,id',
            'workstation_id'   => 'nullable|required_without_all:tool_id,line_id|exists:workstations,id',
            'event_type'       => 'required|string|in:planned,corrective,inspection',
            'assigned_to_id'   => 'nullable|exists:users,id',
            'cost_source_id'   => 'nullable|exists:cost_sources,id',
            'frequency'        => 'required|string|in:' . implode(',', MaintenanceSchedule::FREQUENCIES),
            'interval_value'   => 'required|integer|min:1',
            'preferred_time'   => 'nullable|date_format:H:i',
            'lead_time_days'   => 'nullable|integer|min:0|max:30',
            'next_due_at'      => 'required|date',
        ], [
            'tool_id.required_without_all'        => __('Select at least one of: Tool, Line, or Workstation.'),
            'line_id.required_without_all'        => __('Select at least one of: Tool, Line, or Workstation.'),
            'workstation_id.required_without_all' => __('Select at least one of: Tool, Line, or Workstation.'),
        ]);
    }
}
