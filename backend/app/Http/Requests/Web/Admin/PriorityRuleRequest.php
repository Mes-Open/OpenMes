<?php

namespace App\Http\Requests\Web\Admin;

use App\Enums\PriorityCondition;
use App\Enums\PriorityRuleSource;
use App\Enums\Tier;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class PriorityRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'field_source' => ['required', new Enum(PriorityRuleSource::class)],
            'condition_type' => ['required', new Enum(PriorityCondition::class)],
            // Value is required for every condition except "is true".
            'condition_value' => [
                Rule::requiredIf(fn () => $this->input('condition_type') !== PriorityCondition::IsTrue->value),
                'nullable', 'string', 'max:100',
            ],
            // Upper bound only for "between".
            'condition_value_max' => [
                Rule::requiredIf(fn () => $this->input('condition_type') === PriorityCondition::Between->value),
                'nullable', 'string', 'max:100',
            ],
            'points' => ['required', 'integer', 'min:-1000', 'max:1000'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $source = $this->input('field_source');
            $condition = $this->input('condition_type');
            $value = $this->input('condition_value');

            // A tier comparison must reference a real tier.
            if ($source === PriorityRuleSource::CustomerTier->value
                && in_array($condition, [PriorityCondition::Equals->value], true)
                && $value !== null
                && ! in_array($value, Tier::values(), true)) {
                $validator->errors()->add('condition_value', __('The value must be a valid tier: :tiers.', ['tiers' => implode(', ', Tier::values())]));
            }

            // Numeric sources need a numeric comparison value (and upper bound).
            $numericSource = $source !== null && $source !== PriorityRuleSource::CustomerTier->value;
            $needsNumber = ! in_array($condition, [PriorityCondition::IsTrue->value], true);

            if ($numericSource && $needsNumber && $value !== null && ! is_numeric($value)) {
                $validator->errors()->add('condition_value', __('The value must be a number.'));
            }

            if ($condition === PriorityCondition::Between->value) {
                $max = $this->input('condition_value_max');
                if ($numericSource && $max !== null && ! is_numeric($max)) {
                    $validator->errors()->add('condition_value_max', __('The value must be a number.'));
                } elseif (is_numeric($value) && is_numeric($max) && (float) $max <= (float) $value) {
                    $validator->errors()->add('condition_value_max', __('The upper bound must be greater than the lower bound.'));
                }
            }
        });
    }
}
