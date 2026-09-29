<?php

namespace App\Http\Requests;

use App\Services\CustomFieldService;
use App\Services\Production\OperatorScreens;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Creating a workstation on a line (admin → line → workstations). */
class StoreWorkstationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the route group already requires the admin tab
    }

    public function rules(): array
    {
        return array_merge([
            'code' => ['required', 'string', 'max:50', 'unique:workstations,code'],
            'name' => ['required', 'string', 'max:255'],
            'workstation_type' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
            ...self::operatorScreenRules(),
        ], app(CustomFieldService::class)->rules('workstation'));
    }

    public function attributes(): array
    {
        return app(CustomFieldService::class)->attributeNames('workstation');
    }

    /** Null or an empty list = screens follow the routing; a list pins them. */
    public static function operatorScreenRules(): array
    {
        return [
            'operator_screens' => ['nullable', 'array'],
            'operator_screens.*' => ['string', Rule::in(OperatorScreens::ALL)],
            'unit_label_actions' => ['nullable', 'array'],
            'unit_label_actions.*' => ['string', Rule::in(\App\Support\UnitLabelActions::ALL)],
        ];
    }
}
