<?php

namespace App\Services\Customer;

use App\Models\Customer;
use App\Models\WorkOrder;
use App\Support\TierPromotionRegistry;
use Illuminate\Support\Facades\DB;

/**
 * Maintains a customer's aggregate metrics. When a work order completes it adds
 * one to the customer's order count and the order's value (produced_qty ×
 * unit_price) to its revenue, then auto-promotes the tier by the configured
 * thresholds (upgrade-only).
 *
 * Idempotent per work order: the order is flagged `customer_totals_counted` so
 * a reopen-then-complete cycle never double-counts.
 */
class CustomerMetricsService
{
    public function recordCompletion(WorkOrder $workOrder): void
    {
        if ($workOrder->customer_totals_counted || $workOrder->customer_id === null) {
            return;
        }

        $revenue = (float) $workOrder->produced_qty * (float) ($workOrder->unit_price ?? 0);

        DB::transaction(function () use ($workOrder, $revenue): void {
            $customer = Customer::whereKey($workOrder->customer_id)->lockForUpdate()->first();

            // Mark the order counted regardless, so a deleted/soft-deleted
            // customer can't cause it to be retried forever.
            $workOrder->forceFill(['customer_totals_counted' => true])->saveQuietly();

            if ($customer === null) {
                return;
            }

            $customer->total_orders += 1;
            $customer->total_revenue = (float) $customer->total_revenue + $revenue;

            $target = TierPromotionRegistry::tierForOrders($customer->total_orders);
            if ($target->rank() > $customer->tier->rank()) {
                $customer->tier = $target;
            }

            // Saving fires Customer::updated, which re-scores the customer's
            // other active orders when the tier changes (see the model hook).
            $customer->save();
        });
    }
}
