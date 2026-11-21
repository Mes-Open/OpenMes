<?php

namespace App\Http\Requests\Operator;

use App\Http\Requests\Concerns\ValidatesQualityCheckSamples;
use App\Models\Batch;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Operator records an in-process quality check on a batch from the work-order
 * page. Allowed on the operator's selected line only; the samples are judged
 * on the server against the batch's QC template.
 */
class RecordQualityCheckRequest extends FormRequest
{
    use ValidatesQualityCheckSamples;

    public function authorize(): bool
    {
        /** @var Batch|null $batch */
        $batch = $this->route('batch');
        $selected = $this->session()->get('selected_line_id');

        return $selected !== null && $this->mayRecordQualityCheck($this->user(), $batch, [$selected]);
    }

    public function rules(): array
    {
        return [
            'production_quantity' => ['nullable', 'numeric', 'min:0'],
            'pallet_id' => ['nullable', 'integer', 'exists:pallets,id'],
            ...$this->sampleRules(),
        ];
    }
}
