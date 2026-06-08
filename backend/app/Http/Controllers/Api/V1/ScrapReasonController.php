<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ScrapReason;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ScrapReasonController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ScrapReason::class);

        $query = ScrapReason::query();
        if (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }
        if ($cat = $request->query('category')) {
            $query->where('category', $cat);
        }

        return response()->json(['data' => $query->ordered()->get()]);
    }

    public function show(ScrapReason $scrapReason): JsonResponse
    {
        $this->authorize('view', $scrapReason);

        return response()->json(['data' => $scrapReason]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ScrapReason::class);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:scrap_reasons,code'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(ScrapReason::CATEGORIES)],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $data['is_active'] ?? true;
        $data['sort_order'] = $data['sort_order'] ?? 0;

        $reason = ScrapReason::create($data);

        return response()->json(['message' => 'Scrap reason created', 'data' => $reason], 201);
    }

    public function update(Request $request, ScrapReason $scrapReason): JsonResponse
    {
        $this->authorize('update', $scrapReason);
        $data = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:20', Rule::unique('scrap_reasons', 'code')->ignore($scrapReason->id)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'category' => ['sometimes', 'required', Rule::in(ScrapReason::CATEGORIES)],
            'description' => ['sometimes', 'nullable', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $scrapReason->update($data);

        return response()->json(['message' => 'Scrap reason updated', 'data' => $scrapReason->fresh()]);
    }

    public function destroy(ScrapReason $scrapReason): JsonResponse
    {
        $this->authorize('delete', $scrapReason);
        if ($scrapReason->scrapEntries()->exists()) {
            return response()->json(['message' => 'Cannot delete reason referenced by scrap entries.'], 422);
        }
        $scrapReason->delete();

        return response()->json(['message' => 'Scrap reason deleted']);
    }
}
