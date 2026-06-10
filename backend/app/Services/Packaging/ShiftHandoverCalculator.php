<?php

namespace App\Services\Packaging;

use App\Enums\PalletStatus;
use App\Models\PackagingScanLog;
use App\Models\Pallet;
use App\Models\ScrapEntry;
use App\Models\WorkOrderShiftEntry;
use App\Support\ShiftWindow;

/**
 * Reconciles a shift's flow for the handover screen: produced (shift entries)
 * minus scrap = good, vs packed (scan logs) vs WIP (open pallets + unpacked)
 * vs shipped (shipped pallets), with the differences flagged. All figures are
 * scoped to the active shift window (ShiftWindow) and an optional production line.
 */
class ShiftHandoverCalculator
{
    public function compute(?int $lineId = null): array
    {
        $window = ShiftWindow::current($lineId);
        $start = $window->start;
        $end = $window->end;
        $shiftId = $window->shift?->id;

        // Restrict a query to a line via its work order, when a line is selected.
        $byLine = fn ($query) => $lineId
            ? $query->whereHas('workOrder', fn ($q) => $q->where('line_id', $lineId))
            : $query;

        // Produced — operator shift entries for this shift's business date.
        $produced = (int) round((float) $byLine(
            WorkOrderShiftEntry::query()
                ->whereDate('production_date', $window->businessDate)
                ->when($shiftId, fn ($q) => $q->where('shift_id', $shiftId))
        )->sum('quantity'));

        // Scrap reported during the window.
        $scrap = (int) round((float) $byLine(
            ScrapEntry::query()
                ->whereBetween('reported_at', [$start, $end])
                ->when($shiftId, fn ($q) => $q->where('shift_id', $shiftId))
        )->sum('quantity'));

        $good = max(0, $produced - $scrap);

        // Packed — pieces scanned at the packing station during the window.
        $packed = (int) $byLine(
            PackagingScanLog::query()->whereBetween('scanned_at', [$start, $end])
        )->count();

        // WIP — open pallets (their accumulated qty) + still-unpacked good output.
        $openPalletsQuery = $byLine(Pallet::query()->where('status', PalletStatus::Open->value));
        $openPalletsQty = (int) $openPalletsQuery->sum('qty');
        $openPalletsCount = (int) $openPalletsQuery->count();
        $unpacked = max(0, $good - $packed);
        $wipTotal = $openPalletsQty + $unpacked;

        // Shipped — pallets dispatched during the window.
        $shipped = (int) $byLine(
            Pallet::query()
                ->where('status', PalletStatus::Shipped->value)
                ->whereBetween('updated_at', [$start, $end])
        )->sum('qty');

        $discrepancies = $this->discrepancies($good, $packed, $shipped, $unpacked);

        $openPallets = $byLine(
            Pallet::query()
                ->where('status', PalletStatus::Open->value)
                ->with('workOrder:id,order_no')
        )->orderByDesc('updated_at')->limit(100)->get()
            ->map(fn (Pallet $p) => [
                'id' => $p->id,
                'pallet_no' => $p->pallet_no,
                'order_no' => $p->workOrder?->order_no,
                'qty' => (int) $p->qty,
            ])->all();

        return [
            'line_id' => $lineId,
            'shift_id' => $shiftId,
            'shift' => $window->shiftPayload(),
            'window' => [
                'start' => $start->toIso8601String(),
                'end' => $end->toIso8601String(),
                'business_date' => $window->businessDate,
            ],
            'produced_qty' => $produced,
            'scrap_qty' => $scrap,
            'good_qty' => $good,
            'packed_qty' => $packed,
            'wip_open_pallets_qty' => $openPalletsQty,
            'wip_open_pallets_count' => $openPalletsCount,
            'wip_unpacked_qty' => $unpacked,
            'wip_total_qty' => $wipTotal,
            'shipped_qty' => $shipped,
            'discrepancies' => $discrepancies,
            'open_pallets' => $openPallets,
        ];
    }

    /**
     * Named, human-readable differences worth a supervisor's attention.
     */
    private function discrepancies(int $good, int $packed, int $shipped, int $unpacked): array
    {
        $out = [];

        if ($unpacked > 0) {
            $out['unpacked'] = [
                'label' => __('Good output not yet packed'),
                'value' => $unpacked,
                'severity' => 'warning',
            ];
        }

        $awaitingShipment = max(0, $packed - $shipped);
        if ($awaitingShipment > 0) {
            $out['awaiting_shipment'] = [
                'label' => __('Packed but not yet shipped'),
                'value' => $awaitingShipment,
                'severity' => 'info',
            ];
        }

        // Packed exceeding good output points at a data/count problem.
        if ($packed > $good) {
            $out['over_packed'] = [
                'label' => __('Packed exceeds good output — check counts'),
                'value' => $packed - $good,
                'severity' => 'danger',
            ];
        }

        return $out;
    }
}
