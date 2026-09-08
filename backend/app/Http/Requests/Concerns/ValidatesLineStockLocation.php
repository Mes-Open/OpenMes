<?php

namespace App\Http\Requests\Concerns;

use App\Models\Warehouse;
use Illuminate\Validation\Rule;

/**
 * The rule set behind a line's stock location, shared by create and edit.
 *
 * It has to match what the form actually offers (LineManagementController::
 * warehouseOptions): a live, active warehouse that may hold materials. A line
 * draws components, never finished goods, and pointing one at an archived or
 * finished-goods store would send every consumption deduction somewhere that
 * cannot answer for it.
 *
 * `Rule::exists` queries the table directly and so bypasses the model's global
 * TenantScope — the tenant clause below is what keeps one tenant from naming
 * another tenant's warehouse by id.
 */
trait ValidatesLineStockLocation
{
    /** @return array<int, mixed> */
    protected function stockLocationRules(): array
    {
        return [
            'nullable',
            'integer',
            Rule::exists('warehouses', 'id')
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->whereIn('kind', [Warehouse::KIND_RAW_MATERIAL, Warehouse::KIND_MIXED])
                ->where(function ($query) {
                    // Mirrors TenantScope exactly: scope to the tenant when there is
                    // one, and to nothing when there is not. Rejecting outright on a
                    // null tenant would break every single-tenant install — tenancy is
                    // dormant there, so users and warehouses both carry a null
                    // tenant_id and the picker offers all of them.
                    $tenantId = $this->user()?->tenant_id;

                    if ($tenantId) {
                        $query->where('tenant_id', $tenantId);
                    }
                }),
        ];
    }
}
