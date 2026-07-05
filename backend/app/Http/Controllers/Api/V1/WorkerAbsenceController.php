<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkerAbsenceRequest;
use App\Models\WorkerAbsence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST twin of the web Admin\WorkerAbsenceController — worker absences
 * (vacation / sick / personal / training). Behind auth:sanctum + role:Admin.
 */
class WorkerAbsenceController extends Controller
{
    /** GET /api/v1/worker-absences — absences with worker name (most recent first). */
    public function index(Request $request): JsonResponse
    {
        $absences = WorkerAbsence::with('worker:id,name')
            ->when($request->integer('worker_id'), fn ($q, $wid) => $q->where('worker_id', $wid))
            ->orderByDesc('starts_on')
            ->limit(200)
            ->get()
            ->map(fn (WorkerAbsence $a) => [
                'id' => $a->id,
                'worker_id' => $a->worker_id,
                'worker_name' => $a->worker?->name,
                'type' => $a->type,
                'status' => $a->status,
                'starts_on' => $a->starts_on?->toDateString(),
                'ends_on' => $a->ends_on?->toDateString(),
                'all_day' => (bool) $a->all_day,
                'reason' => $a->reason,
            ]);

        return response()->json(['data' => $absences]);
    }

    /** POST /api/v1/worker-absences — record an absence. */
    public function store(WorkerAbsenceRequest $request): JsonResponse
    {
        $absence = WorkerAbsence::create($this->payload($request));

        return response()->json(['data' => $absence->load('worker:id,name')], 201);
    }

    /** DELETE /api/v1/worker-absences/{workerAbsence} */
    public function destroy(WorkerAbsence $workerAbsence): JsonResponse
    {
        $workerAbsence->delete();

        return response()->json(null, 204);
    }

    /**
     * Normalise the validated payload: default status/all_day and clear the
     * partial-day times when the absence is full-day (mirrors the web controller).
     *
     * @return array<string, mixed>
     */
    private function payload(WorkerAbsenceRequest $request): array
    {
        $data = $request->validated();
        $data['all_day'] = $request->boolean('all_day', true);
        $data['status'] = $data['status'] ?? 'approved';
        $data['created_by_id'] = $request->user()?->id;

        if ($data['all_day']) {
            $data['start_time'] = null;
            $data['end_time'] = null;
        }

        return $data;
    }
}
