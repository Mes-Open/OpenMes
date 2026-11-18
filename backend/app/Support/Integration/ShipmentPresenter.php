<?php

namespace App\Support\Integration;

use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\ShipmentScan;
use App\Services\Logistics\ShipmentLoadingService;

/**
 * The integration-facing result of a load (#400) — shared by the REST API, the
 * ERP shipment endpoints and the shipment.confirmed webhook: what went on the
 * truck (with unit counts), what is missing, what was scanned that did not
 * belong, and who did it when.
 */
class ShipmentPresenter
{
    /** Relations the header reads. */
    public const RELATIONS = ['startedBy:id,name', 'confirmedBy:id,name'];

    /** Header only — for lists. @return array<string, mixed> */
    public static function header(Shipment $shipment): array
    {
        return [
            'id' => $shipment->id,
            'shipment_no' => $shipment->shipment_no,
            'erp_reference' => $shipment->erp_reference,
            'carrier' => $shipment->carrier,
            'vehicle_no' => $shipment->vehicle_no,
            'destination' => $shipment->destination,
            'planned_at' => $shipment->planned_at?->toIso8601String(),
            'notes' => $shipment->notes,
            'status' => $shipment->status->value,
            'started_at' => $shipment->started_at?->toIso8601String(),
            'started_by' => $shipment->startedBy?->name,
            'confirmed_at' => $shipment->confirmed_at?->toIso8601String(),
            'confirmed_by' => $shipment->confirmedBy?->name,
            'short_reason' => $shipment->short_reason,
            'created_at' => $shipment->created_at?->toIso8601String(),
            'updated_at' => $shipment->updated_at?->toIso8601String(),
        ];
    }

    /** Header plus the load result. @return array<string, mixed> */
    public static function present(Shipment $shipment): array
    {
        $service = app(ShipmentLoadingService::class);
        $shipment->loadMissing(self::RELATIONS);
        $summary = $service->summary($shipment);

        $scan = fn (ShipmentScan $s) => [
            'scanned_code' => $s->scanned_code,
            'unit_type' => $s->unit_type,
            'result' => $s->result,
            'message' => $s->message,
            'scanned_at' => $s->scanned_at?->toIso8601String(),
            'scanned_by' => $s->user?->name,
        ];

        return self::header($shipment) + [
            'totals' => [
                'expected' => $summary['expected'],
                'loaded' => $summary['loaded'],
                'missing' => $summary['missing'],
                'loaded_units' => $summary['loaded_items']->sum(fn (ShipmentItem $i) => $service->unitCount($i)),
                'unexpected_scans' => $summary['unexpected']->count(),
                'duplicate_scans' => $summary['duplicates']->count(),
                'blocked_scans' => $summary['blocked']->count(),
            ],
            'loaded' => $summary['loaded_items']->map(fn (ShipmentItem $i) => [
                'unit_type' => $i->unit_type,
                'identifier' => $i->identifier,
                'unit_count' => $service->unitCount($i),
                'loaded_at' => $i->loaded_at?->toIso8601String(),
                'loaded_by' => $i->loadedBy?->name,
            ])->values()->all(),
            'missing' => $summary['missing_items']->map(fn (ShipmentItem $i) => [
                'unit_type' => $i->unit_type,
                'identifier' => $i->identifier,
            ])->values()->all(),
            'unexpected' => $summary['unexpected']->map($scan)->values()->all(),
            'duplicates' => $summary['duplicates']->map($scan)->values()->all(),
            'blocked' => $summary['blocked']->map($scan)->values()->all(),
        ];
    }
}
