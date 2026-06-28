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
     * Forward trace: every batch step / batch / work order that consumed the lot,
     * plus - for a finished-goods lot (source_batch_id set) - the output pallet(s)
     * it was packed onto and the customer order(s) it fulfils. Genealogy no longer
     * dead-ends at the finished lot: it continues to pallet and customer order.
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

        $packing = $this->finishedGoodsPacking($lot);

        return [
            'lot' => $lot->only(['id', 'lot_number', 'material_id', 'status', 'supplier_lot_no', 'source_container_no']),
            'consumptions' => $consumptions,
            'work_orders' => $workOrders,
            'total_consumed' => (float) $consumptions->sum('quantity_consumed'),
            'is_finished_good' => $lot->source_batch_id !== null,
            'pallets' => $packing['pallets'],
            'customer_orders' => $packing['customer_orders'],
        ];
    }

    /**
     * The output pallet(s) a finished-goods lot was packed onto and the customer
     * order(s) it fulfils. Derived through the producing batch: a pallet pins to
     * one batch (pallets.batch_id) and a finished lot pins to the same batch
     * (material_lots.source_batch_id), so the link is the batch they share.
     *
     * A raw/inbound lot (no source_batch_id) returns empty collections.
     *
     * @return array{pallets: \Illuminate\Support\Collection, customer_orders: \Illuminate\Support\Collection}
     */
    private function finishedGoodsPacking(MaterialLot $lot): array
    {
        if ($lot->source_batch_id === null) {
            return ['pallets' => collect(), 'customer_orders' => collect()];
        }

        // withTrashed: a finished lot must still resolve its pallets / customer
        // order if the producing batch was later soft-deleted (recall readiness).
        $pallets = Pallet::where('batch_id', $lot->source_batch_id)
            ->with(['workOrder:id,order_no,customer_order_no'])
            ->orderBy('pallet_no')
            ->get();

        $batch = Batch::withTrashed()
            ->with(['workOrder' => fn ($q) => $q->withTrashed()->select('id', 'order_no', 'customer_order_no')])
            ->find($lot->source_batch_id);

        // Customer order(s): the producing batch's work order plus any pallet's
        // own work order (normally the same), de-duplicated and null-stripped.
        $customerOrders = collect([$batch?->workOrder?->customer_order_no])
            ->merge($pallets->map(fn (Pallet $p) => $p->workOrder?->customer_order_no))
            ->filter()
            ->unique()
            ->values();

        $pallets = $pallets->map(fn (Pallet $p) => [
            'pallet_no' => $p->pallet_no,
            'status' => $p->status instanceof PalletStatus ? $p->status->value : $p->status,
            'qty' => (int) $p->qty,
            'location' => $p->location,
            'shipped_at' => $p->shipped_at?->format('Y-m-d H:i'),
        ])->values();

        return ['pallets' => $pallets, 'customer_orders' => $customerOrders];
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
        $affectedBatchIds = []; // batch_id => true (batches that consumed the recalled material)
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
                    // Recall must see through soft-deleted intermediates, otherwise
                    // a deleted batch/WO would hide downstream affected lots.
                    'batchStep.batch' => fn ($q) => $q->withTrashed()->select('id', 'batch_number', 'work_order_id'),
                    'batchStep.batch.workOrder' => fn ($q) => $q->withTrashed()->select('id', 'order_no', 'product_type_id', 'status'),
                    'batchStep.batch.workOrder.productType:id,name,code',
                ])
                ->get();

            $consumingBatchIds = [];

            foreach ($consumptions as $consumption) {
                $batch = $consumption->batchStep?->batch;
                if (! $batch) {
                    continue; // orphaned consumption (no batch step / batch at all)
                }

                // Track the batch for the downstream walk even if its WO is gone.
                $consumingBatchIds[$batch->id] = true;
                $affectedBatchIds[$batch->id] = true;

                $workOrder = $batch->workOrder;
                if (! $workOrder) {
                    continue; // WO unrecoverable; batch still walked downstream
                }

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
            $frontier = MaterialLot::withTrashed()
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
                // Only units from batches that consumed the recalled material;
                // null batch_id is the legacy fallback (pre-batch-linked serials).
                ->where(function ($query) use ($affectedBatchIds) {
                    $query->whereIn('batch_id', array_keys($affectedBatchIds))
                        ->orWhereNull('batch_id');
                })
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
            // withTrashed: recall must still resolve a pallet whose batch was later
            // soft-deleted, otherwise the genealogy link silently disappears.
            'batch' => fn ($q) => $q->withTrashed(),
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
     * order carrying it, with its pallets and batches. Each batch descends to
     * the finished-goods lots it produced (output_lots) and the component lots
     * consumed to make them (components), so a customer order traces backward
     * all the way to the LOTs and components used. Each pallet/lot links into
     * the deeper pallet/batch trace from the console.
     */
    public function customerOrderTrace(string $customerOrderNo): array
    {
        $workOrders = WorkOrder::where('customer_order_no', $customerOrderNo)
            ->with([
                'productType:id,name',
                'pallets:id,work_order_id,batch_id,pallet_no,status',
                'pallets.batch:id,lot_number',
                'batches:id,work_order_id,batch_number,lot_number,status',
                'batches.outputLots:id,source_batch_id,lot_number,material_id,status',
                'batches.outputLots.material:id,name',
            ])
            ->orderByDesc('id')
            ->get();

        // Precompute the consumed (component) lots for every batch in one pass,
        // instead of two queries per batch inside the map below (N+1).
        $batchIds = $workOrders->pluck('batches')->flatten(1)->pluck('id')->filter()->unique()->all();
        $inputLotsByBatch = $this->inputLotsByBatch($batchIds);

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
                    'output_lots' => $b->outputLots->map(fn ($o) => [
                        'lot_number' => $o->lot_number,
                        'material' => $o->material?->name,
                        'status' => $o->status,
                    ])->values(),
                    'components' => collect($inputLotsByBatch[$b->id] ?? [])->map(fn ($lot) => [
                        'lot_number' => $lot->lot_number,
                        'material' => $lot->material?->name,
                        'supplier_lot_no' => $lot->supplier_lot_no,
                        'status' => $lot->status,
                    ])->values(),
                ])->values(),
            ])->values(),
        ];
    }

    /**
     * Distinct consumed (component) material lots for many batches at once,
     * keyed by batch id - two queries total regardless of batch count.
     *
     * @param  array<int, int>  $batchIds
     * @return array<int, array<int, MaterialLot>>
     */
    private function inputLotsByBatch(array $batchIds): array
    {
        if (empty($batchIds)) {
            return [];
        }

        $pairs = BatchStepLotConsumption::query()
            ->join('batch_steps', 'batch_step_lot_consumption.batch_step_id', '=', 'batch_steps.id')
            ->whereNull('batch_steps.deleted_at')
            ->whereIn('batch_steps.batch_id', $batchIds)
            ->select('batch_steps.batch_id', 'batch_step_lot_consumption.material_lot_id')
            ->distinct()
            ->get();

        $lots = MaterialLot::with('material:id,name,code')
            ->whereIn('id', $pairs->pluck('material_lot_id')->unique())
            ->get()
            ->keyBy('id');

        $map = [];
        foreach ($pairs as $pair) {
            $lot = $lots->get($pair->material_lot_id);
            if ($lot) {
                $map[$pair->batch_id][] = $lot;
            }
        }

        return $map;
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

        // Pallet number (e.g. PAL-000001) - most specific, won't collide with lots.
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
