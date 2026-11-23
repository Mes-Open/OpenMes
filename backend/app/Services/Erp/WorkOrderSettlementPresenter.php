<?php

namespace App\Services\Erp;

use App\Models\AdditionalCost;
use App\Models\AllocationLotPick;
use App\Models\BatchStep;
use App\Models\MaterialAllocation;
use App\Models\ScrapEntry;
use App\Models\Worker;
use App\Models\WorkOrder;
use App\Services\Production\ProductionCostService;
use App\Services\Production\StepTimeService;

/**
 * Settlement sections of the ERP work-order export (`?include=`): what an ERP
 * needs to close a production order in one call — actual material consumption
 * with lots, scrap, and costs.
 *
 * Sources (the same ones the rest of the application uses):
 *  - consumption: the work order's material allocations, where the work-order
 *    page records consumption (consumed / scrap / returned qty) and lot picks;
 *  - scrap: the order's scrap entries (soft-deleted ones excluded);
 *  - costs: ProductionCostService::breakdown(), i.e. the same numbers as the
 *    Production Cost report (materials, labor, additional costs). There is no
 *    machine-cost model in the application, so none is emitted;
 *  - operations: every batch step with its standard, operator-confirmed and
 *    recorded operator (labor) and machine minutes (#400), for an ERP that
 *    costs time itself.
 *
 * Read-only. Every child row is reached through the order's own relations, so
 * the order's tenant scope bounds everything returned. Person names (workers,
 * reporters) are intentionally left out.
 */
class WorkOrderSettlementPresenter
{
    public function __construct(private ProductionCostService $costs) {}

    /**
     * Eager-load paths for the requested sections, so a page of orders costs a
     * constant number of queries regardless of its size.
     *
     * @param  list<string>  $includes
     */
    public function relations(array $includes): array
    {
        $relations = [];

        if (in_array('consumption', $includes, true) || in_array('costs', $includes, true)) {
            // One column list for both sections: two different constraints on the
            // same path would make the later one win and drop columns. A material
            // archived after the order closed is still the material that was used —
            // the ERP needs its code to post the consumption.
            $relations['materialAllocations.material'] = fn ($q) => $q->withTrashed()->select('id', 'code', 'name', 'unit_of_measure', 'unit_price', 'price_currency');
        }

        if (in_array('consumption', $includes, true)) {
            // A lot archived after the order closed is still the lot that was used.
            $relations['materialAllocations.lotPicks.lot'] = fn ($q) => $q->withTrashed()->select('id', 'lot_number');
        }

        if (in_array('scrap', $includes, true)) {
            $relations['scrapEntries.scrapReason'] = fn ($q) => $q->withTrashed()->select('id', 'code', 'name', 'category');
            $relations['scrapEntries.batchStep'] = fn ($q) => $q->select('id', 'batch_id', 'step_number', 'name');
            $relations['scrapEntries.batchStep.batch'] = fn ($q) => $q->select('id', 'batch_number');
        }

        if (in_array('costs', $includes, true)) {
            // BOM fallback for materials without recorded consumption.
            $relations[] = 'productType.processTemplates.bomItems.material';
            $relations[] = 'productType.processTemplates.bomItems.templateStep';
            $relations[] = 'employeeActivities.worker';
            // wageGroup is attached by an optional module; eager-loading it
            // unconditionally is a 500 on an installation without it.
            if (Worker::hasModuleRelation('wageGroup')) {
                $relations[] = 'employeeActivities.worker.wageGroup';
            }
            $relations['additionalCosts.costSource'] = fn ($q) => $q->withTrashed()->select('id', 'code', 'name');
        }

        if (in_array('operations', $includes, true)) {
            $relations['batches.steps.workstation'] = fn ($q) => $q->withTrashed()->select('id', 'code', 'name');
        }

        return $relations;
    }

    /**
     * The requested sections for one (eager-loaded) work order, keyed by name.
     *
     * @param  list<string>  $includes
     * @return array<string, array>
     */
    public function sections(WorkOrder $wo, array $includes): array
    {
        $out = [];

        if (in_array('consumption', $includes, true)) {
            $out['consumption'] = $this->consumption($wo);
        }
        if (in_array('scrap', $includes, true)) {
            $out['scrap'] = $this->scrap($wo);
        }
        if (in_array('costs', $includes, true)) {
            $out['costs'] = $this->costs($wo);
        }
        if (in_array('operations', $includes, true)) {
            $out['operations'] = $this->operations($wo);
        }

        return $out;
    }

