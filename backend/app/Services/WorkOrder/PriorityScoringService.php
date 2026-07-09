<?php

namespace App\Services\WorkOrder;

use App\Enums\PriorityCondition;
use App\Enums\PriorityRuleSource;
use App\Models\Customer;
use App\Models\PriorityRule;
use App\Models\WorkOrder;
use App\Support\PriorityBandRegistry;
use Illuminate\Support\Collection;

/**
 * Computes a work order's priority from the configurable rules: it sums the
 * points of every active rule that matches, then maps that score to a 1–5
 * priority via PriorityBandRegistry.
 *
 * Scoring is opt-in — when no active rules exist the work order is left
 * completely untouched, so shipping this feature never overwrites manually
 * assigned priorities until an admin actually configures rules.
 */
class PriorityScoringService
{
    /** @var Collection<int, PriorityRule>|null Memoized active rule set. */
    private ?Collection $rulesCache = null;

    /**
     * Recompute and set `priority_score` + `priority` on the work order.
     *
     * @param  bool  $persist  true → saveQuietly() (bypassing model events);
     *                         false → only mutate the attributes, for use inside
     *                         the model's own saving hook where a save is pending.
     */
    public function apply(WorkOrder $workOrder, bool $persist = true): void
    {
        $rules = $this->activeRules();

        if ($rules->isEmpty()) {
            // Scoring is not configured. Clear any stale computed score (so
            // removing all rules resets the ranking) but leave `priority`
            // untouched, restoring manual control of it.
            if ($workOrder->priority_score !== 0) {
                $workOrder->priority_score = 0;
                if ($persist && $workOrder->exists) {
                    $workOrder->saveQuietly();
                }
            }

            return;
        }

        $score = $this->scoreFrom($workOrder, $rules);
        $workOrder->priority_score = $score;
        $workOrder->priority = PriorityBandRegistry::priorityForScore($score);

        if ($persist && $workOrder->exists) {
            $workOrder->saveQuietly();
        }
    }

    /** The raw summed score for a work order (0 when no rules match/exist). */
    public function scoreFor(WorkOrder $workOrder): int
    {
        return $this->scoreFrom($workOrder, $this->activeRules());
    }

    /** @param  Collection<int, PriorityRule>  $rules */
    private function scoreFrom(WorkOrder $workOrder, Collection $rules): int
    {
        $customer = $this->customerFor($workOrder);
        $score = 0;

        foreach ($rules as $rule) {
            if ($this->matches($rule, $workOrder, $customer)) {
                $score += $rule->points;
            }
        }

        return $score;
    }

    /**
     * Active rules, memoized for the lifetime of this service instance so a
     * recalculation loop (RecalculatePriorityCommand / Customer::updated) issues
     * one query instead of one per order. Instance-scoped — never a container
     * singleton — to stay correct under long-lived Octane workers.
     *
     * @return Collection<int, PriorityRule>
     */
    private function activeRules(): Collection
    {
        return $this->rulesCache ??= PriorityRule::query()->active()->ordered()->get();
    }

    private function customerFor(WorkOrder $workOrder): ?Customer
    {
        if ($workOrder->customer_id === null) {
            return null;
        }

        if ($workOrder->relationLoaded('customer')) {
            return $workOrder->customer;
        }

        return Customer::find($workOrder->customer_id);
    }

    private function matches(PriorityRule $rule, WorkOrder $workOrder, ?Customer $customer): bool
    {
        $actual = $this->sourceValue($rule->field_source, $workOrder, $customer);

        // A missing value never matches — e.g. a customer rule on an order with
        // no customer, or a due-date rule on an order without a due date.
        if ($actual === null) {
            return false;
        }

        return match ($rule->condition_type) {
            PriorityCondition::IsTrue => (bool) $actual,
            PriorityCondition::Equals => $this->equals($rule, $actual),
            PriorityCondition::GreaterThan => (float) $actual > (float) $rule->condition_value,
            PriorityCondition::LessThan => (float) $actual < (float) $rule->condition_value,
            PriorityCondition::Between => (float) $actual >= (float) $rule->condition_value
                && (float) $actual <= (float) $rule->condition_value_max,
        };
    }

    private function equals(PriorityRule $rule, string|int|float $actual): bool
    {
        if ($rule->field_source->isTextual()) {
            return (string) $actual === (string) $rule->condition_value;
        }

        return (float) $actual === (float) $rule->condition_value;
    }

    private function sourceValue(PriorityRuleSource $source, WorkOrder $workOrder, ?Customer $customer): string|int|float|null
    {
        return match ($source) {
            PriorityRuleSource::CustomerTier => $customer?->tier?->value,
            PriorityRuleSource::CustomerPaymentScore => $customer?->payment_score,
            PriorityRuleSource::CustomerTotalOrders => $customer?->total_orders,
            PriorityRuleSource::WoPlannedQty => (float) $workOrder->planned_qty,
            // Hours remaining until due (negative once overdue); null with no date.
            PriorityRuleSource::WoDueDate => $workOrder->due_date
                ? now()->diffInHours($workOrder->due_date, false)
                : null,
        };
    }
}
