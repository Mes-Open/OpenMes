<?php

namespace App\Services\Warehouse;

use App\Models\Batch;
use App\Models\BatchStepLotConsumption;
use App\Models\Material;
use App\Models\MaterialAllocation;
use App\Models\MaterialLot;
use App\Models\StockDocument;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Services\Material\LotPickingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Turns production into warehouse paperwork (#212).
 *
 * When a work order is concluded, the raw-material warehouse owes a release for
 * what was consumed and the finished-goods warehouse owes a receipt for what was
 * produced. Both are created as DRAFTS: a warehouse keeper (or an ERP that pulls
 * them) decides when they are real. Material the shop floor allocated was
 * already taken off stock when it was consumed; its release lines are flagged
 * booked_at_source, and posting them only confirms the paperwork.
 *
 * Material lines come from what was actually consumed per lot when the shop floor
 * recorded it, and fall back to the order's BOM × produced quantity when it did
 * not — a bakery that books flour by recipe still gets a correct release.
 *
 * A semi-finished part — a product type that some material is produced by — is
 * received as that material into the raw-material warehouse, with one released
 * lot per batch when lot tracking is on. Booked as a product it would never reach
 * the material the next BOM level consumes, so MRP, allocation and lot genealogy
 * would all lose it between levels.
 */
class WorkOrderStockDocumentService
{
    public function __construct(
        private StockDocumentService $documents,
        private LotPickingService $lots,
    ) {}

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

        $semiFinished = $this->semiFinishedMaterial($workOrder);

        foreach ([StockDocument::TYPE_MATERIAL_ISSUE, StockDocument::TYPE_PRODUCT_RECEIPT] as $type) {
            if ($this->alreadyHas($workOrder, $type)) {
                continue;
            }
            // Booked pallet by pallet instead: an order that packs onto pallets has
            // its pallets carry the receipt; one that never palletises keeps this one.
            // That holds for a semi-finished part too: a second, material receipt
            // would book the same output twice.
            if ($type === StockDocument::TYPE_PRODUCT_RECEIPT && PalletStockDocumentService::mode() !== PalletStockDocumentService::OFF
                && \App\Models\BatchStep::where('kind', \App\Models\TemplateStep::KIND_PACKING)->whereHas('batch', fn ($q) => $q->where('work_order_id', $workOrder->id))->exists()) {
                continue;
            }
            if ($type === StockDocument::TYPE_PRODUCT_RECEIPT && $semiFinished) {
                if (! $this->alreadyHas($workOrder, StockDocument::TYPE_MATERIAL_RECEIPT)
                    && ($document = $this->generateSemiFinishedReceipt($workOrder, $semiFinished))) {
                    $created[] = $document;
                }

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

        $lines = $this->markBookedAtSource($workOrder, $lines);

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

    /**
     * Draft receipt of a semi-finished part as the material it is. With lot
     * tracking on, each batch that produced something gets a released lot linked
     * to it (source_batch_id), empty until the receipt is posted. Returns null when
     * nothing was produced or no raw-material warehouse exists.
     */
    public function generateSemiFinishedReceipt(WorkOrder $workOrder, Material $material): ?StockDocument
    {
        if (! Warehouse::resolveDefault(Warehouse::KIND_RAW_MATERIAL)) {
            return null;
        }

        $batches = $workOrder->batches()->where('produced_qty', '>', 0)->orderBy('batch_number')->get();
        $trackLots = $this->lots->isLotTrackingEnabled() && $batches->isNotEmpty();

        if (! $trackLots) {
            $produced = $this->producedQuantity($workOrder);

            return $produced > 0 ? $this->createSemiFinishedReceipt($workOrder, [[
                'material_id' => $material->id,
                'quantity' => $produced,
                'unit_of_measure' => $material->unit_of_measure,
            ]]) : null;
        }

        return DB::transaction(function () use ($workOrder, $material, $batches) {
            $lines = $batches->map(function (Batch $batch) use ($workOrder, $material) {
                $lot = $this->lotForBatch($workOrder, $batch, $material);

                return [
                    'material_id' => $material->id,
                    'material_lot_id' => $lot->id,
                    'lot_number' => $lot->lot_number,
                    'quantity' => (float) $batch->produced_qty,
                    'unit_of_measure' => $material->unit_of_measure,
                ];
            })->all();

            return $this->createSemiFinishedReceipt($workOrder, $lines);
        });
    }

    /**
     * The material this order's product is made into: the one manufactured
     * material whose producing template belongs to the order's product type. Any
     * technology of that product makes the same material. None, or more than one
     * (no way to tell which), keeps the plain product receipt.
     */
    public function semiFinishedMaterial(WorkOrder $workOrder): ?Material
    {
        if ($workOrder->product_type_id === null) {
            return null;
        }

        $materials = Material::query()
            ->where('is_manufactured', true)
            ->whereHas('producingTemplate', fn ($q) => $q->where('product_type_id', $workOrder->product_type_id))
            ->limit(2)
            ->get();

        if ($materials->count() > 1) {
            Log::warning('Several materials are produced by one product type; receiving it as a product', [
                'work_order_id' => $workOrder->id,
                'product_type_id' => $workOrder->product_type_id,
            ]);

            return null;
        }

        return $materials->first();
    }

    /** @param  array<int, array<string, mixed>>  $lines */
    private function createSemiFinishedReceipt(WorkOrder $workOrder, array $lines): StockDocument
    {
        return $this->documents->createDraft([
            'type' => StockDocument::TYPE_MATERIAL_RECEIPT,
            'work_order_id' => $workOrder->id,
            'notes' => __('Semi-finished part received from work order :order', ['order' => $workOrder->order_no]),
            'lines' => $lines,
        ]);
    }

    /**
     * The output lot of one batch: released, so the next level can pick it once
     * the receipt is posted, and empty until then. Named after the batch's lot
     * number (or order-batch), suffixed until the number is free.
     */
    private function lotForBatch(WorkOrder $workOrder, Batch $batch, Material $material): MaterialLot
    {
        $existing = MaterialLot::where('source_batch_id', $batch->id)
            ->where('material_id', $material->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $base = $batch->lot_number ?: $workOrder->order_no.'-'.$batch->batch_number;
        $taken = fn (string $candidate) => MaterialLot::withTrashed()
            ->where('lot_number', $candidate)
            ->where('tenant_id', $workOrder->tenant_id)
            ->exists();

        // The base, then base-<batch id>, then base-<batch id>-2, -3 … until one is free.
        $number = $base;
        for ($n = 1; $taken($number); $n++) {
            $number = $base.'-'.$batch->id.($n > 1 ? '-'.$n : '');
        }

        return MaterialLot::create([
            'tenant_id' => $workOrder->tenant_id,
            'material_id' => $material->id,
            'lot_number' => $number,
            'unit_of_measure' => $material->unit_of_measure ?? 'pcs',
            'quantity_received' => (float) $batch->produced_qty,
            'quantity_available' => 0,
            'received_at' => $batch->completed_at ?? now(),
            'manufacturing_date' => ($batch->completed_at ?? now())->toDateString(),
            'expiry_date' => $batch->expiry_date,
            'source_batch_id' => $batch->id,
            'status' => MaterialLot::STATUS_RELEASED,
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
     * Flag the lines whose material the shop floor already took off stock. A
     * material allocated to this order's batches moved the lot, the plant-wide
     * quantity and (with locations) the warehouse balance when it was picked and
     * consumed, so posting the release must not move it again. Material nobody
     * allocated still leaves stock only when the release is posted.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function markBookedAtSource(WorkOrder $workOrder, array $lines): array
    {
        $allocated = MaterialAllocation::query()
            ->where(fn ($q) => $q->where('work_order_id', $workOrder->id)
                ->orWhereHas('batch', fn ($b) => $b->where('work_order_id', $workOrder->id)))
            ->whereIn('status', [MaterialAllocation::STATUS_ALLOCATED, MaterialAllocation::STATUS_CONSUMED])
            ->distinct()
            ->pluck('material_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_map(fn (array $line) => [
            ...$line,
            'booked_at_source' => in_array((int) ($line['material_id'] ?? 0), $allocated, true),
        ], $lines);
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
