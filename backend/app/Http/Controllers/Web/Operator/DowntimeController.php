<?php

namespace App\Http\Controllers\Web\Operator;

use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Models\ProductionDowntime;
use App\Services\Production\DowntimeService;
use Illuminate\Http\Request;

/**
 * Operator production-downtime actions — the React/HTTP replacement for the old
 * Livewire DowntimeReporter. The line + workstation come from the operator's
 * session (set on line selection); writes go through DowntimeService.
 */
class DowntimeController extends Controller
{
    public function __construct(protected DowntimeService $downtimeService) {}

    public function start(Request $request)
    {
        $validated = $request->validate([
            'reason_id' => 'required|exists:downtime_reasons,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        $lineId = $request->session()->get('selected_line_id');
        $line = $lineId ? Line::find($lineId) : null;
        if (! $line) {
            return back()->with('error', 'No line selected.');
        }

        // Don't open a second concurrent downtime for the same line.
        $active = ProductionDowntime::where('line_id', $line->id)->whereNull('ended_at')->exists();
        if ($active) {
            return back()->with('warning', 'A downtime is already in progress for this line.');
        }

        $workstationId = $request->session()->get('selected_workstation_id');

        $this->downtimeService->start(
            $line,
            (int) $validated['reason_id'],
            $request->user(),
            $workstationId ? (int) $workstationId : null,
            $validated['notes'] ?? null,
        );

        return back()->with('success', 'Downtime started.');
    }

    public function stop(Request $request, ProductionDowntime $downtime)
    {
        $lineId = $request->session()->get('selected_line_id');
        if (! $lineId || $downtime->line_id != $lineId) {
            return back()->with('error', 'This downtime does not belong to the selected line.');
        }

        if ($downtime->ended_at) {
            return back()->with('info', 'Downtime already stopped.');
        }

        $stopped = $this->downtimeService->stop($downtime);

        return back()->with('success', 'Downtime stopped. Duration: '.$stopped->duration_minutes.' min.');
    }
}
