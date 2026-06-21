<?php

namespace App\Services\Traceability;

use App\Enums\PalletStatus;
use App\Models\Batch;
use App\Models\BatchStepLotConsumption;
use App\Models\MaterialLot;
use App\Models\Pallet;
use App\Models\SerialUnit;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Support\Collection;

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
     * Reverse traceability for recall: starting from one or more source lots,
     * walk forward through every batch that consumed them and collect the
     * affected work orders plus the finished serial units produced under them -
     * the "what do I need to pull off the shelf?" answer.
     *
     * The walk is transitive: a consuming batch may itself produce output lots
     * (material_lots.source_batch_id), which can be consumed further downstream,
     * so multi-stage builds surface every affected level. Bounded by MAX_DEPTH.
     *
     * @param  Collection<int, MaterialLot>  $sourceLots
     * @return array{source_lots: array, work_orders: array, totals: array, truncated: bool}
     */
    public function recallImpact(Collection $sourceLots): array
    {
        $visitedLotIds = [];
        $affected = []; // work_order_id => aggregate row
        $frontier = $sourceLots->pluck('id')->filter()->unique()->values()->all();
        $truncated = false;

        for ($depth = 0; ! empty($frontier); $depth++) {
            if ($depth >= self::MAX_DEPTH) {
                $truncated = true;
                break;
            }

            foreach ($frontier as $id) {
                $visitedLotIds[$id] = true;
            }

            $consumptions = BatchStepLotConsumption::query()
                ->whereIn('material_lot_id', $frontier)
                ->with([
                    'batchStep:id,batch_id',
                    'batchStep.batch:id,batch_number,work_order_id',
                    'batchStep.batch.workOrder:id,order_no,product_type_id,status',
                    'batchStep.batch.workOrder.productType:id,name,code',
                ])
                ->get();

            $consumingBatchIds = [];

            foreach ($consumptions as $consumption) {
                $batch = $consumption->batchStep?->batch;
                $workOrder = $batch?->workOrder;
                if (! $workOrder) {
                    continue; // batch / WO soft-deleted (global scope nulled it)
                }

                $consumingBatchIds[$batch->id] = true;

                $row = $affected[$workOrder->id] ?? [
                    'id' => $workOrder->id,
                    'order_no' => $workOrder->order_no,
                    'product' => $workOrder->productType?->name,
                    'status' => $workOrder->status,
                    'quantity_consumed' => 0.0,
                    'batches' => [],
                ];
                $row['quantity_consumed'] += (float) $consumption->quantity_consumed;
                if ($batch->batch_number !== null && ! in_array($batch->batch_number, $row['batches'], true)) {
                    $row['batches'][] = $batch->batch_number;
                }
                $affected[$workOrder->id] = $row;
            }

            // Next level: output lots of the consuming batches we haven't walked yet.
            $frontier = MaterialLot::query()
                ->whereIn('source_batch_id', array_keys($consumingBatchIds))
                ->pluck('id')
                ->reject(fn ($id) => isset($visitedLotIds[$id]))
                ->unique()
                ->values()
                ->all();
        }

        $serialsByWorkOrder = empty($affected)
            ? collect()
            : SerialUnit::query()
                ->whereIn('work_order_id', array_keys($affected))
                ->orderByDesc('produced_at')
                ->orderByDesc('id')
                ->get(['id', 'serial_no', 'status', 'work_order_id', 'produced_at'])
                ->groupBy('work_order_id');

        $workOrders = collect($affected)->values()->map(function (array $row) use ($serialsByWorkOrder) {
            $serials = ($serialsByWorkOrder[$row['id']] ?? collect())
                ->map(fn (SerialUnit $unit) => [
                    'serial_no' => $unit->serial_no,
                    'status' => $unit->status,
                    'produced_at' => $unit->produced_at?->format('Y-m-d H:i'),
                ])
                ->values()
                ->all();
            $row['quantity_consumed'] = round($row['quantity_consumed'], 4);
            $row['finished_serials'] = $serials;

            return $row;
        })->all();

        return [
            'source_lots' => $sourceLots->map(fn (MaterialLot $lot) => [
                'lot_number' => $lot->lot_number,
                'material' => $lot->material?->name,
                'supplier_lot_no' => $lot->supplier_lot_no,
            ])->values()->all(),
            'work_orders' => $workOrders,
            'truncated' => $truncated,
            'totals' => [
                'work_orders' => count($workOrders),
                'finished_serials' => array_sum(array_map(fn ($w) => count($w['finished_serials']), $workOrders)),
                'quantity_consumed' => round(array_sum(array_column($workOrders, 'quantity_consumed')), 4),
            ],
        ];
    }

    /**
     * Recall impact for a serialised unit, resolved through its batch: a
     * component serial is traced via the output lots its batch produced, which
     * is what flows downstream into finished goods. A unit with no batch (or a
     * top-level finished unit with no downstream consumption) yields an empty
     * impact.
     */
    public function recallImpactForSerial(SerialUnit $unit): array
    {
        $sourceLots = $unit->batch_id
            ? MaterialLot::with('material:id,name,code')
                ->where('source_batch_id', $unit->batch_id)
                ->get()
            : collect();

        return $this->recallImpact($sourceLots);
    }

    /**
     * Diagnostic drill-down for a finished serial unit: for every component lot
     * consumed to build it, the production lines and workstations that component
     * passed through during its OWN manufacture (the steps of the batch that
     * produced it). Answers "this finished piece is defective - which component,
     * and on which line, was at fault?".
     *
     * A component with no producing batch (a raw inbound lot) is returned with
     * an empty journey: it came from a supplier, not an internal line.
     *
     * @return array{components: array}
     */
    public function componentLineJourneys(SerialUnit $unit): array
    {
        $batch = $unit->batch_id ? Batch::find($unit->batch_id) : null;
        if (! $batch) {
            return ['components' => []];
        }

        $components = $this->batchInputLots($batch)
            ->map(fn (MaterialLot $lot) => $this->lotLineJourney($lot))
            ->values()
            ->all();

        return ['components' => $components];
    }

    /**
     * The line / workstation path a single material lot travelled through during
     * its own production, resolved from the steps of the batch that produced it
     * (source_batch_id). Empty journey for a raw inbound lot.
     *
     * @return array{lot_number: ?string, material: ?string, material_code: ?string, supplier_lot_no: ?string, status: ?string, is_raw: bool, lines: array, steps: array}
     */
    private function lotLineJourney(MaterialLot $lot): array
    {
        $node = [
            'lot_number' => $lot->lot_number,
            'material' => $lot->material?->name,
            'material_code' => $lot->material?->code,
            'supplier_lot_no' => $lot->supplier_lot_no,
            'status' => $lot->status,
            'is_raw' => $lot->source_batch_id === null,
            'lines' => [],
            'steps' => [],
        ];

        if (! $lot->source_batch_id) {
            return $node;
        }

        $batch = Batch::with([
            'steps:id,batch_id,step_number,name,status,workstation_id,completed_by_id,completed_at',
            'steps.workstation:id,name,code,line_id',
            'steps.workstation.line:id,name,code',
            'steps.completedBy:id,name',
        ])->find($lot->source_batch_id);

        if (! $batch) {
            return $node;
        }

        $lines = []; // line_id => row, first occurrence wins (steps are step-ordered)

        foreach ($batch->steps as $step) {
            $line = $step->workstation?->line;
            $node['steps'][] = [
                'step_number' => $step->step_number,
                'name' => $step->name,
                'status' => $step->status,
                'line' => $line?->name,
                'workstation' => $step->workstation?->name,
                'completed_by' => $step->completedBy?->name,
                'completed_at' => $step->completed_at?->format('Y-m-d H:i'),
            ];
            if ($line && ! array_key_exists($line->id, $lines)) {
                $lines[$line->id] = ['name' => $line->name, 'code' => $line->code];
            }
        }

        $node['lines'] = array_values($lines);

        return $node;
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
            'steps.workstation:id,name,code,line_id',
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
     * Full chain for a pallet: pallet → work order (incl. customer order) →
     * batch → consumed lots → machine/line → operator → quality controls.
     * Returns a ready-to-render array; `batch` is null for unlinked pallets.
     */
    public function palletTrace(Pallet $pallet): array
    {
        $pallet->loadMissing([
            'workOrder:id,order_no,customer_order_no,product_type_id',
            'workOrder.productType:id,name',
            'batch',
        ]);

        return [
            'pallet' => [
                'pallet_no' => $pallet->pallet_no,
                'status' => $pallet->status instanceof PalletStatus ? $pallet->status->value : $pallet->status,
                'qty' => (int) $pallet->qty,
                'location' => $pallet->location,
                'created_at' => $pallet->created_at?->format('Y-m-d H:i'),
                'shipped_at' => $pallet->shipped_at?->format('Y-m-d H:i'),
            ],
            'work_order' => $pallet->workOrder ? [
                'order_no' => $pallet->workOrder->order_no,
                'customer_order_no' => $pallet->workOrder->customer_order_no,
                'product' => $pallet->workOrder->productType?->name,
            ] : null,
            'batch' => $pallet->batch ? $this->batchChain($pallet->batch) : null,
        ];
    }

    /**
     * The batch leg of the chain: per-step machine/line/operator + consumed
     * lots, the distinct input lots, and the quality controls for the batch.
     */
    private function batchChain(Batch $batch): array
    {
        $genealogy = $this->batchGenealogy($batch);
        $b = $genealogy['batch'];
        $byStep = $genealogy['consumptions_by_step'];

        // batchGenealogy already loaded steps, steps.workstation (incl. line_id)
        // and completedBy. Only pull what's still missing - the workstation line,
        // the step starter, the batch-level machine and the quality controls.
        $b->loadMissing([
            'steps.workstation.line:id,name',
            'steps.startedBy:id,name',
            'workstation:id,name',
            'qualityChecks.checkedBy:id,name',
            'qualityChecks.samples',
        ]);

        return [
            'batch_number' => $b->batch_number,
            'lot_number' => $b->lot_number,
            'status' => $b->status,
            'machine' => $b->workstation?->name,
            'steps' => $b->steps->map(fn ($s) => [
                'step_number' => $s->step_number,
                'name' => $s->name,
                'status' => $s->status,
                'machine' => $s->workstation?->name,
                'line' => $s->workstation?->line?->name,
                'operator' => $s->completedBy?->name ?? $s->startedBy?->name,
                'completed_at' => $s->completed_at ? Carbon::parse($s->completed_at)->format('Y-m-d H:i') : null,
                'consumptions' => ($byStep[$s->id] ?? collect())->map(fn ($c) => [
                    'lot_number' => $c->materialLot?->lot_number,
                    'material' => $c->materialLot?->material?->name,
                    'quantity' => (float) $c->quantity_consumed,
                ])->values(),
            ])->values(),
            'input_lots' => $genealogy['distinct_input_lots']->map(fn ($lot) => [
                'material' => $lot->material?->name,
                'lot_number' => $lot->lot_number,
                'supplier_lot_no' => $lot->supplier_lot_no,
                'status' => $lot->status,
            ])->values(),
            'quality_checks' => $b->qualityChecks->map(fn ($qc) => [
                'all_passed' => (bool) $qc->all_passed,
                'checked_by' => $qc->checkedBy?->name,
                'checked_at' => $qc->checked_at ? Carbon::parse($qc->checked_at)->format('Y-m-d H:i') : null,
                'samples' => $qc->samples->map(fn ($s) => [
                    'parameter' => $s->parameter_name,
                    'value' => $s->value_numeric ?? $s->value_boolean,
                    'passed' => $s->is_passed,
                ])->values(),
            ])->values(),
        ];
    }

    /**
     * Aggregated trace for a customer order number (non-unique): every work
     * order carrying it, with its pallets and batches. Each pallet/lot links
     * into the deeper pallet/batch trace from the console.
     */
    public function customerOrderTrace(string $customerOrderNo): array
    {
        $workOrders = WorkOrder::where('customer_order_no', $customerOrderNo)
            ->with([
                'productType:id,name',
                'pallets:id,work_order_id,batch_id,pallet_no,status',
                'pallets.batch:id,lot_number',
                'batches:id,work_order_id,batch_number,lot_number,status',
            ])
            ->orderByDesc('id')
            ->get();

        return [
            'customer_order_no' => $customerOrderNo,
            'work_orders' => $workOrders->map(fn ($wo) => [
                'order_no' => $wo->order_no,
                'product' => $wo->productType?->name,
                'status' => $wo->status,
                'pallets' => $wo->pallets->map(fn ($p) => [
                    'pallet_no' => $p->pallet_no,
                    'status' => $p->status instanceof PalletStatus ? $p->status->value : $p->status,
                    'batch_lot' => $p->batch?->lot_number,
                ])->values(),
                'batches' => $wo->batches->map(fn ($b) => [
                    'batch_number' => $b->batch_number,
                    'lot_number' => $b->lot_number,
                    'status' => $b->status,
                ])->values(),
            ])->values(),
        ];
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

        // Pallet number (e.g. PAL-000001) — most specific, won't collide with lots.
        $pallet = Pallet::where('pallet_no', $term)->first();
        if ($pallet) {
            return ['type' => 'pallet', 'model' => $pallet];
        }

        $batch = Batch::where('lot_number', $term)->first();
        if ($batch) {
            return ['type' => 'batch', 'model' => $batch];
        }

        // Grouped so the orWhere chain can't escape the global scopes
        // (tenant + soft-delete) via AND/OR precedence.
        $lot = MaterialLot::where(function ($query) use ($term) {
            $query->where('lot_number', $term)
                ->orWhere('supplier_lot_no', $term)
                ->orWhere('source_container_no', $term);
        })->first();
        if ($lot) {
            return ['type' => 'material_lot', 'model' => $lot];
        }

        return null;
    }
}
