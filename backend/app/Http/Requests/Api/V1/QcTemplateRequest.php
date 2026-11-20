<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\AllowsModuleFields;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Create (POST /process-templates/{id}/qc-templates) or edit
 * (PATCH /qc-templates/{id}) a quality control template over the API.
 *
 * The rule set goes through the filter `validation.api.qc_templates`, so a
 * module that keeps its own rules for control templates (e.g. limits: min not
 * above max, no limits on a pass / fail parameter) applies them on the API too,
 * not only on its own screen. Context: `action` (store / update) and
 * `template` (the edited QualityCheckTemplate, null on create).
 */
class QcTemplateRequest extends FormRequest
{
    use AllowsModuleFields;

    public function authorize(): bool
    {
        return true; // route middleware gates the API group
    }

    protected function moduleFieldFilter(): string
    {
        return 'validation.api.qc_templates';
    }

    protected function moduleFieldContext(): array
    {
        return ['action' => $this->moduleFieldAction(), 'template' => $this->route('qualityCheckTemplate')];
    }

    public function rules(): array
    {
        $creating = $this->moduleFieldAction() === 'store';

        return $this->withModuleFields([
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:100'],
            'min_checks_per_batch' => ['nullable', 'integer', 'min:1'],
            'min_checks_per_day' => ['nullable', 'integer', 'min:1'],
            'samples_per_check' => ['nullable', 'integer', 'min:1'],
            'parameters' => [$creating ? 'required' : 'sometimes', 'array', 'min:1'],
            'parameters.*.name' => ['required', 'string', 'max:100'],
            'parameters.*.type' => ['required', 'string', 'in:measurement,pass_fail'],
            'parameters.*.unit' => ['nullable', 'string', 'max:20'],
            'parameters.*.min' => ['nullable', 'numeric'],
            'parameters.*.max' => ['nullable', 'numeric'],
        ]);
    }
}
