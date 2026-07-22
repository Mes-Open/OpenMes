<?php

namespace App\Http\Requests\Web\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $revision = $this->route('product_revision');

        return [
            // product_type is fixed for the life of the revision — not editable.
            'revision_code' => [
                'required', 'string', 'max:50',
                'regex:/^[A-Za-z0-9.\-]+$/',
                Rule::unique('product_revisions', 'revision_code')
                    ->where('product_type_id', $revision->product_type_id)
                    ->whereNull('deleted_at')
                    ->ignore($revision->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'process_template_id' => [
                'nullable',
                Rule::exists('process_templates', 'id')
                    ->where('product_type_id', $revision->product_type_id)
                    ->whereNull('deleted_at'),
            ],
            'change_reason' => ['nullable', 'string', 'max:255'],
            'external_ref' => ['nullable', 'string', 'max:255'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }
}
