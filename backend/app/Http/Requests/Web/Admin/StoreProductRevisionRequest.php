<?php

namespace App\Http\Requests\Web\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_type_id' => ['required', Rule::exists('product_types', 'id')->whereNull('deleted_at')],
            'revision_code' => [
                'required', 'string', 'max:50',
                // Letters, digits, dot, hyphen — e.g. A, 01, C.2, REV-3.
                'regex:/^[A-Za-z0-9.\-]+$/',
                // Unique per product type among live rows only (partial index).
                Rule::unique('product_revisions', 'revision_code')
                    ->where('product_type_id', $this->input('product_type_id'))
                    ->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'process_template_id' => [
                'nullable',
                Rule::exists('process_templates', 'id')
                    ->where('product_type_id', $this->input('product_type_id'))
                    ->whereNull('deleted_at'),
            ],
            'change_reason' => ['nullable', 'string', 'max:255'],
            'external_ref' => ['nullable', 'string', 'max:255'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }
}
