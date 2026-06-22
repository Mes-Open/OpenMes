<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SetWorkstationStateRequest;
use App\Models\Workstation;
use App\Models\WorkstationState;
use App\Services\Machine\MachineMonitorService;
use App\Services\Machine\WorkstationStateMachine;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;

/**
 * Live machine monitor — real-time fleet status driven by workstation_states
 * and machine_events. Uses HTTP polling for refresh.
 */
class MachineMonitorController extends Controller
{
    public function __construct(private readonly MachineMonitorService $monitor) {}

    public function index()
    {
        return Inertia::render('admin/machine-monitor/Index', [
            'tiles' => $this->tiles(),
            'checkUrl' => route('admin.machine-monitor.check'),
            'states' => WorkstationState::STATES,
        ]);
    }

    public function check(): JsonResponse
    {
        return response()->json(['data' => $this->tiles(), 'timestamp' => now()->timestamp]);
    }

    /**
     * Manually set a workstation's state (#87) — supervisor/admin override from
     * the monitor. Recorded with source 'manual' in the state history.
     */
    public function setState(SetWorkstationStateRequest $request, Workstation $workstation, WorkstationStateMachine $stateMachine): JsonResponse
    {
        $note = $request->validated()['note'] ?? null;

        $stateMachine->transition(
            $workstation,
            $request->validated()['state'],
            $note ? ['note' => $note] : [],
            null,
            'manual',
        );

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
