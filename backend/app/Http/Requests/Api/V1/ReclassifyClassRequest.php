<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Reclassify a quantity of material from one class (material) to another (#99).
 * The route is gated to Supervisor|Admin; this request owns shape validation.
 */
class ReclassifyClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_material_id' => ['required', 'integer', 'exists:materials,id'],
            'target_material_id' => ['required', 'integer', 'different:source_material_id', 'exists:materials,id'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'source_lot_id' => ['nullable', 'integer', 'exists:material_lots,id'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
