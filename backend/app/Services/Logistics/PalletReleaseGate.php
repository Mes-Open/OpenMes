<?php

namespace App\Services\Logistics;

use App\Enums\PalletStatus;
use App\Models\Batch;
use App\Models\Pallet;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ship only goods released for sale (system setting `ship_requires_release`,
 * off by default). On top of the quality ship-gate, the pallet's batch - or,
 * for a pallet linked only to a work order, every started batch of that order
 * - must be released for sale, and the order must not be blocked by an open
 * non-conformance. Runs on Pallet saving, so a pallet recorded straight as
 * shipped is covered too.
 */
class PalletReleaseGate
{
    public const SETTING = 'ship_requires_release';

    public static function enabled(): bool
    {
        try {
            $value = DB::table('system_settings')->where('key', self::SETTING)->value('value');
        } catch (\Throwable) {
            return false;
        }

        return $value !== null && (bool) json_decode($value, true);
    }

    public function saving(Pallet $pallet): void
    {
        if (! $pallet->isDirty('status') || $pallet->status !== PalletStatus::Shipped || ! self::enabled()) {
            return;
        }

        $problem = $this->problem($pallet);
        if ($problem !== null) {
            throw ValidationException::withMessages(['status' => $problem]);
        }
    }

    private function problem(Pallet $pallet): ?string
    {
        $batches = $pallet->batch_id
            ? Batch::whereKey($pallet->batch_id)->get()
            : Batch::where('work_order_id', $pallet->work_order_id)
                ->whereNotIn('status', [Batch::STATUS_CANCELLED, Batch::STATUS_PENDING])
                ->get();

        $number = $pallet->pallet_no ?: __('(new)');

        if ($batches->isEmpty()) {
            return __('Pallet :no cannot ship: it is not linked to a produced batch, so its release for sale cannot be checked.', ['no' => $number]);
        }

        $unreleased = $batches->first(fn (Batch $b) => ! $b->isReleased() || $b->release_type !== Batch::RELEASE_FOR_SALE);
        if ($unreleased) {
            return __('Pallet :no cannot ship: batch :lot is not released for sale.', [
                'no' => $number,
                'lot' => $unreleased->lot_number ?: '#'.$unreleased->batch_number,
            ]);
        }

        $workOrder = WorkOrder::find($pallet->work_order_id ?? $batches->first()->work_order_id);
        if ($workOrder?->isBlocked()) {
            return __('Pallet :no cannot ship: work order :order is blocked by an open non-conformance.', [
                'no' => $number,
                'order' => $workOrder->order_no,
            ]);
        }

        return null;
    }
}
