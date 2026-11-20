<?php

namespace App\Http\Requests\Shipments;

use App\Models\Shipment;
use Illuminate\Foundation\Http\FormRequest;

/** A new shipment with its expected list — web form and REST API. */
class StoreShipmentRequest extends FormRequest
{
    use ShipmentRules;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Shipment::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->expandItemsText();
    }

    public function rules(): array
    {
        return $this->headerRules() + $this->itemRules();
    }
}
