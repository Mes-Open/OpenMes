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
}
