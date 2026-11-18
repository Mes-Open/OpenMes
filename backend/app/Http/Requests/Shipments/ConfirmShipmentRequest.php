<?php

namespace App\Http\Requests\Shipments;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Let the truck go. Whether a reason is required (a short load) and whether the
 * user may confirm a short load at all depends on the load itself, so the
 * service decides that; this request bounds the input.
 */
class ConfirmShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('confirm', $this->route('shipment')) ?? false;
    }

    public function rules(): array
    {
        return [
            'short_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
