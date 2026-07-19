<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LabelTemplateRequest;
use App\Models\LabelTemplate;
use Illuminate\Http\JsonResponse;

/**
 * Label templates (print layouts for WO / finished-goods / step / pallet labels),
 * mirroring the web admin screen (Pages/packaging/label-templates): name, type,
 * size, barcode format, the on-label field map, default + active flags. Full
 * CRUD incl. set-default (one default per type). PDF/ZPL rendering stays on web.
 */
class LabelTemplateController extends Controller
{
    private function present(LabelTemplate $tpl): array
    {
        return [
            'id' => $tpl->id,
            'name' => $tpl->name,
            'type' => $tpl->type,
            'type_label' => LabelTemplate::TYPES[$tpl->type] ?? $tpl->type,
            'size' => $tpl->size,
            'barcode_format' => $tpl->barcode_format,
            'fields_config' => $tpl->fields_config ?? [],
            'fields_count' => is_array($tpl->fields_config) ? count(array_filter($tpl->fields_config)) : 0,
            'is_default' => $tpl->is_default,
            'is_active' => $tpl->is_active,
        ];
    }

    public function index(): JsonResponse
    {
        $templates = LabelTemplate::orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (LabelTemplate $tpl) => $this->present($tpl));

        return response()->json(['data' => $templates]);
    }

    /** Type / size / barcode-format / available-field catalogs for the form. */
    public function formMeta(): JsonResponse
    {
        $opts = fn (array $map) => collect($map)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values();

        return response()->json(['data' => [
            'types' => $opts(LabelTemplate::TYPES),
            'sizes' => $opts(LabelTemplate::SIZES),
            'barcode_formats' => $opts(LabelTemplate::BARCODE_FORMATS),
            'fields' => $opts(LabelTemplate::AVAILABLE_FIELDS),
        ]]);
    }

    public function store(LabelTemplateRequest $request): JsonResponse
    {
        $template = LabelTemplate::create([
            'name' => $request->input('name'),
            'type' => $request->input('type'),
            'size' => $request->input('size'),
            'barcode_format' => $request->input('barcode_format'),
            'fields_config' => $request->fieldsConfig(),
            'is_default' => $request->boolean('is_default'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->ensureSingleDefault($template);

        return response()->json(['data' => $this->present($template->fresh())], 201);
    }

    public function update(LabelTemplateRequest $request, LabelTemplate $labelTemplate): JsonResponse
    {
        $labelTemplate->update([
            'name' => $request->input('name'),
            'type' => $request->input('type'),
            'size' => $request->input('size'),
            'barcode_format' => $request->input('barcode_format'),
            'fields_config' => $request->fieldsConfig(),
            'is_default' => $request->boolean('is_default'),
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->ensureSingleDefault($labelTemplate);

        return response()->json(['data' => $this->present($labelTemplate->fresh())]);
    }

    public function setDefault(LabelTemplate $labelTemplate): JsonResponse
    {
        $labelTemplate->update(['is_default' => true]);
        $this->ensureSingleDefault($labelTemplate);

        return response()->json(['data' => $this->present($labelTemplate->fresh())]);
    }

    public function destroy(LabelTemplate $labelTemplate): JsonResponse
    {
        $labelTemplate->delete();

        return response()->json(null, 204);
    }

    /** Keep at most one default template per type. */
    private function ensureSingleDefault(LabelTemplate $template): void
    {
        if (! $template->is_default) {
            return;
        }

        LabelTemplate::query()
            ->where('type', $template->type)
            ->where('id', '!=', $template->id)
            ->update(['is_default' => false]);
    }
}
