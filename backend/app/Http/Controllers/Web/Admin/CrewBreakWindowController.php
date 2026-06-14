<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CrewBreakWindowRequest;
use App\Models\Crew;
use App\Models\CrewBreakWindow;
use Inertia\Inertia;

class CrewBreakWindowController extends Controller
{
    /**
     * Display a listing of break windows. Rows live-sync via the
     * `crew_break_windows` shape; crew names come as a prop (the shape only
     * carries crew_id).
     */
    public function index()
    {
        return Inertia::render('admin/crew-break-windows/Index', [
            'crewNames' => Crew::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/crew-break-windows/Create', [
            'crews' => Crew::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(CrewBreakWindowRequest $request)
    {
        CrewBreakWindow::create($this->payload($request));

        return redirect()->route('admin.crew-break-windows.index')
            ->with('success', __('Break window created successfully.'));
    }

    public function edit(CrewBreakWindow $crewBreakWindow)
    {
        return Inertia::render('admin/crew-break-windows/Edit', [
            'window' => [
                'id' => $crewBreakWindow->id,
                'crew_id' => $crewBreakWindow->crew_id,
                'name' => $crewBreakWindow->name,
                'start_time' => substr((string) $crewBreakWindow->start_time, 0, 5),
                'end_time' => substr((string) $crewBreakWindow->end_time, 0, 5),
                'days_of_week' => array_map('intval', $crewBreakWindow->days_of_week ?? []),
                'is_active' => (bool) $crewBreakWindow->is_active,
            ],
            'crews' => Crew::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(CrewBreakWindowRequest $request, CrewBreakWindow $crewBreakWindow)
    {
        $crewBreakWindow->update($this->payload($request));

        return redirect()->route('admin.crew-break-windows.index')
            ->with('success', __('Break window updated successfully.'));
    }

    public function destroy(CrewBreakWindow $crewBreakWindow)
    {
        $crewBreakWindow->delete();

        return redirect()->route('admin.crew-break-windows.index')
            ->with('success', __('Break window deleted successfully.'));
    }

    /**
     * Normalise the validated payload: default is_active and store days_of_week
     * as a clean integer array.
     *
     * @return array<string, mixed>
     */
    private function payload(CrewBreakWindowRequest $request): array
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $data['days_of_week'] = array_values(array_map('intval', $data['days_of_week']));

        return $data;
    }
}
