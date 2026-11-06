<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreWorkstationRequest;
use App\Http\Requests\Api\V1\UpdateWorkstationRequest;
use App\Models\Line;
use App\Models\Workstation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkstationController extends Controller
{
    public function index(Request $request, Line $line): JsonResponse
    {
        $this->authorize('view', $line);

        $query = $line->workstations()->with('workstationType');
        if (!$request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }
        $query->withCount(['templateSteps', 'workers']);

        return response()->json([
            'data' => $query->orderBy('code')->get(),
        ]);
    }

    public function show(Workstation $workstation): JsonResponse
    {
        $this->authorize('view', $workstation);

        $workstation->load(['line', 'workstationType', 'workers']);

        return response()->json(['data' => $workstation]);
    }

    public function store(StoreWorkstationRequest $request, Line $line): JsonResponse
    {
        $this->authorize('create', Workstation::class);

        $data = $request->validated();
        $data['line_id'] = $line->id;
        $data['is_active'] = $data['is_active'] ?? true;

        $workstation = Workstation::create($data);

        return response()->json([
            'message' => 'Workstation created successfully',
            'data' => $workstation->fresh(['line', 'workstationType']),
        ], 201);
    }

    public function update(UpdateWorkstationRequest $request, Workstation $workstation): JsonResponse
    {
        $this->authorize('update', $workstation);

        $workstation->update($request->validated());

        return response()->json([
            'message' => 'Workstation updated successfully',
            'data' => $workstation->fresh(['line', 'workstationType']),
        ]);
    }

    public function destroy(Workstation $workstation): JsonResponse
    {
        $this->authorize('delete', $workstation);

        if ($workstation->templateSteps()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete workstation referenced by template steps. Deactivate it instead.',
            ], 422);
        }

        $workstation->delete();

        return response()->json(['message' => 'Workstation deleted successfully']);
    }

    public function toggleActive(Workstation $workstation): JsonResponse
    {
        $this->authorize('update', $workstation);

        $workstation->update(['is_active' => !$workstation->is_active]);

        return response()->json([
            'message' => $workstation->is_active ? 'Workstation activated' : 'Workstation deactivated',
            'data' => $workstation,
        ]);
    }
}
