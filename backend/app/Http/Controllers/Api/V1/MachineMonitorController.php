<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SetWorkstationStateRequest;
use App\Models\Workstation;
use App\Models\WorkstationState;
use App\Services\Machine\MachineMonitorService;
use App\Services\Machine\WorkstationStateMachine;
use Illuminate\Http\JsonResponse;

/**
 * REST twin of the web Admin\MachineMonitorController — live fleet status
 * (workstation states) + a manual state override. Behind auth:sanctum +
 * role:Supervisor|Admin. Refresh via polling.
 */
class MachineMonitorController extends Controller
{
    public function __construct(private readonly MachineMonitorService $monitor) {}

    /** GET /api/v1/machine-monitor — live fleet status tiles + the valid state set. */
    public function index(): JsonResponse
    {
        return response()->json(['data' => [
            'tiles' => $this->tiles(),
            'states' => WorkstationState::STATES,
        ]]);
    }

    /** POST /api/v1/machine-monitor/{workstation}/state — manual state override (#87). */
    public function setState(
        SetWorkstationStateRequest $request,
        Workstation $workstation,
        WorkstationStateMachine $stateMachine,
    ): JsonResponse {
        $note = $request->validated()['note'] ?? null;

        $stateMachine->transition(
            $workstation,
            $request->validated()['state'],
            $note ? ['note' => $note] : [],
            null,
            'manual',
        );

        return response()->json(['data' => ['tiles' => $this->tiles()]]);
    }

    /**
     * Flatten the fleet read model into the tile shape (mirrors the web controller).
     *
     * @return array<int, array<string, mixed>>
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
