<?php

namespace App\Http\Controllers\Web\Operator;

use App\Http\Controllers\Controller;
use App\Models\Line;
use Illuminate\Http\Request;

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
                $defaultView = $line?->default_operator_view ?? 'queue';

                return redirect()->route($defaultView === 'workstation' ? 'operator.workstation' : 'operator.queue');
            }
        }

        // Operators see only assigned lines
        $lines = $user->lines()->where('is_active', true)->with('workstations')->get();

        return view('operator.select-line', compact('lines'));
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

        // Store selected line and workstation in session
        $request->session()->put('selected_line_id', $lineId);
        $request->session()->put('selected_workstation_id', $workstationId);

        $line = Line::find($lineId);
        $defaultView = $line?->default_operator_view ?? 'queue';
        $route = $defaultView === 'workstation' ? 'operator.workstation' : 'operator.queue';

        return redirect()->route($route)
            ->with('success', 'Line selected successfully.');
    }
}
