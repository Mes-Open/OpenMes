<?php

namespace App\Http\Requests\Api\V1\Erp;

use App\Http\Requests\Shipments\ShipmentRules;
use App\Models\Shipment;
use Illuminate\Foundation\Http\FormRequest;

/**
 * ERP → OpenMES: one load and the pallets / cartons it must carry (#400).
 *
 * Sending a shipment_no that already exists replaces that shipment while it is
 * still planned (idempotent re-send), so the number is not checked for
 * uniqueness here — the controller decides between create and replace.
 * Authorization is the auth.apikey + scope middleware.
 */
class StoreErpShipmentRequest extends FormRequest
{
    use ShipmentRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = $this->headerRules() + $this->itemRules();
        $rules['shipment_no'] = ['required', 'string', 'max:'.Shipment::IDENTIFIER_MAX, 'regex:'.Shipment::IDENTIFIER_REGEX];
        unset($rules['items_text'], $rules['default_unit_type']);

        return $rules;
    }
}
