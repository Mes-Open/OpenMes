<?php

namespace App\Http\Requests\Quality;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Complete an inspection and fix its verdict (InspectionPolicy::complete).
 * Completing does not release the lot unless the user may also decide on it.
 */
class CompleteInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('complete', $this->route('inspection')) ?? false;
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
