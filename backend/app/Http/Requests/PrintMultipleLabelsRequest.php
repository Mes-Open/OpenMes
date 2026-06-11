<?php

namespace App\Http\Requests;

use App\Models\LabelTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrintMultipleLabelsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route is behind auth + role:Operator|Supervisor|Admin middleware.
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_keys(LabelTemplate::TYPES))],
            'format' => ['required', Rule::in(['pdf', 'zpl'])],
            'template_id' => 'nullable|integer|exists:label_templates,id',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ];
    }
}
