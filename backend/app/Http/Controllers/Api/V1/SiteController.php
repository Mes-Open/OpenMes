<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Site::class);

        $query = Site::query()->with('company')->withCount(['areas', 'lines']);

        if (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        if ($companyId = $request->input('company_id')) {
            $query->where('company_id', $companyId);
        }

        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    public function show(Site $site): JsonResponse
    {
        $this->authorize('view', $site);

        $site->load([
            'company',
            'areas' => function ($q) {
                $q->withCount('lines')->orderBy('name');
            },
        ])->loadCount(['areas', 'lines']);

        return response()->json(['data' => $site]);
    }
}
