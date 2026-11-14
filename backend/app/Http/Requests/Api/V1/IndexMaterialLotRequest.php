<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Query parameters of GET /api/v1/material-lots. Only `lot_number` is
 * validated; the older filters (material_id, status, supplier_lot_no,
 * expiring_within_days, available_only, per_page) are read as before.
 */
class IndexMaterialLotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Any authenticated user may list lots (auth:sanctum).
    }

    public function rules(): array
    {
        return [
            'lot_number' => ['nullable', 'string', 'max:255'],
        ];
    }
}
