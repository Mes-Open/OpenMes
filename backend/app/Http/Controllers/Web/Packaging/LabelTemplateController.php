<?php

namespace App\Http\Controllers\Web\Packaging;

use App\Http\Controllers\Controller;
use App\Models\LabelTemplate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class LabelTemplateController extends Controller
{
    public function index()
    {
        return Inertia::render('packaging/label-templates/Index', [
            'typeLabels' => LabelTemplate::TYPES,
        ]);
    }

    /** Option maps shared by the create/edit forms. */
    private function formData(): array
    {
        return [
            'types' => LabelTemplate::TYPES,
            'sizes' => LabelTemplate::SIZES,
            'barcodeFormats' => LabelTemplate::BARCODE_FORMATS,
            'availableFields' => LabelTemplate::AVAILABLE_FIELDS,
        ];
    }

    public function create()
    {
        return Inertia::render('packaging/label-templates/Create', array_merge($this->formData(), [
            'defaultFields' => LabelTemplate::defaultFieldsFor(LabelTemplate::TYPE_WORK_ORDER),
        ]));
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
        return Inertia::render('packaging/label-templates/Edit', array_merge($this->formData(), [
            'template' => $labelTemplate->only('id', 'name', 'type', 'size', 'barcode_format', 'fields_config', 'is_default', 'is_active'),
        ]));
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
