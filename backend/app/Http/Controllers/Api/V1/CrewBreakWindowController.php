<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CrewBreakWindowRequest;
use App\Models\CrewBreakWindow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST twin of the web Admin\CrewBreakWindowController — recurring crew break
 * windows (start/end time + days of week). Behind auth:sanctum + role:Admin.
 */
class CrewBreakWindowController extends Controller
{
    /** GET /api/v1/crew-break-windows — break windows with crew name. */
    public function index(Request $request): JsonResponse
    {
        $windows = CrewBreakWindow::with('crew:id,name')
            ->when($request->integer('crew_id'), fn ($q, $cid) => $q->where('crew_id', $cid))
            ->orderBy('start_time')
            ->get()
            ->map(fn (CrewBreakWindow $w) => [
                'id' => $w->id,
                'crew_id' => $w->crew_id,
                'crew_name' => $w->crew?->name,
                'name' => $w->name,
                'start_time' => substr((string) $w->start_time, 0, 5),
                'end_time' => substr((string) $w->end_time, 0, 5),
                'days_of_week' => array_map('intval', $w->days_of_week ?? []),
                'is_active' => (bool) $w->is_active,
            ]);

        return response()->json(['data' => $windows]);
    }

    /** POST /api/v1/crew-break-windows — create a break window. */
    public function store(CrewBreakWindowRequest $request): JsonResponse
    {
        $window = CrewBreakWindow::create($this->payload($request));

        return response()->json(['data' => $window->load('crew:id,name')], 201);
    }

    /** DELETE /api/v1/crew-break-windows/{crewBreakWindow} — soft delete. */
    public function destroy(CrewBreakWindow $crewBreakWindow): JsonResponse
    {
        $crewBreakWindow->delete();

        return response()->json(null, 204);
    }

    /**
     * Normalise the validated payload (mirrors the web controller).
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
