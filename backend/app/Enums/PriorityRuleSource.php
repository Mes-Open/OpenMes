<?php

namespace App\Enums;

/**
 * The field a priority rule reads when scoring a work order. Customer-sourced
 * rules are skipped when the work order has no customer. `WoDueDate` resolves
 * to the number of hours remaining until the due date (negative when overdue),
 * so a "less than 24" rule fires for orders due within a day.
 */
enum PriorityRuleSource: string
{
    case CustomerTier = 'customer.tier';
    case CustomerPaymentScore = 'customer.payment_score';
    case CustomerTotalOrders = 'customer.total_orders';
    case WoPlannedQty = 'wo.planned_qty';
    case WoDueDate = 'wo.due_date';

    public function label(): string
    {
        return match ($this) {
            self::CustomerTier => __('Customer tier'),
            self::CustomerPaymentScore => __('Customer payment score'),
            self::CustomerTotalOrders => __('Customer total orders'),
            self::WoPlannedQty => __('Planned quantity'),
            self::WoDueDate => __('Hours until due'),
        };
    }

    /** Whether the compared value is textual (tier) rather than numeric. */
    public function isTextual(): bool
    {
        return $this === self::CustomerTier;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
