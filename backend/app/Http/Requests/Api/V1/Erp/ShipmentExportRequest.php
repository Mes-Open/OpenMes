<?php

namespace App\Http\Requests\Api\V1\Erp;

use App\Enums\ShipmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters for the ERP shipment export (#400). An ERP normally polls
 * `?since=<last sync>&status=confirmed`. Authorization is the auth.apikey +
 * scope middleware.
 */
class ShipmentExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'since' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(ShipmentStatus::values())],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            'cursor' => ['nullable', 'string'],
        ];
    }

    public function perPage(): int
    {
        $limit = (int) $this->input('limit');

        return $limit > 0 ? $limit : 50;
    }
}
