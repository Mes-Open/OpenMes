<?php

namespace App\Http\Requests\Web\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** Editing a BOM line: its quantity, basis and when it is consumed. */
class UpdateBomItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the admin route group already requires the role
    }

    public function rules(): array
    {
        return [
            'template_step_id' => 'nullable|exists:template_steps,id',
            'quantity_per_unit' => 'required|numeric|gt:0',
            'per' => 'nullable|in:unit,carton,pallet',
            'scrap_percentage' => 'nullable|numeric|min:0|max:100',
            'consumed_at' => 'nullable|in:start,during,end',
            'notes' => 'nullable|string',
        ];
    }
}
