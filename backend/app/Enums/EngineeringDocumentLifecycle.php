<?php

namespace App\Enums;

/**
 * Engineering-document lifecycle (#179), mirroring the product-revision model:
 * DRAFT (editable) -> RELEASED (immutable, usable on the shop floor and
 * snapshotted onto released work orders) -> OBSOLETE (kept for traceability).
 */
enum EngineeringDocumentLifecycle: string
{
    case Draft = 'draft';
    case Released = 'released';
    case Obsolete = 'obsolete';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Released => __('Released'),
            self::Obsolete => __('Obsolete'),
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Draft => 'bg-gray-200 text-gray-700',
            self::Released => 'bg-green-100 text-green-800',
            self::Obsolete => 'bg-amber-100 text-amber-800',
        };
    }

    /** A released document is immutable and cannot be re-uploaded or edited in place. */
    public function isImmutable(): bool
    {
        return $this === self::Released;
    }
}
