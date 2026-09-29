<?php

namespace App\Http\Controllers\Web\Packaging;

use App\Http\Controllers\Controller;
use App\Http\Requests\PreviewLabelTemplateRequest;
use App\Models\LabelTemplate;
use App\Services\Packaging\LabelGenerator;

/**
 * What a label template prints, on sample data - so a template is checked
 * before a real pallet or unit exists for it. Two doors: a saved template by
 * id, and the drawer's unsaved field values, so edits are seen before saving.
 */
class LabelTemplatePreviewController extends Controller
{
    public function __construct(private readonly LabelGenerator $generator) {}

    public function show(LabelTemplate $labelTemplate)
    {
        return $this->generator->pdfPreview($labelTemplate)->stream("label-preview-{$labelTemplate->id}.pdf");
    }

    /** The form's current values, rendered without saving them. */
    public function draft(PreviewLabelTemplateRequest $request)
    {
        $data = $request->validated();
        $fields = [];
        foreach (array_keys(LabelTemplate::AVAILABLE_FIELDS) as $key) {
            $fields[$key] = filter_var($data['fields'][$key] ?? false, FILTER_VALIDATE_BOOLEAN);
        }
        $template = new LabelTemplate([
            'name' => $data['name'] ?? __('Preview'),
            'type' => $data['type'],
            'size' => $data['size'],
            'barcode_format' => $data['barcode_format'],
            'fields_config' => $fields,
        ]);

        return $this->generator->pdfPreview($template)->stream('label-preview.pdf');
    }
}
