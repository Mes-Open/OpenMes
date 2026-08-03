<?php

namespace App\Http\Requests\Api\V1\Erp;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an ERP recipe (bill of materials) payload (#212).
 *
 * Component quantities are per ONE unit of the finished product, matching how
 * ERPs store recipes and how bom_items.quantity_per_unit is defined.
 */
class ImportBomsRequest extends FormRequest
{
    public const MAX_ROWS = 500;

    public const MAX_COMPONENTS = 200;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // replace = the payload becomes the template's full component list;
            // merge = only the listed components are upserted.
            'mode' => ['nullable', Rule::in(['replace', 'merge'])],

            'recipes' => ['required', 'array', 'min:1', 'max:'.self::MAX_ROWS],
            'recipes.*.product_type_code' => ['required', 'string', 'max:50'],
            'recipes.*.process_template_version' => ['nullable', 'integer', 'min:1'],
            'recipes.*.components' => ['required', 'array', 'min:1', 'max:'.self::MAX_COMPONENTS],
            'recipes.*.components.*.material_code' => ['required', 'string', 'max:50'],
            'recipes.*.components.*.quantity_per_unit' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'recipes.*.components.*.scrap_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'recipes.*.components.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'recipes.*.components.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function mode(): string
    {
        return $this->input('mode', 'replace');
    }
}
