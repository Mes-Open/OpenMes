<?php

namespace App\Enums;

/**
 * How a priority rule compares the source field against its configured value.
 * `Between` uses both `condition_value` (lower bound) and `condition_value_max`
 * (upper bound), inclusive. `IsTrue` treats the source value as a boolean.
 */
enum PriorityCondition: string
{
    case Equals = 'equals';
    case GreaterThan = 'greater_than';
    case LessThan = 'less_than';
    case Between = 'between';
    case IsTrue = 'is_true';

    public function label(): string
    {
        return match ($this) {
            self::Equals => __('Equals'),
            self::GreaterThan => __('Greater than'),
            self::LessThan => __('Less than'),
            self::Between => __('Between'),
            self::IsTrue => __('Is true'),
        };
    }

    public function needsUpperBound(): bool
    {
        return $this === self::Between;
    }

    public function needsValue(): bool
    {
        return $this !== self::IsTrue;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
