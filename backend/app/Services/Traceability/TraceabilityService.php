<?php

namespace App\Services\Traceability;

use App\Models\Batch;
use App\Models\BatchStepLotConsumption;
use App\Models\MaterialLot;

/**
 * Unified material traceability / genealogy.
 *
 * Builds on the existing ISA-95 lot infrastructure (material_lots,
 * batch_step_lot_consumption) and the formal batch-output link
 * (material_lots.source_batch_id) to answer two questions:
 *
 *   forward  — "where did this material lot go?" (impact analysis on a bad
 *              supplier delivery: which finished work orders are affected)
 *   backward — "what fed into this finished lot?" (recall: trace a finished
 *              batch back to its ingredient lots and their suppliers)
 */
class TraceabilityService
{
    /** Hard cap on backward recursion to guard against cyclic / deep chains. */
    private const MAX_DEPTH = 10;

    /**
     * Forward trace: every batch step / batch / work order that consumed the lot.
     *
     * @return array{lot: array, consumptions: \Illuminate\Support\Collection, work_orders: \Illuminate\Support\Collection}
     */
    public function forwardTrace(MaterialLot $lot): array
    {
        // Only the work-order chain is needed downstream (work_orders + total).
        // Heavier relations (workstation, batch numbers, recordedBy) were trimmed
        // because the caller discards them.
        $consumptions = $lot->consumptions()
            ->with([
                'batchStep:id,batch_id',
                'batchStep.batch:id,work_order_id',
                'batchStep.batch.workOrder:id,order_no,product_type_id,status',
                'batchStep.batch.workOrder.productType:id,name,code',
            ])
            ->orderByDesc('consumed_at')
            ->get();

        $workOrders = $consumptions
            ->map(fn ($c) => $c->batchStep?->batch?->workOrder)
            ->filter()
            ->unique('id')
            ->values();

        return [
            'lot' => $lot->only(['id', 'lot_number', 'material_id', 'status', 'supplier_lot_no', 'source_container_no']),
            'consumptions' => $consumptions,
            'work_orders' => $workOrders,
            'total_consumed' => (float) $consumptions->sum('quantity_consumed'),
        ];
    }

    /**
     * Backward trace from a material lot: the ingredient lots that fed into it.
     *
     * For an inbound raw lot this is terminal (supplier reference). For a
     * batch-produced lot (source_batch_id set), it returns the lots consumed by
     * that batch, recursing into each so the full ingredient tree is built.
     */
    public function backwardTraceLot(MaterialLot $lot, int $depth = 0): array
    {
        $node = [
            'lot' => $lot->only(['id', 'lot_number', 'material_id', 'status']),
            'material' => $lot->material?->only(['id', 'name', 'code']),
            'supplier_lot_no' => $lot->supplier_lot_no,
            'supplier_reference' => $lot->supplier_reference,
            'source_container_no' => $lot->source_container_no,
            'inspection_id' => $lot->inspection_id,
            'source_batch_id' => $lot->source_batch_id,
            'ingredients' => [],
            'truncated' => false,
        ];

        if ($depth >= self::MAX_DEPTH) {
            $node['truncated'] = true;

            return $node;
        }

        if ($lot->source_batch_id) {
            $batch = Batch::find($lot->source_batch_id);
            if ($batch) {
                $node['source_batch'] = $batch->only(['id', 'batch_number', 'lot_number', 'work_order_id']);
                $node['ingredients'] = $this->batchInputLots($batch)
                    ->map(fn (MaterialLot $ingredient) => $this->backwardTraceLot($ingredient, $depth + 1))
                    ->values()
                    ->all();
            }
        }

        return $node;
    }

    /**
     * Full genealogy for a finished batch: which lots were consumed at each
     * step, by which operator, when — plus the batch's own output lots.
     */
    public function batchGenealogy(Batch $batch): array
    {
        $batch->loadMissing([
            'workOrder:id,order_no,product_type_id,status',
            'workOrder.productType:id,name,code',
            'steps:id,batch_id,name,step_number,status,workstation_id,started_by_id,completed_by_id,started_at,completed_at',
            'steps.workstation:id,name,code',
            'steps.completedBy:id,name',
            'outputLots:id,lot_number,material_id,source_batch_id,status',
            'outputLots.material:id,name,code',
        ]);

        $consumptions = BatchStepLotConsumption::query()
            ->whereHas('batchStep', fn ($q) => $q->where('batch_id', $batch->id))
            ->with([
                'materialLot:id,lot_number,material_id,supplier_lot_no,source_container_no,source_batch_id,status',
                'materialLot.material:id,name,code',
                'batchStep:id,batch_id,name,step_number',
                'recordedBy:id,name',
            ])
            ->orderBy('consumed_at')
            ->get()
            ->groupBy('batch_step_id');

        return [
            'batch' => $batch,
            'consumptions_by_step' => $consumptions,
            'distinct_input_lots' => $this->batchInputLots($batch),
        ];
    }

    /**
     * Distinct material lots consumed by any step of the given batch.
     *
     * @return \Illuminate\Support\Collection<int, MaterialLot>
     */
    public function batchInputLots(Batch $batch): \Illuminate\Support\Collection
    {
        $lotIds = BatchStepLotConsumption::query()
            ->whereHas('batchStep', fn ($q) => $q->where('batch_id', $batch->id))
            ->pluck('material_lot_id')
            ->unique()
            ->values();

        if ($lotIds->isEmpty()) {
            return collect();
        }

        return MaterialLot::with('material:id,name,code')
            ->whereIn('id', $lotIds)
            ->get();
    }

    /**
     * Resolve a free-text search to a result: a finished batch (by lot_number)
     * or a material lot (by lot_number / supplier_lot_no / source_container_no).
     *
     * @return array{type: string, model: mixed}|null
     */
    public function resolve(string $term): ?array
    {
        $term = trim($term);
        if ($term === '') {
            return null;
        }

        $batch = Batch::where('lot_number', $term)->first();
        if ($batch) {
            return ['type' => 'batch', 'model' => $batch];
        }

        $lot = MaterialLot::where('lot_number', $term)
            ->orWhere('supplier_lot_no', $term)
            ->orWhere('source_container_no', $term)
            ->first();
        if ($lot) {
            return ['type' => 'material_lot', 'model' => $lot];
        }

        return null;
    }
}
