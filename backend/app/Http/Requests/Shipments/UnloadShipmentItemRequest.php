<?php

namespace App\Http\Requests\Shipments;

use Illuminate\Foundation\Http\FormRequest;

/** Undo a scan: take one loaded unit off the truck again. */
class UnloadShipmentItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('load', $this->route('shipment')) ?? false;
    }

    public function rules(): array
    {
        return [
            'workstation_id' => ['nullable', 'integer', 'exists:workstations,id'],
        ];
    }
}
