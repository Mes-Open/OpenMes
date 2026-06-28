<?php

namespace App\Http\Requests\BatchStepDocument;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Attach a document to a production step and optionally mark it mandatory /
 * validatable for shop-floor document control. Supervisor/Admin only.
 */
class StoreBatchStepDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasAnyRole(['Supervisor', 'Admin']);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'is_mandatory' => ['sometimes', 'boolean'],
            'requires_validation' => ['sometimes', 'boolean'],
            'file' => ['nullable', 'file', 'max:20480'], // 20 MB
        ];
    }
}
