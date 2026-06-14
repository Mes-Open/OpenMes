<?php

namespace App\Http\Requests;

use App\Enums\PalletStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route is behind auth + role:Admin middleware.
    }

    public function rules(): array
    {
        $palletId = $this->route('pallet')?->id;

        return [
            'work_order_id' => ['required', 'exists:work_orders,id'],
            'qty' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::enum(PalletStatus::class)],
            'location' => ['nullable', 'string', 'max:100'],
            'erp_reference' => ['nullable', 'string', 'max:100'],
            // pallet_no is sequence-generated on create; editable but kept unique on update.
            'pallet_no' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('pallets', 'pallet_no')->ignore($palletId),
            ],
        ];
    }

    /**
     * Normalized payload. On create, pallet_no is omitted so the model draws it
     * from the sequence; qty defaults to 0.
     */
    public function payload(): array
    {
        $validated = $this->validated();
        $validated['qty'] = $validated['qty'] ?? 0;

        if (empty($validated['pallet_no'])) {
            unset($validated['pallet_no']);
        }

        return $validated;
    }
}
