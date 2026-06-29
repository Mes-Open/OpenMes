<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Add a checklist item to a template step. Admin-only (route-gated).
 */
class StoreTemplateStepChecklistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route sits behind auth + role:Admin middleware.
    }

    public function rules(): array
    {
        return [
            'template_step_id' => ['required', 'integer', 'exists:template_steps,id'],
            'label' => ['required', 'string', 'max:500'],
            'is_required' => ['sometimes', 'boolean'],
        ];
    }
}
