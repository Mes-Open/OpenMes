<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProcessSegment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProcessSegmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ProcessSegment::query()
            ->with(['workstationType'])
            ->withCount('templateSteps')
            ->orderBy('code');

        if ($type = $request->query('segment_type')) {
            $query->where('segment_type', $type);
        }
        if ($wsTypeId = $request->query('workstation_type_id')) {
            $query->where('workstation_type_id', $wsTypeId);
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        } elseif (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->query('per_page', 30);
        $perPage = max(1, min($perPage, 100));
        $page    = $query->paginate($perPage);

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
            ],
        ]);
    }

    public function show(ProcessSegment $processSegment): JsonResponse
    {
        $processSegment->load(['workstationType', 'createdBy']);
        $processSegment->loadCount('templateSteps');

        return response()->json(['data' => $processSegment]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request);
        $data['is_active']     = $data['is_active'] ?? true;
        $data['created_by_id'] = $request->user()?->id;

        $segment = ProcessSegment::create($data);

        return response()->json([
            'message' => __('Process segment created'),
            'data'    => $segment->load('workstationType'),
        ], 201);
    }

    public function update(Request $request, ProcessSegment $processSegment): JsonResponse
    {
        $data = $this->validatePayload($request, $processSegment);
        $processSegment->update($data);

        return response()->json([
            'message' => __('Process segment updated'),
            'data'    => $processSegment->fresh(['workstationType']),
        ]);
    }

    public function destroy(ProcessSegment $processSegment): JsonResponse
    {
        $usage = $processSegment->templateSteps()->count();
        if ($usage > 0) {
            return response()->json([
                'message' => __('Cannot delete — used by :count template step(s).', ['count' => $usage]),
            ], 422);
        }

        $processSegment->delete();

        return response()->json(['message' => __('Process segment deleted')]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function validatePayload(Request $request, ?ProcessSegment $segment = null): array
    {
        $tenantId  = $request->user()?->tenant_id;
        $segmentId = $segment?->id;
        $isUpdate  = $segment !== null;

        $rules = [
            'code' => [
                $isUpdate ? 'sometimes' : 'required',
                'string', 'max:50',
                Rule::unique('process_segments', 'code')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId))
                    ->ignore($segmentId),
            ],
            'name'                       => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'description'                => ['nullable', 'string'],
            'segment_type'               => [$isUpdate ? 'sometimes' : 'required', Rule::in(ProcessSegment::TYPES)],
            'workstation_type_id'        => ['nullable', 'integer', 'exists:workstation_types,id'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'required_operators'         => ['nullable', 'integer', 'min:1', 'max:50'],
            'standard_instruction'       => ['nullable', 'string'],
            'required_skill_ids'         => ['nullable', 'array'],
            'required_skill_ids.*'       => ['integer', 'exists:skills,id'],
            'parameters'                 => ['nullable', 'array'],
            'is_active'                  => ['sometimes', 'boolean'],
        ];

        return $request->validate($rules);
    }
}
