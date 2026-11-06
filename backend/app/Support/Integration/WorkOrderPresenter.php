<?php

namespace App\Support\Integration;

use App\Models\WorkOrder;

/**
 * The integration-facing shape of a work order — one source of truth shared by
 * the ERP production export (GET /api/v1/erp/production/...) and the
 * work_order.* webhook events, so a receiver sees identical fields whichever
 * way it learns about an order.
 */
class WorkOrderPresenter
{
    /** Relations present() reads; eager-load these to avoid a query per order. */
    public const RELATIONS = ['line:id,code,name', 'productType:id,code,name'];

    /** @return array<string, mixed> */
    public static function present(WorkOrder $wo): array
    {
        return [
            'id' => $wo->id,
            'order_no' => $wo->order_no,
            'customer_order_no' => $wo->customer_order_no,
            'status' => $wo->status,
            'planned_qty' => (float) $wo->planned_qty,
            'produced_qty' => (float) $wo->produced_qty,
            'unit_price' => $wo->unit_price !== null ? (float) $wo->unit_price : null,
            'counting_source' => $wo->counting_source,
            'line_code' => $wo->line?->code,
            'product_type_code' => $wo->productType?->code,
            'completed_at' => $wo->completed_at?->toIso8601String(),
            'updated_at' => $wo->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Webhook payload: present() plus the order id, the status it left
     * (null on work_order.created) and the internal ids earlier payloads
     * carried, kept for backward compatibility.
     *
     * @return array<string, mixed>
     */
    public static function webhookPayload(WorkOrder $wo, ?string $previousStatus): array
    {
        $wo->loadMissing(['line', 'productType']);

        return ['id' => $wo->id]
            + self::present($wo)
            + [
                'previous_status' => $previousStatus,
                'line_id' => $wo->line_id,
                'product_type_id' => $wo->product_type_id,
            ];
    }
}
