<?php

namespace App\Http\Requests\Api\V1\Erp;

use App\Http\Requests\Api\V1\Erp\Concerns\SharesMasterDataRules;
use App\Models\MaterialLot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an ERP material-lot + available-quantity payload (#212).
 *
 * Unknown material or warehouse codes are per-row errors from the importer, not
 * a 422 — one stale code must not reject a whole nightly lot sync.
 */
class ImportMaterialLotsRequest extends FormRequest
{
    use SharesMasterDataRules;

    public const MAX_ROWS = 5000;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'strategy' => ['nullable', Rule::in(self::STRATEGIES)],
            // Applied to rows that name no warehouse of their own.
            'warehouse_code' => ['nullable', 'string', 'max:100'],

            'lots' => ['required', 'array', 'min:1', 'max:'.self::MAX_ROWS],
            'lots.*.material_code' => ['required', 'string', 'max:50'],
            'lots.*.lot_number' => ['required', 'string', 'max:100'],
            'lots.*.quantity_available' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'lots.*.quantity_received' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lots.*.unit_of_measure' => ['nullable', 'string', 'max:20'],
            'lots.*.warehouse_code' => ['nullable', 'string', 'max:100'],
            'lots.*.status' => ['nullable', Rule::in(MaterialLot::STATUSES)],
            'lots.*.received_at' => ['nullable', 'date'],
            'lots.*.manufacturing_date' => ['nullable', 'date'],
            'lots.*.expiry_date' => ['nullable', 'date'],
            'lots.*.supplier_lot_no' => ['nullable', 'string', 'max:100'],
            'lots.*.supplier_reference' => ['nullable', 'string', 'max:100'],
        ];
    }
}
