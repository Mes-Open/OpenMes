<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** A sub-assembly's own serial, scanned where it is made (operator label station). */
class RegisterSubassemblyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route is behind role:Operator|Supervisor|Admin.
    }

    public function rules(): array
    {
        return [
            'work_order_id' => ['required', 'integer', 'exists:work_orders,id'],
            'material_id' => ['required', 'integer', 'exists:materials,id'],
            // The sub-assembly's own format - not the product's serial pattern.
            'serial_no' => ['required', 'string', 'max:100'],
        ];
    }
}
