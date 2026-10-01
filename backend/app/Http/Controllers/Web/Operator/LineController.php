<?php

namespace App\Http\Controllers\Web\Operator;

use App\Http\Controllers\Controller;
use App\Models\Line;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LineController extends Controller
{
    /**
     * Show line selection page.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Users with a default workstation auto-redirect (both workstation accounts and operators with assigned workstation)
        if ($user->workstation_id) {
            $workstation = $user->workstation;
            $lineId = $workstation?->line_id;
            if ($lineId) {
                $request->session()->put('selected_line_id', $lineId);
                $request->session()->put('selected_workstation_id', $workstation->id);
                $line = Line::find($lineId);
                $screens = app(\App\Services\Production\OperatorScreens::class);

                // A packing bench opens on packing, an assembly bench on its queue.
                return redirect()->route($screens->landingRoute($screens->for($user, $workstation), $line?->default_operator_view ?? 'queue'));
            }
        }

        // Operators see only assigned lines
        $assigned = $user->lines()->where('is_active', true)->with('workstations')->get();

        // One line leaves nothing to choose: open it straight away. The bench stays
        // switchable from the queue, so keep the one already picked on this line.
        if ($assigned->count() === 1) {
            $line = $assigned->first();
            $keep = $request->session()->get('selected_line_id') == $line->id
                ? $request->session()->get('selected_workstation_id')
                : null;
            $workstationId = $keep && $line->workstations->where('is_active', true)->contains('id', $keep)
                ? $keep
                : null;

            return $this->land($request, $line, $workstationId);
        }

        $lines = $assigned
            ->map(fn ($line) => [
                'id' => $line->id,
                'name' => $line->name,
                'description' => $line->description,
                'workstations' => $line->workstations
                    ->where('is_active', true)
                    ->sortBy('name')
                    ->map(fn ($ws) => ['id' => $ws->id, 'name' => $ws->name, 'code' => $ws->code])
                    ->values(),
            ])->values();

        return Inertia::render('operator/SelectLine', compact('lines'));
    }

    /**
     * Select a line and store in session.
     */
    public function select(Request $request)
    {
        $request->validate([
            'line_id' => 'required|exists:lines,id',
            'workstation_id' => 'nullable|exists:workstations,id',
        ]);

        $lineId = $request->input('line_id');

        // Verify operator has access to this line
        if (! $request->user()->lines()->where('lines.id', $lineId)->exists()) {
            return back()->with('error', 'You do not have access to this line.');
        }

        // If workstation selected, verify it belongs to this line
        $workstationId = $request->input('workstation_id');
        if ($workstationId) {
            $validWorkstation = \App\Models\Workstation::where('id', $workstationId)
                ->where('line_id', $lineId)
                ->where('is_active', true)
                ->exists();
            if (! $validWorkstation) {
                $workstationId = null;
            }
        }

        return $this->land($request, Line::find($lineId), $workstationId);
    }

    /**
     * Remember the line and bench in the session and open the bench's first screen.
     */
    private function land(Request $request, Line $line, $workstationId)
    {
        $request->session()->put('selected_line_id', $line->id);
        $request->session()->put('selected_workstation_id', $workstationId);

        // The bench's first screen: a packing bench opens on packing, an assembly
        // bench on the line's default production view.
        $screens = app(\App\Services\Production\OperatorScreens::class);
        $route = $screens->landingRoute(
            $screens->for($request->user(), $workstationId ? \App\Models\Workstation::find($workstationId) : null),
            $line->default_operator_view ?? 'queue',
        );

        // The choice rides in the address, so the page can be bookmarked or
        // shared and opens on this line and bench.
        return redirect()->route($route, ['line' => $line->id, 'workstation' => $workstationId ?: 'all']);
    }
}