    private function consumption(WorkOrder $wo): array
    {
        return $wo->materialAllocations
            ->filter(fn (MaterialAllocation $a) => (float) $a->consumed_qty > 0 || (float) $a->scrap_qty > 0)
            ->map(fn (MaterialAllocation $a) => [
                'material_code' => $a->material?->code,
                'material_name' => $a->material?->name,
                'unit_of_measure' => $a->material?->unit_of_measure,
                'consumed_qty' => (float) $a->consumed_qty,
                'scrap_qty' => (float) $a->scrap_qty,
                'returned_qty' => (float) $a->returned_qty,
                'status' => $a->status,
                'consumed_at' => $a->consumed_at?->toIso8601String(),
                'lots' => $a->lotPicks->map(fn (AllocationLotPick $pick) => [
                    'lot_number' => $pick->lot?->lot_number,
                    'quantity' => (float) $pick->picked_qty,
                ])->values()->all(),
            ])
            ->sortBy('material_code')
            ->values()
            ->all();
    }

    private function scrap(WorkOrder $wo): array
    {
        $entries = $wo->scrapEntries->sortBy('id');

        return [
            'total_qty' => round((float) $entries->sum(fn (ScrapEntry $e) => (float) $e->quantity), 4),
            'entries' => $entries->map(fn (ScrapEntry $e) => [
                'quantity' => (float) $e->quantity,
                'reason' => $e->scrapReason ? [
                    'code' => $e->scrapReason->code,
                    'name' => $e->scrapReason->name,
                    'category' => $e->scrapReason->category,
                ] : null,
                'step' => $e->batchStep ? [
                    'batch_number' => $e->batchStep->batch?->batch_number,
                    'step_number' => $e->batchStep->step_number,
                    'name' => $e->batchStep->name,
                ] : null,
                'reported_at' => $e->reported_at?->toIso8601String(),
                'notes' => $e->notes,
            ])->values()->all(),
        ];
    }

    /** Every step of every batch with its times; labor and machine minutes are null when nothing was recorded. */
    private function operations(WorkOrder $wo): array
    {
        return $wo->batches->sortBy('batch_number')
            ->flatMap(fn ($batch) => $batch->steps->sortBy('step_number')->map(fn (BatchStep $s) => StepTimeService::operation($s, $batch)))
            ->values()
            ->all();
    }

    private function costs(WorkOrder $wo): array
    {
        $b = $this->costs->breakdown($wo);

        return [
            'currency' => $b['currency'],
            'mixed_currency' => $b['mixed_currency'],
            'total_cost' => $b['total_cost'],
            'cost_per_unit' => $b['cost_per_unit'],
            'materials' => [
                'total' => $b['materials']['total'],
                'items' => array_map(fn (array $i) => [
                    'material_code' => $i['material_code'],
                    'source' => $i['source'],
                    'qty' => $i['qty'],
                    'unit_price' => $i['unit_price'],
                    'currency' => $i['currency'],
                    'line_total' => $i['line_total'],
                ], $b['materials']['items']),
            ],
            'labor' => [
                'total' => $b['labor']['total'],
                // worker_name dropped on purpose: the code identifies the worker
                // to the ERP without exporting personal data.
                'items' => array_map(fn (array $i) => [
                    'worker_code' => $i['worker_code'],
                    'pay_type' => $i['pay_type'],
                    'basis' => $i['basis'],
                    'basis_unit' => $i['basis_unit'],
                    'rate' => $i['rate'],
                    'currency' => $i['currency'],
                    'line_total' => $i['line_total'],
                ], $b['labor']['items']),
            ],
            'additional' => [
                'total' => $b['additional']['total'],
                'items' => $wo->additionalCosts->sortBy('id')->map(fn (AdditionalCost $c) => [
                    'description' => $c->description,
                    'cost_source_code' => $c->costSource?->code,
                    'cost_source_name' => $c->costSource?->name,
                    'amount' => round((float) $c->amount, 2),
                    'currency' => $c->currency ?: $b['currency'],
                ])->values()->all(),
            ],
        ];
    }
}
