<?php

namespace App\Support;

use App\Models\ShiftHandover;

/**
 * The one shape of a shift handover snapshot, shared by the web supervisor
 * page and the REST API (Api\V1\ShiftHandoverController).
 */
class ShiftHandoverPresenter
{
    /**
     * Eager loads for present(). A handover is a historical record, so its
     * line, shift and confirming user resolve even after being soft-deleted.
     */
    public static function relations(): array
    {
        return [
            'line' => fn ($q) => $q->withTrashed()->select('id', 'name'),
            'shift' => fn ($q) => $q->withTrashed()->select('id', 'name'),
            'confirmedBy' => fn ($q) => $q->withTrashed()->select('id', 'name'),
        ];
    }

    /**
     * The keys the web page shows come first, in their original formats; the
     * rest is the full snapshot. `discrepancies` and `breakdown` are always
     * JSON objects (`{}` when empty), never arrays.
     */
    public static function present(ShiftHandover $h): array
    {
        return [
            'id' => $h->id,
            'business_date' => $h->business_date?->toDateString(),
            'shift_start' => $h->shift_start?->format('Y-m-d H:i'),
            'line_name' => $h->line?->name,
            'produced_qty' => $h->produced_qty,
            'good_qty' => $h->good_qty,
            'packed_qty' => $h->packed_qty,
            'shipped_qty' => $h->shipped_qty,
            'confirmed_by' => $h->confirmedBy?->name,
            'confirmed_at' => $h->confirmed_at?->format('Y-m-d H:i'),
            'shift_end' => $h->shift_end?->format('Y-m-d H:i'),
            'line_id' => $h->line_id,
            'shift_id' => $h->shift_id,
            // The name captured at handover time, so renaming a shift later
            // does not rewrite history; the current name only for old rows
            // without a snapshot.
            'shift_name' => data_get($h->breakdown, 'shift.name') ?? $h->shift?->name,
            'scrap_qty' => $h->scrap_qty,
            'wip_open_pallets_qty' => $h->wip_open_pallets_qty,
            'wip_unpacked_qty' => $h->wip_unpacked_qty,
            'discrepancies' => (object) ($h->discrepancies ?? []),
            'breakdown' => (object) ($h->breakdown ?? []),
            'notes' => $h->notes,
            'confirmed_by_id' => $h->confirmed_by,
        ];
    }
}
