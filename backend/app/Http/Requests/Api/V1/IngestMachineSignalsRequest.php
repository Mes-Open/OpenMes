<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class IngestMachineSignalsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    } // Gateway route enforces API authorization.

    public function rules(): array
    {
        return ['readings' => ['required', 'array', 'min:1', 'max:1000'],
            'readings.*.tag_id' => ['nullable', 'integer'], 'readings.*.node_id' => ['nullable', 'string'],
            'readings.*.value' => ['present'], 'readings.*.ts' => ['nullable', 'date'],
            'readings.*.event_id' => ['nullable', 'string', 'max:160']];
    }
}
