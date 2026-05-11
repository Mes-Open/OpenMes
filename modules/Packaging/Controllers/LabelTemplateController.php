<?php

namespace Modules\Packaging\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Packaging\Models\LabelTemplate;

class LabelTemplateController extends Controller
{
    public function index()
    {
        $templates = LabelTemplate::query()
            ->orderBy('type')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return view('packaging::label-templates.index', compact('templates'));
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

        return view('packaging::label-templates.create', compact('template'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);

        $template = LabelTemplate::create($validated);

        if ($template->is_default) {
            $this->ensureSingleDefault($template);
        }

        return redirect()->route('packaging.label-templates.index')
            ->with('success', 'Label template created.');
    }

    public function edit(LabelTemplate $labelTemplate)
    {
        return view('packaging::label-templates.edit', ['template' => $labelTemplate]);
    }

    public function update(Request $request, LabelTemplate $labelTemplate)
    {
        $validated = $this->validateRequest($request);

        $labelTemplate->update($validated);

        if ($labelTemplate->is_default) {
            $this->ensureSingleDefault($labelTemplate);
        }

        return redirect()->route('packaging.label-templates.index')
            ->with('success', 'Label template updated.');
    }

    public function destroy(LabelTemplate $labelTemplate)
    {
        $labelTemplate->delete();

        return redirect()->route('packaging.label-templates.index')
            ->with('success', 'Label template deleted.');
    }

    public function setDefault(LabelTemplate $labelTemplate)
    {
        $labelTemplate->update(['is_default' => true]);
        $this->ensureSingleDefault($labelTemplate);

        return redirect()->route('packaging.label-templates.index')
            ->with('success', 'Default template updated.');
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
