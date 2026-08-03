<?php

namespace App\Http\Requests\Web\Admin;

use App\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route middleware already restricts admin routes to the Admin role.
        return true;
    }

    /**
     * `kind` is NOT NULL with a DB default, but a cleared select arrives as null
     * (ConvertEmptyStringsToNull) and an explicit null would trip the constraint.
     * New warehouses are active by default; a missing checkbox on create means
     * "not filled in", not "unchecked".
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('kind') === null || $this->input('kind') === '') {
            $this->merge(['kind' => Warehouse::KIND_MIXED]);
        }

        $this->merge([
            'is_active' => $this->boolean('is_active', true),
            'is_default' => $this->boolean('is_default'),
        ]);
    }

    public function rules(): array
    {
        return [
            // Unique among live rows only — a soft-deleted warehouse frees its code.
            'code' => ['required', 'string', 'max:50', Rule::unique('warehouses', 'code')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'kind' => ['required', Rule::in(Warehouse::KINDS)],
            'erp_code' => ['nullable', 'string', 'max:100', Rule::unique('warehouses', 'erp_code')->whereNull('deleted_at')],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
