<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Holding a non-conforming unit: the error code comes from the plant's scrap
 * reason list (code, name, 5M category), the note says what was seen.
 */
class BlockUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route is behind auth + role:Operator|Supervisor|Admin middleware.
    }

    public function rules(): array
    {
        return [
            'scrap_reason_id' => ['required', 'integer', Rule::exists('scrap_reasons', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
