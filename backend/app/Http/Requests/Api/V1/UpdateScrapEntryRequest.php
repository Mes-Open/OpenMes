<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateScrapEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('scrapEntry')) ?? false;
    }

    public function rules(): array
    {
        return [
            'scrap_reason_id' => ['sometimes', 'integer', Rule::exists('scrap_reasons', 'id')->where('is_active', true)],
            'quantity' => ['sometimes', 'numeric', 'min:0.01', 'max:99999999'],
            'batch_step_id' => ['sometimes', 'nullable', 'integer', 'exists:batch_steps,id'],
            'shift_id' => ['sometimes', 'nullable', 'integer', 'exists:shifts,id'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
