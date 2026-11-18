<?php

namespace App\Http\Requests\Shipments;

use Illuminate\Foundation\Http\FormRequest;

/** Change a shipment's header and (optionally) replace its expected list. */
class UpdateShipmentRequest extends FormRequest
{
    use ShipmentRules;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('shipment')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->expandItemsText();
    }

    public function rules(): array
    {
        return $this->headerRules($this->route('shipment')) + $this->itemRules(required: false);
    }
}
