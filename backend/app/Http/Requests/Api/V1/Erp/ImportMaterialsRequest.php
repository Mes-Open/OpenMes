<?php

namespace App\Http\Requests\Api\V1\Erp;

use App\Http\Requests\Api\V1\Erp\Concerns\SharesMasterDataRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an ERP material (raw material / component) master-data payload (#212).
 *
 * Same envelope as the product import — ERPs keep both in one item table and tell
 * them apart by classification, so the shared `only_categories` filter and row
 * fields come from SharesMasterDataRules. Only the material-specific columns
 * (tracking, price, supplier) are declared here.
 */
class ImportMaterialsRequest extends FormRequest
{
    use SharesMasterDataRules;

    public const MAX_ROWS = 2000;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...$this->envelopeRules(),
            'materials' => ['required', 'array', 'min:1', 'max:'.self::MAX_ROWS],
            ...$this->commonRowRules('materials.*'),

            // Explicit OpenMES material type; defaults to the row's category.
            'materials.*.material_type_code' => ['nullable', 'string', 'max:50'],
            'materials.*.tracking_type' => ['nullable', Rule::in(['none', 'batch', 'serial'])],
            'materials.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'materials.*.price_currency' => ['nullable', 'string', 'max:10'],
            'materials.*.min_stock_level' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'materials.*.supplier_name' => ['nullable', 'string', 'max:255'],
            'materials.*.supplier_code' => ['nullable', 'string', 'max:100'],
            'materials.*.ean' => ['nullable', 'string', 'max:50'],
        ];
    }
}
