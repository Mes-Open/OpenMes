<?php

namespace App\Support\Integration;

use App\Models\StockDocument;

/**
 * The integration-facing shape of a warehouse document — shared by the ERP
 * stock-document export and the stock_document.posted webhook event.
 */
class StockDocumentPresenter
{
    /** Relations present() reads; eager-load these to avoid a query per line. */
    public const RELATIONS = [
        'warehouse:id,code,name,erp_code',
        'workOrder:id,order_no,external_system,external_ref',
        'lines.material:id,code,name',
        'lines.productType:id,code,name',
        'lines.materialLot:id,lot_number',
    ];

    /** @return array<string, mixed> */
    public static function present(StockDocument $doc): array
    {
        return [
            'id' => $doc->id,
            'document_no' => $doc->document_no,
            'type' => $doc->type,
            'status' => $doc->status,
            'direction' => $doc->direction() > 0 ? 'in' : 'out',
            'warehouse_code' => $doc->warehouse?->code,
            'warehouse_erp_code' => $doc->warehouse?->erp_code,
            'work_order_no' => $doc->workOrder?->order_no,
            'work_order_external_system' => $doc->workOrder?->external_system,
            'work_order_external_ref' => $doc->workOrder?->external_ref,
            'notes' => $doc->notes,
            'erp_reference' => $doc->erp_reference,
            'erp_synced_at' => $doc->erp_synced_at?->toIso8601String(),
            'posted_at' => $doc->posted_at?->toIso8601String(),
            'updated_at' => $doc->updated_at?->toIso8601String(),
            'lines' => $doc->lines->map(fn ($line) => [
                'material_code' => $line->material?->code,
                'product_type_code' => $line->productType?->code,
                'lot_number' => $line->effectiveLotNumber(),
                'quantity' => (float) $line->quantity,
                'unit_of_measure' => $line->unit_of_measure,
                'notes' => $line->notes,
            ])->values(),
        ];
    }
}
