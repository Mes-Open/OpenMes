<?php

namespace App\Http\Requests;

use App\Models\LabelTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The drawer's unsaved template values, for a preview on sample data. */
class PreviewLabelTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route is behind auth + role:Admin middleware.
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(LabelTemplate::TYPES))],
            'size' => ['required', Rule::in(array_keys(LabelTemplate::SIZES))],
            'barcode_format' => ['required', Rule::in(array_keys(LabelTemplate::BARCODE_FORMATS))],
            'fields' => ['nullable', 'array'],
        ];
    }
}
