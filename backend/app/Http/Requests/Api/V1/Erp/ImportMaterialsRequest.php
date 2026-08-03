<?php

namespace App\Http\Requests\Api\V1\Erp;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an ERP material (raw material / component) master-data payload (#212).
 *
 * Same contract as the product import — ERPs keep both in one item table and tell
 * them apart by classification, so the same `only_categories` filter applies.
 */
class ImportMaterialsRequest extends FormRequest
{
    public const MAX_ROWS = 2000;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'strategy' => ['nullable', Rule::in(['update_or_create', 'skip_existing', 'error_on_duplicate'])],
            'only_categories' => ['nullable', 'array', 'max:100'],
            'only_categories.*' => ['string', 'max:100'],
            'external_system' => ['nullable', 'string', 'max:50'],

            'materials' => ['required', 'array', 'min:1', 'max:'.self::MAX_ROWS],
            'materials.*.code' => ['required', 'string', 'max:50'],
            'materials.*.name' => ['nullable', 'string', 'max:255'],
            'materials.*.description' => ['nullable', 'string', 'max:2000'],
            'materials.*.category' => ['nullable', 'string', 'max:100'],
            // Explicit OpenMES material type; defaults to the row's category.
            'materials.*.material_type_code' => ['nullable', 'string', 'max:50'],
            'materials.*.unit_of_measure' => ['nullable', 'string', 'max:20'],
            'materials.*.tracking_type' => ['nullable', Rule::in(['none', 'batch', 'serial'])],
            'materials.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'materials.*.price_currency' => ['nullable', 'string', 'max:10'],
            'materials.*.min_stock_level' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'materials.*.supplier_name' => ['nullable', 'string', 'max:255'],
            'materials.*.supplier_code' => ['nullable', 'string', 'max:100'],
            'materials.*.ean' => ['nullable', 'string', 'max:50'],
            'materials.*.external_code' => ['nullable', 'string', 'max:100'],
            'materials.*.external_system' => ['nullable', 'string', 'max:50'],
            'materials.*.is_active' => ['nullable', 'boolean'],
        ];
    }

    public function strategy(): string
    {
        return $this->input('strategy', 'update_or_create');
    }

    /** @return list<string> */
    public function onlyCategories(): array
    {
        return array_values(array_filter((array) $this->input('only_categories', [])));
    }
}
