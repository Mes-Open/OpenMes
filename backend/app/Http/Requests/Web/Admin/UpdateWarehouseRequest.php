<?php

namespace App\Http\Requests\Web\Admin;

use App\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * See StoreWarehouseRequest — same null-coercion. On update a missing
     * checkbox genuinely means unchecked.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('kind') === null || $this->input('kind') === '') {
            $this->merge(['kind' => Warehouse::KIND_MIXED]);
        }

        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'is_default' => $this->boolean('is_default'),
        ]);
    }

    public function rules(): array
    {
        $id = $this->route('warehouse')?->id;

        return [
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('warehouses', 'code')
                    ->whereNull('deleted_at')
                    ->where('tenant_id', $this->tenantId())
                    ->ignore($id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'kind' => ['required', Rule::in(Warehouse::KINDS)],
            'erp_code' => [
                'nullable', 'string', 'max:100',
                Rule::unique('warehouses', 'erp_code')
                    ->whereNull('deleted_at')
                    ->where('tenant_id', $this->tenantId())
                    ->ignore($id),
            ],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Tenant the uniqueness rules must be scoped to. Rule::unique bypasses the
     * model's global TenantScope, so without this a code taken by another tenant
     * would block this one — the DB index is per tenant.
     */
    private function tenantId(): ?int
    {
        return $this->user()?->tenant_id;
    }
}
