<?php

namespace App\Http\Controllers\Web\Packaging;

use App\Http\Controllers\Controller;
use App\Models\LabelTemplate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LabelTemplateController extends Controller
{
    public function index()
    {
        $templates = LabelTemplate::query()
            ->orderBy('type')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return view('packaging.label-templates.index', compact('templates'));
    }

    public function create()
    {
        $template = new LabelTemplate([
            'type' => LabelTemplate::TYPE_WORK_ORDER,
            'size' => '100x50',
            'barcode_format' => 'code128',
            'fields_config' => LabelTemplate::defaultFieldsFor(LabelTemplate::TYPE_WORK_ORDER),
            'is_active' => true,
        ]);

        return view('packaging.label-templates.create', [
            'template' => $template,
            ...$this->formData($template),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);

        $template = LabelTemplate::create($validated);

        if ($template->is_default) {
            $this->ensureSingleDefault($template);
        }

        return redirect()->route('packaging.label-templates.index')
            ->with('success', __('Label template created.'));
    }

    public function edit(LabelTemplate $labelTemplate)
    {
        return view('packaging.label-templates.edit', [
            'template' => $labelTemplate,
            ...$this->formData($labelTemplate),
        ]);
    }

    public function update(Request $request, LabelTemplate $labelTemplate)
    {
        $validated = $this->validateRequest($request);

        $labelTemplate->update($validated);

        if ($labelTemplate->is_default) {
            $this->ensureSingleDefault($labelTemplate);
        }

        return redirect()->route('packaging.label-templates.index')
            ->with('success', __('Label template updated.'));
    }

    public function destroy(LabelTemplate $labelTemplate)
    {
        $labelTemplate->delete();

        return redirect()->route('packaging.label-templates.index')
            ->with('success', __('Label template deleted.'));
    }

    public function setDefault(LabelTemplate $labelTemplate)
    {
        $labelTemplate->update(['is_default' => true]);
        $this->ensureSingleDefault($labelTemplate);

        return redirect()->route('packaging.label-templates.index')
            ->with('success', __('Default template updated.'));
    }

    /**
     * Build the view data the create/edit form needs: the resolved field
     * toggles (honoring old() input), the derived code type, the non-code
     * fields, and the initial state for the live Alpine preview.
     */
    private function formData(LabelTemplate $template): array
    {
        $fields = $template->fields_config
            ?? LabelTemplate::defaultFieldsFor($template->type ?? LabelTemplate::TYPE_WORK_ORDER);

        $initialFields = [];
        foreach (array_keys(LabelTemplate::AVAILABLE_FIELDS) as $key) {
            $initialFields[$key] = (bool) old("fields.$key", $fields[$key] ?? false);
        }

        // Code type derived from individual fields. barcode wins over qr if both set.
        $codeType = 'none';
        if (! empty($initialFields['barcode'])) {
            $codeType = 'barcode';
        } elseif (! empty($initialFields['qr'])) {
            $codeType = 'qr';
        }

        return [
            'otherFields' => collect(LabelTemplate::AVAILABLE_FIELDS)
                ->except(['barcode', 'qr'])
                ->toArray(),
            'previewInitial' => [
                'name' => old('name', $template->name ?? ''),
                'type' => old('type', $template->type ?? LabelTemplate::TYPE_WORK_ORDER),
                'size' => old('size', $template->size ?? '100x50'),
                'barcode_format' => old('barcode_format', $template->barcode_format ?? 'code128'),
                'fields' => $initialFields,
                'code_type' => $codeType,
            ],
        ];
    }

    private function validateRequest(Request $request): array
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => ['required', Rule::in(array_keys(LabelTemplate::TYPES))],
            'size' => ['required', Rule::in(array_keys(LabelTemplate::SIZES))],
            'barcode_format' => ['required', Rule::in(array_keys(LabelTemplate::BARCODE_FORMATS))],
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'fields' => 'array',
        ]);

        $fields = [];
        foreach (array_keys(LabelTemplate::AVAILABLE_FIELDS) as $key) {
            $fields[$key] = (bool) ($request->input("fields.$key"));
        }

        return [
            'name' => $request->input('name'),
            'type' => $request->input('type'),
            'size' => $request->input('size'),
            'barcode_format' => $request->input('barcode_format'),
            'fields_config' => $fields,
            'is_default' => $request->boolean('is_default'),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function ensureSingleDefault(LabelTemplate $template): void
    {
        LabelTemplate::query()
            ->where('type', $template->type)
            ->where('id', '!=', $template->id)
            ->update(['is_default' => false]);
    }
}
