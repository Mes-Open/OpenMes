<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Area;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AreaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Area::class);

        $query = Area::query()->with('site')->withCount('lines');

        if (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        if ($siteId = $request->input('site_id')) {
            $query->where('site_id', $siteId);
        }

        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    public function show(Area $area): JsonResponse
    {
        $this->authorize('view', $area);

        $area->load(['site', 'lines'])->loadCount('lines');

        return response()->json(['data' => $area]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Area::class);
        $validated = $this->validatePayload($request);
        $area = Area::create($validated);
        $area->load('site');
        return response()->json(['data' => $area], 201);
    }

    public function update(Request $request, Area $area): JsonResponse
    {
        $this->authorize('update', $area);
        $validated = $this->validatePayload($request, $area->id);
        $area->update($validated);
        $area->load('site');
        return response()->json(['data' => $area]);
    }

    public function destroy(Area $area): JsonResponse
    {
        $this->authorize('delete', $area);
        $area->delete();
        return response()->json(['message' => 'Area deleted']);
    }

    private function validatePayload(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            // Unique within (site_id, code) so two different sites can both
            // have an Area called "ASSEMBLY-1".
            'code'        => [
                'required', 'string', 'max:50',
                \Illuminate\Validation\Rule::unique('areas', 'code')
                    ->where(fn ($q) => $q->where('site_id', $request->input('site_id')))
                    ->ignore($ignoreId),
            ],
            'site_id'     => ['required', 'integer', 'exists:sites,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active'   => ['boolean'],
        ]);
    }
}
