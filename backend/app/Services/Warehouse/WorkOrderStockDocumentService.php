<?php

namespace App\Services\Warehouse;

use App\Models\BatchStepLotConsumption;
use App\Models\StockDocument;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Log;

/**
 * Turns production into warehouse paperwork (#212).
 *
 * When a work order is concluded, the raw-material warehouse owes a release for
 * what was consumed and the finished-goods warehouse owes a receipt for what was
 * produced. Both are created as DRAFTS: a warehouse keeper (or an ERP that pulls
 * them) decides when they are real, and nothing moves stock until posted.
 *
 * Material lines come from what was actually consumed per lot when the shop floor
 * recorded it, and fall back to the order's BOM × produced quantity when it did
 * not — a bakery that books flour by recipe still gets a correct release.
 */
class WorkOrderStockDocumentService
{
    public function __construct(private StockDocumentService $documents) {}

    /**
     * Create the draft documents a completed work order implies. Idempotent: an
     * order that already has a document of a given type is left alone, so
     * re-completing (or a replayed event) cannot duplicate paperwork.
     *
     * @return array<int, StockDocument>
     */
    public function generateForCompletion(WorkOrder $workOrder): array
    {
        $created = [];

        foreach ([StockDocument::TYPE_MATERIAL_ISSUE, StockDocument::TYPE_PRODUCT_RECEIPT] as $type) {
            if ($this->alreadyHas($workOrder, $type)) {
                continue;
            }

            $document = $type === StockDocument::TYPE_MATERIAL_ISSUE
                ? $this->generateMaterialIssue($workOrder)
                : $this->generateProductReceipt($workOrder);

            if ($document) {
                $created[] = $document;
            }
        }

        return $created;
    }

    /**
     * Draft release of the materials this work order used. Returns null when the
     * order consumed nothing traceable (no lot consumption and no BOM) or no
     * raw-material warehouse exists.
     */
    public function generateMaterialIssue(WorkOrder $workOrder, ?float $quantity = null): ?StockDocument
    {
        if (! Warehouse::resolveDefault(Warehouse::KIND_RAW_MATERIAL)) {
            return null;
        }

        $lines = $this->consumedLines($workOrder);

        if ($lines === []) {
            $lines = $this->bomLines($workOrder, $quantity ?? $this->producedQuantity($workOrder));
        }

        if ($lines === []) {
            return null;
        }

        return $this->documents->createDraft([
            'type' => StockDocument::TYPE_MATERIAL_ISSUE,
            'work_order_id' => $workOrder->id,
            'notes' => __('Material released for work order :order', ['order' => $workOrder->order_no]),
            'lines' => $lines,
        ]);
    }

    /**
     * Draft receipt of the product this work order made. Returns null when
     * nothing was produced or no finished-goods warehouse exists.
     */
    public function generateProductReceipt(WorkOrder $workOrder, ?float $quantity = null): ?StockDocument
    {
        if (! Warehouse::resolveDefault(Warehouse::KIND_FINISHED_GOODS)) {
            return null;
        }

        $produced = $quantity ?? $this->producedQuantity($workOrder);

        if ($produced <= 0 || $workOrder->product_type_id === null) {
            return null;
        }

        return $this->documents->createDraft([
            'type' => StockDocument::TYPE_PRODUCT_RECEIPT,
            'work_order_id' => $workOrder->id,
            'notes' => __('Product received from work order :order', ['order' => $workOrder->order_no]),
            'lines' => [[
                'product_type_id' => $workOrder->product_type_id,
                'quantity' => $produced,
                'unit_of_measure' => $workOrder->productType?->unit_of_measure,
            ]],
        ]);
    }

    private function alreadyHas(WorkOrder $workOrder, string $type): bool
    {
        return StockDocument::where('work_order_id', $workOrder->id)
            ->where('type', $type)
            ->exists();
    }

    /** Produced quantity, falling back to the plan for orders that never counted. */
    private function producedQuantity(WorkOrder $workOrder): float
    {
        $produced = (float) $workOrder->produced_qty;

        return $produced > 0 ? $produced : (float) $workOrder->planned_qty;
    }

    /**
     * Actual consumption recorded against the order's batch steps, one line per
     * (material, lot) so the release keeps lot traceability.
     *
     * @return array<int, array<string, mixed>>
     */
    private function consumedLines(WorkOrder $workOrder): array
    {
        $rows = BatchStepLotConsumption::query()
            ->whereHas('batchStep.batch', fn ($q) => $q->where('work_order_id', $workOrder->id))
            ->with('materialLot.material')
            ->get();

        $lines = [];

        foreach ($rows as $row) {
            $lot = $row->materialLot;

            if (! $lot || ! $lot->material) {
                continue;
            }

            $key = $lot->material_id.':'.$lot->id;

            $lines[$key] ??= [
                'material_id' => $lot->material_id,
                'material_lot_id' => $lot->id,
                'lot_number' => $lot->lot_number,
                'unit_of_measure' => $lot->unit_of_measure ?? $lot->material->unit_of_measure,
                'quantity' => 0.0,
            ];

            $lines[$key]['quantity'] += (float) $row->quantity_consumed;
        }

        return array_values($lines);
    }

    /**
     * Planned consumption from the order's BOM(s): quantity per unit (plus its
     * scrap allowance) times the quantity produced.
     *
     * @return array<int, array<string, mixed>>
     */
    private function bomLines(WorkOrder $workOrder, float $quantity): array
    {
        if ($quantity <= 0) {
            return [];
        }

        try {
            $templates = $workOrder->bomTemplates()->with('bomItems.material')->get();

            if ($templates->isEmpty()) {
                $active = $workOrder->productType?->processTemplates()
                    ->where('is_active', true)
                    ->orderByDesc('version')
                    ->with('bomItems.material')
                    ->first();

                $templates = collect(array_filter([$active]));
            }
        } catch (\Throwable $e) {
            Log::warning('Could not resolve BOM for stock document', [
                'work_order_id' => $workOrder->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        $lines = [];

        foreach ($templates as $template) {
            foreach ($template->bomItems as $item) {
                if (! $item->material) {
                    continue;
                }

                // Several linked BOMs may list the same component — merge.
                $lines[$item->material_id] ??= [
                    'material_id' => $item->material_id,
                    'unit_of_measure' => $item->material->unit_of_measure,
                    'quantity' => 0.0,
                ];

                $lines[$item->material_id]['quantity'] += $item->calculateRequiredQuantity($quantity);
            }
        }

        return array_values($lines);
    }
}
