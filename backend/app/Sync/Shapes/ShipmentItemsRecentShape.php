<?php

namespace App\Sync\Shapes;

use App\Models\User;
use App\Sync\Shape;

/**
 * Expected units of recent shipments (#400) — the live list on the loading
 * terminal and the shipment pages.
 *
 * Rolling window on created_at: every shipment adds its whole expected list, so
 * syncing the table without bound would grow every client's snapshot forever.
 * A load stays on the dock for hours, not months, so the window never cuts a
 * shipment that is still being worked on. The literal date is computed in PHP
 * (see PalletMovementsRecentShape for why).
 */
class ShipmentItemsRecentShape extends Shape
{
    public const WINDOW_DAYS = 90;

    public function table(): string
    {
        return 'shipment_items';
    }

    public function columns(): array
    {
        return [
            'id', 'shipment_id', 'unit_type', 'identifier', 'pallet_id', 'carton_id',
            'loaded_at', 'loaded_by_id', 'created_at', 'updated_at',
        ];
    }

    public function where(User $user): ?string
    {
        $since = now()->subDays(self::WINDOW_DAYS)->toDateString();

        return "created_at >= '{$since}'";
    }

    public function tabs(): array
    {
        return ['packaging'];
    }

    /** The loading terminal is a role-gated shop-floor screen. */
    public function roles(): array
    {
        return ['Operator'];
    }
}
