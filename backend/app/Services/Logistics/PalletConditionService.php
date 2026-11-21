<?php

namespace App\Services\Logistics;

use App\Enums\PalletCondition;
use App\Enums\PalletStatus;
use App\Models\Pallet;
use App\Models\PalletConditionEvent;
use App\Models\PalletMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The physical condition of a pallet (damaged wrap, crushed cartons): recorded
 * by the warehouse on receipt and on moves, restored on the pallet screen.
 *
 * The pallet row carries the current condition; every record also appends a
 * pallet_condition_events row, so who found what and when stays auditable.
 * Quality status is QC's verdict and is never touched here.
 *
 * With the system setting `damaged_pallets_cannot_ship` (on by default) a
 * damaged pallet cannot be loaded on a shipment or marked shipped until it is
 * restored to good.
 */
class PalletConditionService
{
    public const SHIP_BLOCK_SETTING = 'damaged_pallets_cannot_ship';

    /** Whether a damaged pallet is kept off shipments. No row = on. */
    public static function blocksShipping(): bool
    {
        try {
            $value = DB::table('system_settings')->where('key', self::SHIP_BLOCK_SETTING)->value('value');
        } catch (\Throwable) {
            return true;
        }

        return $value === null || (bool) json_decode($value, true);
    }

    /**
     * Why this pallet may not ship because of its condition, or null when it may.
     */
    public static function shipBlocker(Pallet $pallet): ?string
    {
        if (! $pallet->isDamaged() || ! self::blocksShipping()) {
            return null;
        }

        return __('Pallet :code is damaged. Restore its condition to good before shipping.', ['code' => $pallet->pallet_no]);
    }

    /**
     * Record the pallet's condition. Runs in the caller's transaction when there
     * is one (a move), so the move and its condition land together.
     */
    public function record(
        Pallet $pallet,
        PalletCondition $condition,
        ?string $note,
        ?User $recordedBy,
        string $source,
        ?PalletMovement $movement = null,
    ): PalletConditionEvent {
        if (! in_array($source, PalletConditionEvent::SOURCES, true)) {
            throw new \InvalidArgumentException("Unknown pallet condition source [{$source}].");
        }

        $note = $note !== null ? trim($note) : null;
        $note = $note === '' ? null : $note;

        return DB::transaction(function () use ($pallet, $condition, $note, $recordedBy, $source, $movement) {
            $locked = Pallet::whereKey($pallet->getKey())->lockForUpdate()->firstOrFail();

            // Re-checked under the lock: the request's checks ran before it, so a
            // shipment confirmed or a parallel restore in between must not slip a
            // record onto a shipped pallet or a duplicate "good" into the history.
            if ($locked->status === PalletStatus::Shipped) {
                throw ValidationException::withMessages(['condition' => __('The pallet has already been shipped.')]);
            }
            if ($condition === PalletCondition::Good && ! $locked->isDamaged()) {
                throw ValidationException::withMessages(['condition' => __('The pallet is already in good condition.')]);
            }

            $now = now();

            // A plain save (not quiet): the synced pallets collection broadcasts
            // from model events. The status hooks see no status change.
            $locked->forceFill([
                'condition' => $condition,
                'condition_note' => $note,
                'condition_recorded_by_id' => $recordedBy?->id,
                'condition_recorded_at' => $now,
            ])->save();

            $pallet->setRawAttributes($locked->getAttributes(), true);

            return PalletConditionEvent::create([
                'pallet_id' => $locked->id,
                'condition' => $condition->value,
                'note' => $note,
                'user_id' => $recordedBy?->id,
                'recorded_at' => $now,
                'source' => $source,
                'pallet_movement_id' => $movement?->id,
                'tenant_id' => $locked->tenant_id,
            ]);
        });
    }
}
