<?php

namespace App\Http\Requests\Quality;

use App\Models\Inspection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Decide on an inspected lot — accept, quarantine, scrap, …
 * (InspectionPolicy::disposition). This is what puts material into stock, so
 * it needs the disposition right, not just the inspector one.
 */
class InspectionDispositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('disposition', $this->route('inspection')) ?? false;
    }

    public function rules(): array
    {
        return [
            'disposition' => ['required', 'string', Rule::in(array_values(array_filter(
                Inspection::DISPOSITIONS,
                fn ($d) => $d !== Inspection::DISPOSITION_PENDING,
            )))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
