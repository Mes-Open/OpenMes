<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesQualityCheckSamples;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Record the samples of a due quality control. The samples are judged on the
 * server against the control's QC template (no client verdict is accepted).
 */
class PerformQualityControlTaskRequest extends FormRequest
{
    use ValidatesQualityCheckSamples;

    public function authorize(): bool
    {
        return true; // route is gated by the supervisor/admin middleware
    }

    public function rules(): array
    {
        return [
            'production_quantity' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'pallet_id' => ['nullable', 'integer', 'exists:pallets,id'],
            ...$this->sampleRules(),
        ];
    }
}
