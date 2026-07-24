<?php

namespace App\Sync\Shapes;

use App\Models\User;
use App\Sync\Shape;

/**
 * Physical pallet movement history (#103) — who moved which pallet where.
 *
 * Recent window only. The pallet_movements ledger is append-only and grows
 * without bound, so live-syncing the whole table (and preloading a lookup
 * label for every pallet ever moved) would eventually bloat the payload and
 * exhaust memory. Older movements are historical and don't need to live-sync;
 * a dedicated audit report can cover the full trail if ever needed.
 *
 * Electric WHERE clauses can't contain SQL value functions like `now()`, so we
 * embed a literal date computed in PHP at request time. Crossing midnight means
 * the next page load gets a different shape handle (handled by the client
 * automatically); already-open lists keep their shape until they refresh.
 */
class PalletMovementsRecentShape extends Shape
{
    /** Rolling live-sync window, in days. Shared with the controller's lookup maps. */
    public const WINDOW_DAYS = 90;

    public function table(): string
    {
        return 'pallet_movements';
    }

    public function columns(): array
    {
        return [
            'id',
            'pallet_id',
            'worker_id',
            'from_location',
            'to_location',
            'moved_at',
            'notes',
            'performed_by',
            'created_at',
            'updated_at',
        ];
    }

    public function where(User $user): ?string
    {
        $since = now()->subDays(self::WINDOW_DAYS)->toDateString();

        return "moved_at >= '{$since}'";
    }
}
