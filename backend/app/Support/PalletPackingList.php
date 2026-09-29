<?php

namespace App\Support;

use App\Enums\PalletStatus;
use App\Models\Pallet;
use App\Models\SerialUnit;
use App\Models\UnitCarton;

/**
 * The shipping document for a pallet of serialised goods: every carton with
 * the units inside it, units packed loose, and the totals. Built once here so
 * the PDF, a later export and the tests read the same thing.
 */
final class PalletPackingList
{
    /**
     * @return array{
     *   pallet: array<string, mixed>,
     *   cartons: array<int, array{carton_no: string, status: string, closed_at: ?string, units: array<int, array<string, mixed>>}>,
     *   loose_units: array<int, array<string, mixed>>,
     *   totals: array{cartons: int, units: int, scrapped: int}
     * }
     */
    public static function data(Pallet $pallet): array
    {
        $pallet->loadMissing(['workOrder:id,order_no,customer_order_no,product_type_id', 'workOrder.productType:id,name,code']);

        $units = SerialUnit::where('pallet_id', $pallet->id)
            ->orderBy('carton_id')->orderBy('serial_no')
            ->get(['id', 'serial_no', 'psn', 'status', 'carton_id', 'packed_at']);
        $cartons = UnitCarton::where('pallet_id', $pallet->id)->orderBy('carton_no')->get();

        $row = fn (SerialUnit $u) => [
            'serial_no' => $u->serial_no,
            'psn' => $u->psn,
            'status' => $u->status,
            'packed_at' => $u->packed_at?->format('Y-m-d H:i'),
        ];
        $byCarton = $units->groupBy('carton_id');

        return [
            'pallet' => [
                'id' => $pallet->id,
                'pallet_no' => $pallet->pallet_no,
                'status' => $pallet->status instanceof PalletStatus ? $pallet->status->value : $pallet->status,
                'quality_status' => $pallet->quality_status,
                'qty' => (int) $pallet->qty,
                'location' => $pallet->location,
                'destination' => $pallet->destination,
                'erp_reference' => $pallet->erp_reference,
                'order_no' => $pallet->workOrder?->order_no,
                'customer_order_no' => $pallet->workOrder?->customer_order_no,
                'product' => $pallet->workOrder?->productType?->name,
                'product_code' => $pallet->workOrder?->productType?->code,
                'created_at' => $pallet->created_at?->format('Y-m-d H:i'),
                'shipped_at' => $pallet->shipped_at?->format('Y-m-d H:i'),
            ],
            'cartons' => $cartons->map(fn (UnitCarton $c) => [
                'carton_no' => $c->carton_no,
                'status' => $c->status,
                'closed_at' => $c->closed_at?->format('Y-m-d H:i'),
                'units' => $byCarton->get($c->id, collect())->map($row)->values()->all(),
            ])->values()->all(),
            'loose_units' => $byCarton->get(null, collect())->map($row)->values()->all(),
            'totals' => [
                'cartons' => $cartons->count(),
                'units' => $units->where('status', '!=', SerialUnit::STATUS_SCRAPPED)->count(),
                'scrapped' => $units->where('status', SerialUnit::STATUS_SCRAPPED)->count(),
            ],
        ];
    }
}
