<?php

namespace App\Http\Requests;

use App\Models\TemplateStepOutput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Add a typed output definition to a template step. Admin-only (route-gated).
 */
class StoreTemplateStepOutputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route sits behind auth + role:Admin middleware.
    }

    public function rules(): array
    {
        return [
            'template_step_id' => ['required', 'integer', 'exists:template_steps,id'],
            'key' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'label' => ['required', 'string', 'max:255'],
            'value_type' => ['required', Rule::in(TemplateStepOutput::VALUE_TYPES)],
            'unit' => ['nullable', 'string', 'max:30'],
            'options' => ['nullable', 'array'],
            'options.*' => ['string', 'max:255'],
            'is_required' => ['sometimes', 'boolean'],
            'expected_min' => ['nullable', 'numeric'],
            'expected_max' => ['nullable', 'numeric', 'gte:expected_min'],
            'expected_value' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            // Explicit non-blank predicate: PHP's default array_filter would drop the
            // string "0", wrongly rejecting a select whose only option is "0".
            if ($this->input('value_type') === TemplateStepOutput::TYPE_SELECT
                && empty(array_filter(
                    (array) $this->input('options', []),
                    static fn ($option) => trim((string) $option) !== '',
                ))) {
                $v->errors()->add('options', __('A select output needs at least one option.'));
            }
        });
    }
}
