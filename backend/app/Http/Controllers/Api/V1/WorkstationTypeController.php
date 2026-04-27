<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreWorkstationTypeRequest;
use App\Http\Requests\Api\V1\UpdateWorkstationTypeRequest;
use App\Models\WorkstationType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkstationTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = WorkstationType::query()->withCount('workstations');
        if (!$request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }
        if ($q = $request->query('q')) {
            $needle = '%' . strtolower($q) . '%';
            $query->where(function ($qb) use ($needle) {
                $qb->whereRaw('LOWER(name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(code) LIKE ?', [$needle]);
            });
        }
        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    public function show(WorkstationType $workstationType): JsonResponse
    {
        $this->authorize('view', $workstationType);
        $workstationType->loadCount('workstations');
        return response()->json(['data' => $workstationType]);
    }

    public function store(StoreWorkstationTypeRequest $request): JsonResponse
    {
        $this->authorize('create', WorkstationType::class);
        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;
        $wt = WorkstationType::create($data);
        return response()->json(['message' => 'Workstation type created', 'data' => $wt], 201);
    }

    public function update(UpdateWorkstationTypeRequest $request, WorkstationType $workstationType): JsonResponse
    {
        $this->authorize('update', $workstationType);
        $workstationType->update($request->validated());
        return response()->json(['message' => 'Workstation type updated', 'data' => $workstationType->fresh()]);
    }

    public function destroy(WorkstationType $workstationType): JsonResponse
    {
        $this->authorize('delete', $workstationType);

        if ($workstationType->workstations()->exists()) {
            return response()->json([
                'message' => 'Cannot delete workstation type referenced by workstations. Deactivate it instead.',
            ], 422);
        }

        $workstationType->delete();
        return response()->json(['message' => 'Workstation type deleted']);
    }

    public function toggleActive(WorkstationType $workstationType): JsonResponse
    {
        $this->authorize('update', $workstationType);
        $workstationType->update(['is_active' => !$workstationType->is_active]);
        return response()->json([
            'message' => $workstationType->is_active ? 'Activated' : 'Deactivated',
            'data' => $workstationType,
        ]);
    }
}
