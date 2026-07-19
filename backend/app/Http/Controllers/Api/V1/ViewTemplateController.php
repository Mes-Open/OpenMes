<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ViewTemplateRequest;
use App\Models\ViewTemplate;
use Illuminate\Http\JsonResponse;

/**
 * View Templates (line dashboard column presets), mirroring the web admin screen
 * (Pages/admin/view-templates): name, description and the column list (each
 * column is a label + key + source). Full CRUD incl. the column builder.
 */
class ViewTemplateController extends Controller
{
    private function present(ViewTemplate $vt, bool $withColumns = false): array
    {
        $out = [
            'id' => $vt->id,
            'name' => $vt->name,
            'description' => $vt->description,
            'lines_count' => $vt->lines_count ?? $vt->lines()->count(),
            'columns_count' => is_array($vt->columns) ? count($vt->columns) : 0,
        ];
        if ($withColumns) {
            $out['columns'] = $vt->columns ?? [];
        }

        return $out;
    }

    public function index(): JsonResponse
    {
        $templates = ViewTemplate::withCount('lines')
            ->orderBy('name')
            ->get()
            ->map(fn (ViewTemplate $vt) => $this->present($vt, true));

        return response()->json(['data' => $templates]);
    }

    public function store(ViewTemplateRequest $request): JsonResponse
    {
        $template = ViewTemplate::create($request->validated());

        return response()->json(['data' => $this->present($template->fresh(), true)], 201);
    }

    public function update(ViewTemplateRequest $request, ViewTemplate $viewTemplate): JsonResponse
    {
        $viewTemplate->update($request->validated());

        return response()->json(['data' => $this->present($viewTemplate->fresh(), true)]);
    }

    public function destroy(ViewTemplate $viewTemplate): JsonResponse
    {
        $viewTemplate->delete();

        return response()->json(null, 204);
    }
}
