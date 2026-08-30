<?php

namespace App\Enums;

/**
 * From when an applied configuration change takes effect (#182).
 *
 * This is the field that keeps history honest. A change never rewrites what was
 * already built — it declares where the new configuration starts, so units produced
 * before and after remain attributable to the configuration they were actually made
 * under.
 *
 * SPECIFIC_BATCH and SPECIFIC_SERIAL_NUMBER from the issue are deliberately not
 * implemented yet: each needs its own splitting and validation path, and the three
 * below cover the cases a plant hits in practice.
 */
enum ChangeEffectivePoint: string
{
    /** Applies to batches created from now on; batches already open finish as they are. */
    case NextBatch = 'NEXT_BATCH';

    /** Applies to everything still to be produced, counted from the current produced quantity. */
    case RemainingQuantity = 'REMAINING_QUANTITY';

    /**
     * Applies at once, including the not-yet-started steps of open batches. Only
     * allowed when nothing incompatible has already been executed or consumed —
     * otherwise the shop floor would be told to follow a configuration that
     * contradicts what it has already done.
     */
    case Immediate = 'IMMEDIATE';

    public function label(): string
    {
        return match ($this) {
            self::NextBatch => __('From the next batch'),
            self::RemainingQuantity => __('For the remaining quantity'),
            self::Immediate => __('Immediately'),
        };
    }

    /** Whether open batches are touched, which is what makes IMMEDIATE the risky one. */
    public function touchesOpenBatches(): bool
    {
        return $this === self::Immediate;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
