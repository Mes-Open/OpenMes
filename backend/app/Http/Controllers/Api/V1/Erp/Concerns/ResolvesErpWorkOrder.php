<?php

namespace App\Http\Controllers\Api\V1\Erp\Concerns;

use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Finds the work order an ERP endpoint addresses — by id, or by order number.
 *
 * Resolved in the controller and never through route-model binding: binding runs
 * before the API-key middleware sets the tenant, so it could hand back another
 * tenant's order (see ProductionExportController::show()). Here the tenant scope is
 * always in force, and an order of another tenant is simply not found.
 */
trait ResolvesErpWorkOrder
{
    /**
     * @param  'id'|'order_no'|string  $by
     *
     * @throws ModelNotFoundException
     */
    protected function resolveWorkOrder(string $value, string $by, array $with = []): WorkOrder
    {
        $query = WorkOrder::query()->with($with);

        if ($by === 'order_no') {
            return $query->where('order_no', $value)->firstOrFail();
        }

        // A non-numeric id can never match; answer like any other unknown order
        // instead of letting the database reject the cast.
        if (! ctype_digit($value)) {
            throw (new ModelNotFoundException)->setModel(WorkOrder::class, [$value]);
        }

        return $query->findOrFail((int) $value);
    }
}
