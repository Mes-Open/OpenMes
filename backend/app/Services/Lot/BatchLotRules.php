<?php

namespace App\Services\Lot;

use App\Models\Batch;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The batch LOT and expiry, enforced on the model so every path that writes a
 * batch (operator screen, API, release, restore from Trash) gets the same
 * answer:
 *
 * - a LOT belongs to one live batch;
 * - a new batch of a product with a shelf life gets its expiry date at
 *   creation (creation day + shelf life), so the date printed during
 *   packaging is known from the start;
 * - whether a LOT may be typed by hand is the system setting
 *   `batch_lot_manual_entry` ({@see manualEntryAllowed()}), checked where a
 *   LOT is typed (the batch create forms and API), since a LOT the sequence
 *   issued reaches the model the same way.
 */
class BatchLotRules
{
    public const MANUAL_ENTRY_SETTING = 'batch_lot_manual_entry';

    public function creating(Batch $batch): void
    {
        $this->normalize($batch);
        $this->assertUnique($batch);

        if ($batch->expiry_date === null && $batch->work_order_id) {
            $expiry = WorkOrder::with('productType')->find($batch->work_order_id)?->productType?->expiryFrom(today());
            if ($expiry) {
                $batch->expiry_date = $expiry->toDateString();
            }
        }
    }

    public function updating(Batch $batch): void
    {
        if ($batch->isDirty('lot_number')) {
            $this->normalize($batch);
        }
        if ($batch->isDirty('lot_number')) {
            $this->assertUnique($batch);
        }
    }

    public function restoring(Batch $batch): void
    {
        $this->normalize($batch);
        $this->assertUnique($batch);
    }

    /** Whether a user may type a batch LOT instead of taking the sequence's. Default: yes. */
    public static function manualEntryAllowed(): bool
    {
        try {
            $value = DB::table('system_settings')->where('key', self::MANUAL_ENTRY_SETTING)->value('value');
        } catch (\Throwable) {
            return true;
        }

        return $value === null ? true : (bool) json_decode($value, true);
    }

    /**
     * Store the LOT as it is compared: trimmed, and empty as no LOT. Otherwise
     * " LOT1" would pass the check against "LOT1" and sit next to it, since the
     * unique index compares the stored value.
     */
    private function normalize(Batch $batch): void
    {
        if (is_string($batch->lot_number)) {
            $lot = trim($batch->lot_number);
            $batch->lot_number = $lot === '' ? null : $lot;
        }
    }

    private function assertUnique(Batch $batch): void
    {
        $lot = trim((string) $batch->lot_number);
        if ($lot === '') {
            return;
        }

        // Two saves of the same LOT in concurrent transactions would both pass
        // the lookup below. On PostgreSQL a transaction-scoped advisory lock on
        // the LOT makes the second wait until the first commits, so it sees the
        // first batch. It matters when the unique index is missing (the
        // migration skips it over existing duplicates); with the index the
        // database refuses the second row anyway.
        if (DB::transactionLevel() > 0 && DB::connection()->getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['batch-lot:'.$lot]);
        }

        $other = Batch::with('workOrder:id,order_no')
            ->where('lot_number', $lot)
            ->when($batch->exists, fn ($q) => $q->whereKeyNot($batch->getKey()))
            ->first();

        if ($other) {
            throw ValidationException::withMessages([
                'lot_number' => __('LOT :lot is already used by batch :batch of work order :order.', [
                    'lot' => $lot,
                    'batch' => $other->batch_number,
                    'order' => $other->workOrder?->order_no ?? $other->work_order_id,
                ]),
            ]);
        }
    }
}
