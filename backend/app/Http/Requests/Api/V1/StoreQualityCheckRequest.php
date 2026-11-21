<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ValidatesQualityCheckSamples;
use App\Models\Batch;
use App\Models\QualityCheckTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Record an in-process quality check on a batch over the API. Same rule as the
 * operator page: a shop-floor role on the batch's line (Admin and Supervisor
 * on every line, an Operator on the lines assigned to them). A chosen QC
 * template must belong to the batch's process; without one the batch's default
 * template is used. The samples are judged on the server.
 */
class StoreQualityCheckRequest extends FormRequest
{
    use ValidatesQualityCheckSamples;

    public function authorize(): bool
    {
        $user = $this->user();
        $lineIds = $user?->hasAnyRole(['Admin', 'Supervisor']) ? null : $user?->lines()->pluck('lines.id');

        return $this->mayRecordQualityCheck($user, $this->batch(), $lineIds);
    }

    public function rules(): array
    {
        return [
            'production_quantity' => ['nullable', 'numeric', 'min:0'],
            'quality_check_template_id' => ['nullable', 'integer', 'exists:quality_check_templates,id'],
            'pallet_id' => ['nullable', 'integer', 'exists:pallets,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            ...$this->sampleRules(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $id = $this->input('quality_check_template_id');
            if ($id === null || $v->errors()->has('quality_check_template_id')) {
                return;
            }
            $processTemplateId = $this->batch()?->workOrder?->process_snapshot['template_id'] ?? null;
            if ((int) QualityCheckTemplate::whereKey($id)->value('process_template_id') !== (int) $processTemplateId) {
                $v->errors()->add('quality_check_template_id', __('This quality check template does not belong to the batch\'s process.'));
            }
        });
    }

    /** The chosen QC template, else the batch's default one (null when none). */
    public function template(): ?QualityCheckTemplate
    {
        $id = $this->validated()['quality_check_template_id'] ?? null;

        return $id !== null ? QualityCheckTemplate::find($id) : QualityCheckTemplate::forBatch($this->batch());
    }

    private function batch(): ?Batch
    {
        $batch = $this->route('batch');

        return $batch instanceof Batch ? $batch : null;
    }
}
