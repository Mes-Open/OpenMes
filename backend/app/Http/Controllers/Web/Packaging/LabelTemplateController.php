<?php

namespace App\Http\Controllers\Web\Packaging;

use App\Http\Controllers\Concerns\StaysOnList;
use App\Http\Controllers\Controller;
use App\Models\LabelTemplate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Label templates: a list that creates and edits in a drawer, like the other admin lists. */
class LabelTemplateController extends Controller
{
    use StaysOnList;

    public function index(Request $request)
    {
        // /create and /{id}/edit land here as ?create=1 / ?edit={id}: the page
        // opens its drawer the same way a row's Edit or "New template" does.
        $editTemplate = $request->integer('edit')
            ? LabelTemplate::find($request->integer('edit'))?->only('id', 'name', 'type', 'size', 'barcode_format', 'fields_config', 'is_default', 'is_active')
            : null;

        return Inertia::render('packaging/label-templates/Index', [
            'typeLabels' => LabelTemplate::TYPES,
            'editTemplate' => $editTemplate,
            'openCreate' => $request->boolean('create'),
            // The drawer's option lists, loaded on its first opening (partial
            // reload) rather than with every render of the list.
            'types' => Inertia::optional(fn () => LabelTemplate::TYPES),
            'sizes' => Inertia::optional(fn () => LabelTemplate::SIZES),
            'barcodeFormats' => Inertia::optional(fn () => LabelTemplate::BARCODE_FORMATS),
            'availableFields' => Inertia::optional(fn () => LabelTemplate::AVAILABLE_FIELDS),
            'fieldsByType' => Inertia::optional(fn () => collect(array_keys(LabelTemplate::TYPES))
                ->mapWithKeys(fn ($type) => [$type => LabelTemplate::fieldsForType($type)])
                ->all()),
            // Which fields a fresh template of each type starts with.
            'defaultFieldsByType' => Inertia::optional(fn () => collect(array_keys(LabelTemplate::TYPES))
                ->mapWithKeys(fn ($type) => [$type => LabelTemplate::defaultFieldsFor($type)])
                ->all()),
        ]);
    }

    public function create()
    {
        return redirect()->route('packaging.label-templates.index', ['create' => 1]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);

        $template = LabelTemplate::create($validated);

        if ($template->is_default) {
            $this->ensureSingleDefault($template);
        }

        return $this->saved($request, redirect()->route('packaging.label-templates.index'), __('Label template created.'));
    }

    public function edit(LabelTemplate $labelTemplate)
    {
        return redirect()->route('packaging.label-templates.index', ['edit' => $labelTemplate->id]);
    }

    public function update(Request $request, LabelTemplate $labelTemplate)
    {
        $validated = $this->validateRequest($request);

        $labelTemplate->update($validated);

        if ($labelTemplate->is_default) {
            $this->ensureSingleDefault($labelTemplate);
        }

        return $this->saved($request, redirect()->route('packaging.label-templates.index'), __('Label template updated.'));
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
