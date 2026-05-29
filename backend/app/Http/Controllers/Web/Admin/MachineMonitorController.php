<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\Machine\MachineMonitorService;
use Illuminate\Http\JsonResponse;

/**
 * Live machine monitor — real-time fleet status driven by workstation_states
 * and machine_events. Uses HTTP polling (Reverb push is an optional upgrade).
 */
class MachineMonitorController extends Controller
{
    public function __construct(private readonly MachineMonitorService $monitor) {}

    public function index()
    {
        return view('admin.machine-monitor.index', [
            'tiles' => $this->tiles(),
        ]);
    }

    public function check(): JsonResponse
    {
        return response()->json(['data' => $this->tiles(), 'timestamp' => now()->timestamp]);
    }

    /**
     * Flatten the fleet read model into the tile shape used by both the initial
     * render and the polling endpoint.
     */
    private function tiles(): array
    {
        return collect($this->monitor->fleetStatus())->map(fn ($s) => [
            'id' => $s['workstation']->id,
            'name' => $s['workstation']->name,
            'line' => $s['workstation']->line?->name,
            'state' => $s['state'],
            'color' => $this->monitor->stateColor($s['state']),
            'since' => $s['since']?->toIso8601String(),
            'availability' => $s['availability'],
            'quality' => $s['quality'],
            'good' => $s['good'],
            'reject' => $s['reject'],
            'metadata' => $s['metadata'],
        ])->values()->all();
    }
}
