<?php

namespace App\Http\Requests\Api\V1\Erp;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an ERP warehouse-balance snapshot (#212).
 *
 * Each row names exactly one item — a material or a product type — which the
 * importer enforces per row so a mixed payload reports the offending rows instead
 * of failing wholesale.
 */
class ImportStockRequest extends FormRequest
{
    public const MAX_ROWS = 5000;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Applied to rows that name no warehouse of their own.
            'warehouse_code' => ['nullable', 'string', 'max:100'],

            'balances' => ['required', 'array', 'min:1', 'max:'.self::MAX_ROWS],
            'balances.*.warehouse_code' => ['nullable', 'string', 'max:100'],
            'balances.*.material_code' => ['nullable', 'string', 'max:50'],
            'balances.*.product_type_code' => ['nullable', 'string', 'max:50'],
            'balances.*.quantity' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'balances.*.unit_of_measure' => ['nullable', 'string', 'max:20'],
        ];
    }
}
