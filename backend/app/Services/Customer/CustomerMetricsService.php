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
        // Cheap pre-check to skip the transaction for the common already-counted
        // case; the authoritative guard is the locked re-check below.
        if ($workOrder->customer_totals_counted || $workOrder->customer_id === null) {
            return;
        }

        DB::transaction(function () use ($workOrder): void {
            // Re-read the order under a row lock and re-check the flag, so two
            // concurrent completions (e.g. a double "complete" submit) can't both
            // pass the guard and double-count revenue/orders.
            $locked = WorkOrder::whereKey($workOrder->getKey())->lockForUpdate()->first();
            if ($locked === null || $locked->customer_totals_counted) {
                $workOrder->customer_totals_counted = true; // keep the caller's instance in sync

                return;
            }

            $revenue = (float) $locked->produced_qty * (float) ($locked->unit_price ?? 0);

            // Mark counted regardless of the customer's state, so a soft-deleted
            // customer can't leave the order eligible to be retried forever.
            $locked->forceFill(['customer_totals_counted' => true])->saveQuietly();
            $workOrder->customer_totals_counted = true;

            $customer = Customer::whereKey($locked->customer_id)->lockForUpdate()->first();
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
