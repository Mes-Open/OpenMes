<?php

namespace App\Http\Requests\Operator;

use App\Http\Requests\Concerns\ValidatesQualityCheckSamples;
use App\Models\Batch;
use App\Models\QualityCheckTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

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
        $selected = $this->session()->get('selected_line_id');

        return $selected !== null && $this->mayRecordQualityCheck($this->user(), $this->batch(), [$selected]);
    }

    /**
     * Off the selected line (or with none selected) the operator is sent back
     * with an error, like the other operator actions; a user without a
     * shop-floor role still gets a 403.
     */
    protected function failedAuthorization(): void
    {
        if ($this->mayRecordQualityCheck($this->user(), $this->batch(), null)) {
            throw new HttpResponseException(
                back()->with('error', __('This batch does not belong to the selected line.'))
            );
        }

        parent::failedAuthorization();
    }

    public function rules(): array
    {
        return [
            'production_quantity' => ['nullable', 'numeric', 'min:0'],
            'pallet_id' => ['nullable', 'integer', 'exists:pallets,id'],
            ...$this->sampleRules($this->batch() ? QualityCheckTemplate::forBatch($this->batch()) : null),
        ];
    }

    private function batch(): ?Batch
    {
        $batch = $this->route('batch');

        return $batch instanceof Batch ? $batch : null;
    }
}
