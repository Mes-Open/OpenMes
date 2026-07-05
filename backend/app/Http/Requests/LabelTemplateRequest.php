<?php

namespace App\Http\Requests;

use App\Models\LabelTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates create/update of a label template for the mobile API. Mirrors the
 * web Packaging\LabelTemplateController rules: name + type + size + barcode
 * format, plus a boolean map of which fields appear on the label. Authorization
 * is the route's role:Admin|Supervisor middleware.
 */
class LabelTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(LabelTemplate::TYPES))],
            'size' => ['required', Rule::in(array_keys(LabelTemplate::SIZES))],
            'barcode_format' => ['required', Rule::in(array_keys(LabelTemplate::BARCODE_FORMATS))],
            'fields_config' => ['nullable', 'array'],
            'fields_config.*' => ['boolean'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** Normalise fields_config to a full boolean map over the known field keys. */
    public function fieldsConfig(): array
    {
        $in = (array) $this->input('fields_config', []);
        $out = [];
        foreach (array_keys(LabelTemplate::AVAILABLE_FIELDS) as $key) {
            $out[$key] = (bool) ($in[$key] ?? false);
        }

        return $out;
    }
}
