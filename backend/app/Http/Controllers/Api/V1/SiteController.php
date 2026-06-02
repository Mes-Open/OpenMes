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

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Site::class);
        $validated = $this->validatePayload($request);
        $site = Site::create($validated);
        $site->load('company');
        return response()->json(['data' => $site], 201);
    }

    public function update(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);
        $validated = $this->validatePayload($request, $site->id);
        $site->update($validated);
        $site->load('company');
        return response()->json(['data' => $site]);
    }

    public function destroy(Site $site): JsonResponse
    {
        $this->authorize('delete', $site);
        $site->delete();
        return response()->json(['message' => 'Site deleted']);
    }

    private function validatePayload(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'code'        => [
                'required', 'string', 'max:50',
                \Illuminate\Validation\Rule::unique('sites', 'code')->ignore($ignoreId),
            ],
            'company_id'  => ['nullable', 'integer', 'exists:companies,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'address'     => ['nullable', 'string', 'max:500'],
            'city'        => ['nullable', 'string', 'max:120'],
            'country'     => ['nullable', 'string', 'max:80'],
            'timezone'    => ['nullable', 'string', 'max:64'],
            'is_active'   => ['boolean'],
        ]);
    }
}
