<?php

namespace App\Http\Requests;

use App\Support\UnitSerialisation;
use Illuminate\Foundation\Http\FormRequest;

/** A process serial scanned at packing, normalised the same way it was stored. */
class ScanUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route is behind auth + role:Operator|Supervisor|Admin middleware.
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['psn' => app(UnitSerialisation::class)->normalize($this->input('psn'))]);
    }

    public function rules(): array
    {
        return [
            'psn' => ['required', 'string', 'max:100'],
            // Where the unit goes: the carton being filled and/or the pallet it lands on.
            'carton_id' => ['nullable', 'integer', 'exists:unit_cartons,id'],
            'pallet_id' => ['nullable', 'integer', 'exists:pallets,id'],
            // No carton given but the order packs into cartons: open one for the
            // operator (or reuse their open one) instead of dropping the unit loose.
            'auto_carton' => ['nullable', 'boolean'],
            // The unit's weight off the scale, when the packing step checks it.
            'weight_g' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
        ];
    }
}
