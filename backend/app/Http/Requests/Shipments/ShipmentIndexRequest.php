<?php

namespace App\Http\Requests\Shipments;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Filters for the REST shipment list. */
class ShipmentIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Shipment::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(ShipmentStatus::values())],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
