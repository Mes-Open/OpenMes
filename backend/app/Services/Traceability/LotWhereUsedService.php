<?php

namespace App\Services\Traceability;

use App\Enums\PalletStatus;
use App\Models\BatchStepLotConsumption;
use App\Models\MaterialLot;
use App\Models\SerialUnit;
use App\Models\SerialUnitComponent;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Unit-level "where-used" for a material lot: which serialised units the lot
 * was scanned into, and - walking up the component bindings - which units
 * contain those (a pinion inside a repair kit inside a carton of kits). The
 * containment answer for a complaint, in one call.
 *
 * Precision is reported, not assumed. When the lot has component bindings the
 * answer is exact per unit ("unit"). When it only has batch-step consumption
 * records, every unit of a consuming batch is a candidate - an over-count
 * the client must know about ("batch"). With neither, "none".
 */
class LotWhereUsedService
{
    public const DEFAULT_MAX_DEPTH = 10;

    public const MAX_DEPTH = 20;

    public const DEFAULT_LIMIT = 1000;

    public const MAX_LIMIT = 5000;

    /** IN-list size per query - well under every driver's bound-parameter cap. */
    private const CHUNK = 500;

    /**
     * @return array<string, mixed>
     */
    public function whereUsed(
        MaterialLot $lot,
        int $maxDepth = self::DEFAULT_MAX_DEPTH,
        int $limit = self::DEFAULT_LIMIT,
        bool $includeRemoved = false,
    ): array {
        $maxDepth = max(0, min($maxDepth, self::MAX_DEPTH));
        $limit = max(1, min($limit, self::MAX_LIMIT));
        // A recall must still name the part after its material was deleted.
        $lot->load(['material' => fn ($q) => $q->withTrashed()->select('id', 'code', 'name')]);

        // --- 1. Units the lot was bound into directly --------------------------
        // One scan for every count: active / removed bindings, distinct units.
        $stats = $this->directBindings($lot)
            ->selectRaw('COUNT(*) AS bindings')
            ->selectRaw('SUM(CASE WHEN unbound_at IS NULL THEN 1 ELSE 0 END) AS active_bindings')
            ->selectRaw('COUNT(DISTINCT serial_unit_id) AS units')
            ->selectRaw('COUNT(DISTINCT CASE WHEN unbound_at IS NULL THEN serial_unit_id END) AS active_units')
            ->toBase()
            ->first();
        $removedBindings = (int) $stats->bindings - (int) $stats->active_bindings;
        // Precision describes what is returned: removed bindings count only when asked for.
        $hasUnitBindings = $includeRemoved ? (int) $stats->bindings > 0 : (int) $stats->active_bindings > 0;
        $directTotal = $includeRemoved ? (int) $stats->units : (int) $stats->active_units;

        $direct = $this->directBindings($lot)
            ->when(! $includeRemoved, fn (Builder $q) => $q->whereNull('unbound_at'));
        $directIds = (clone $direct)
            ->select('serial_unit_id')
            ->distinct()
            ->orderBy('serial_unit_id')
            ->limit($limit)
            ->pluck('serial_unit_id')
            ->all();

        $bindingsByUnit = collect();
        foreach (array_chunk($directIds, self::CHUNK) as $chunk) {
            $bindingsByUnit = $bindingsByUnit->concat(
                (clone $direct)->whereIn('serial_unit_id', $chunk)
                    ->orderBy('bound_at')->orderBy('id')
                    ->get(['id', 'serial_unit_id', 'identifier', 'material_lot_id', 'quantity', 'bound_at', 'unbound_at', 'unbind_reason'])
            );
        }
        $bindingsByUnit = $bindingsByUnit->groupBy('serial_unit_id');

        // --- 2. Walk up: the units that contain them ---------------------------
        $depthOf = array_fill_keys($directIds, 0);
        $parentsOf = []; // child id => [parent id => installed?]
        $cutOff = []; // child id => true: an installed parent of it was not walked (depth / limit)
        $frontier = $directIds;
        $truncatedByLimit = $directTotal > count($directIds);
        $truncatedByDepth = false;

        for ($depth = 1; ! empty($frontier); $depth++) {
            $edges = collect();
            foreach (array_chunk($frontier, self::CHUNK) as $chunk) {
                $edges = $edges->concat(
                    SerialUnitComponent::query()
                        ->whereIn('component_serial_unit_id', $chunk)
                        ->when(! $includeRemoved, fn (Builder $q) => $q->whereNull('unbound_at'))
                        ->get(['id', 'serial_unit_id', 'component_serial_unit_id', 'unbound_at'])
                );
            }
            if ($edges->isEmpty()) {
                break;
            }

            $next = [];
            foreach ($edges as $edge) {
                $childId = $edge->component_serial_unit_id;
                $parentId = $edge->serial_unit_id;
                $installed = $edge->unbound_at === null;
                $parentsOf[$childId][$parentId] = ($parentsOf[$childId][$parentId] ?? false) || $installed;

                // Already seen (a diamond, or a cycle in corrupted data): never re-walk.
                if (isset($depthOf[$parentId])) {
                    continue;
                }
                if ($depth > $maxDepth || count($depthOf) >= $limit) {
                    if ($depth > $maxDepth) {
                        $truncatedByDepth = true;
                    } else {
                        $truncatedByLimit = true;
                    }
                    if ($installed) {
                        $cutOff[$childId] = true;
                    }

                    continue;
                }
                $depthOf[$parentId] = $depth;
                $next[] = $parentId;
            }
            $frontier = $next;
        }

        // --- 3. Load every unit once, with its context -------------------------
        $units = collect();
        foreach (array_chunk(array_keys($depthOf), self::CHUNK) as $chunk) {
            $units = $units->concat($this->unitQuery()->whereIn('id', $chunk)->get());
        }
        $units = $units->keyBy('id');

        $childrenOf = [];
        foreach ($parentsOf as $childId => $parents) {
            foreach (array_keys($parents) as $parentId) {
                $childrenOf[$parentId][] = $childId;
            }
        }

        $rows = collect($depthOf)
            ->map(function (int $depth, int $id) use ($units, $parentsOf, $childrenOf, $bindingsByUnit, $depthOf, $cutOff) {
                $unit = $units->get($id);
                if (! $unit) {
                    return null; // outside the caller's tenant - not theirs to see
                }
                $ref = fn (int $other) => $units->has($other)
                    ? ['id' => $other, 'serial_no' => $units[$other]->serial_no, 'psn' => $units[$other]->psn]
                    : null;

                // Outermost unit: installed in nothing. Unknown (null) when the
                // parent it is installed in lies beyond max_depth / limit.
                $installedInReturned = collect($parentsOf[$id] ?? [])
                    ->contains(fn (bool $installed, int $parent) => $installed && isset($depthOf[$parent]));
                $topLevel = $installedInReturned ? false : (isset($cutOff[$id]) ? null : true);

                $row = $this->unitRow($unit) + [
                    'depth' => $depth,
                    'top_level' => $topLevel,
                    'contained_in' => collect(array_keys($parentsOf[$id] ?? []))->map($ref)->filter()->values()->all(),
                ];
                if ($depth === 0) {
                    $row['bindings'] = ($bindingsByUnit[$id] ?? collect())->map(fn (SerialUnitComponent $b) => [
                        'identifier' => $b->identifier,
                        'match' => $b->material_lot_id ? 'material_lot' : 'identifier',
                        'quantity' => (float) $b->quantity,
                        'installed' => $b->unbound_at === null,
                        'bound_at' => $b->bound_at?->toIso8601String(),
                        'unbound_at' => $b->unbound_at?->toIso8601String(),
                        'unbind_reason' => $b->unbind_reason,
                    ])->values()->all();
                }
                // Which affected units inside it carry the lot.
                $row['contains'] = collect($childrenOf[$id] ?? [])
                    ->filter(fn ($child) => isset($units[$child]))
                    ->map($ref)->values()->all();

                return $row;
            })
            ->filter()
            ->sortBy([['depth', 'asc'], ['id', 'asc']])
            ->values();

        $directRows = $rows->where('depth', 0)->values();
        $parentRows = $rows->where('depth', '>', 0)->values();

        // --- 4. Batch-level consumption (the fallback, and a cross-check) ------
        $batchLevel = $this->batchLevel($lot, $units->only($directIds)->values());

        $precision = $hasUnitBindings ? 'unit' : ($batchLevel['work_orders'] !== [] ? 'batch' : 'none');

        return [
            'lot' => [
                'id' => $lot->id,
                'lot_number' => $lot->lot_number,
                'supplier_lot_no' => $lot->supplier_lot_no,
                'status' => $lot->status,
                'material' => $lot->material?->only(['id', 'code', 'name']),
            ],
            'precision' => $precision,
            'has_unit_bindings' => $hasUnitBindings,
            'params' => ['max_depth' => $maxDepth, 'limit' => $limit, 'include_removed' => $includeRemoved],
            'truncated' => $truncatedByLimit || $truncatedByDepth,
            'truncated_by' => array_values(array_filter([
                $truncatedByLimit ? 'limit' : null,
                $truncatedByDepth ? 'depth' : null,
            ])),
            'totals' => [
                'direct_units' => $directTotal,
                'direct_units_returned' => $directRows->count(),
                'parent_units' => $parentRows->count(),
                'top_level_units' => $rows->where('top_level', true)->count(),
                'top_level_unknown_units' => $rows->whereNull('top_level')->count(),
                'removed_bindings' => $removedBindings,
                'work_orders' => $rows->pluck('work_order.id')->filter()->unique()->count(),
                'by_status' => $rows->countBy('status')->sortKeys()->all(),
            ],
            'work_orders' => $this->workOrderSummary($rows),
            'direct_units' => $directRows->all(),
            'parent_units' => $parentRows->all(),
            'batch_level' => $batchLevel,
        ];
    }

    /**
     * Bindings that name this lot: resolved to it at scan time, or scanned by
     * its number before the lot was known (identifier-only rows).
     */
    private function directBindings(MaterialLot $lot): Builder
    {
        $folded = strtoupper(str_replace(' ', '', (string) $lot->lot_number));

        return SerialUnitComponent::query()->where(function (Builder $q) use ($lot, $folded) {
            $q->where('material_lot_id', $lot->id)
                ->orWhere(fn (Builder $q) => $q->whereNull('material_lot_id')
                    ->whereNull('component_serial_unit_id')
                    // Both sides folded (case, spaces) the way bindComponent matches
                    // a scan to a lot, so the plant's normalise setting doesn't matter.
                    ->whereRaw("UPPER(REPLACE(identifier, ' ', '')) = ?", [$folded]));
        });
    }

    private function unitQuery(): Builder
    {
        // Recall must see through soft-deleted work orders / batches / pallets.
        return SerialUnit::query()
            ->select(['id', 'serial_no', 'psn', 'status', 'work_order_id', 'batch_id', 'pallet_id', 'material_id', 'produced_at', 'shipped_at'])
            ->with([
                ...$this->workOrderEagerLoad('workOrder'),
                'batch' => fn ($q) => $q->withTrashed()->select('id', 'batch_number', 'lot_number'),
                'pallet' => fn ($q) => $q->withTrashed()->select('id', 'pallet_no', 'status'),
                'material' => fn ($q) => $q->withTrashed()->select('id', 'code', 'name'),
            ]);
    }

    /**
     * Eager loads for a work order and its product, soft-deleted ones included
     * (a recall must still name them), under the given relation path.
     *
     * @return array<string, \Closure>
     */
    private function workOrderEagerLoad(string $path): array
    {
        return [
            $path => fn ($q) => $q->withTrashed()->select('id', 'order_no', 'status', 'product_type_id', 'customer_order_no'),
            $path.'.productType' => fn ($q) => $q->withTrashed()->select('id', 'name', 'code'),
        ];
    }

    /** @return array<string, mixed>|null */
    private function workOrderRow(?WorkOrder $workOrder): ?array
    {
        return $workOrder ? [
            'id' => $workOrder->id,
            'order_no' => $workOrder->order_no,
            'status' => $workOrder->status,
            'customer_order_no' => $workOrder->customer_order_no,
            'product' => $workOrder->productType?->name,
        ] : null;
    }

    /** @return array<string, mixed> */
    private function unitRow(SerialUnit $unit): array
    {
        $palletStatus = $unit->pallet?->status;

        return [
            'id' => $unit->id,
            'serial_no' => $unit->serial_no,
            'psn' => $unit->psn,
            'status' => $unit->status,
            'material' => $unit->material?->only(['id', 'code', 'name']),
            'work_order' => $this->workOrderRow($unit->workOrder),
            'batch' => $unit->batch?->only(['id', 'batch_number', 'lot_number']),
            'pallet' => $unit->pallet ? [
                'id' => $unit->pallet->id,
                'pallet_no' => $unit->pallet->pallet_no,
                'status' => $palletStatus instanceof PalletStatus ? $palletStatus->value : $palletStatus,
            ] : null,
            'produced_at' => $unit->produced_at?->toIso8601String(),
            'shipped_at' => $unit->shipped_at?->toIso8601String(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function workOrderSummary(Collection $rows): array
    {
        return $rows
            ->groupBy(fn (array $row) => $row['work_order']['id'] ?? 0)
            ->map(fn (Collection $group) => [
                'work_order' => $group->first()['work_order'],
                'direct_units' => $group->where('depth', 0)->count(),
                'parent_units' => $group->where('depth', '>', 0)->count(),
                'top_level_units' => $group->where('top_level', true)->count(),
                'by_status' => $group->countBy('status')->sortKeys()->all(),
            ])
            ->sortBy(fn (array $s) => $s['work_order']['order_no'] ?? "\u{10FFFF}")
            ->values()
            ->all();
    }

    /**
     * The lot's batch-step consumption records, per work order and batch, with
     * how many units each consuming batch produced ("candidates": every one of
     * them may contain the lot) against how many are proven by a binding.
     *
     * Direct consumption only - multi-stage chains through output lots are
     * what GET /material-lots/{lot}/genealogy/forward follows.
     *
     * @param  Collection<int, SerialUnit>  $boundUnits  the units bound to the lot directly
     * @return array{work_orders: list<array<string, mixed>>, totals: array<string, mixed>}
     */
    private function batchLevel(MaterialLot $lot, Collection $boundUnits): array
    {
        $consumptions = BatchStepLotConsumption::query()
            ->where('material_lot_id', $lot->id)
            ->with([
                // Deleting a batch soft-deletes its steps: the consumption must still show.
                'batchStep' => fn ($q) => $q->withTrashed()->select('id', 'batch_id'),
                'batchStep.batch' => fn ($q) => $q->withTrashed()->select('id', 'batch_number', 'lot_number', 'work_order_id'),
                ...$this->workOrderEagerLoad('batchStep.batch.workOrder'),
            ])
            ->get(['id', 'batch_step_id', 'material_lot_id', 'quantity_consumed']);

        $byBatch = $consumptions
            ->filter(fn ($c) => $c->batchStep?->batch !== null)
            ->groupBy(fn ($c) => $c->batchStep->batch->id);

        $batchIds = $byBatch->keys()->all();
        $candidates = [];
        foreach (array_chunk($batchIds, self::CHUNK) as $chunk) {
            $candidates += SerialUnit::query()
                ->whereIn('batch_id', $chunk)
                ->groupBy('batch_id')
                ->select('batch_id', DB::raw('COUNT(*) as units'))
                ->pluck('units', 'batch_id')
                ->map(fn ($n) => (int) $n)
                ->all();
        }
        $boundPerBatch = $boundUnits->filter(fn (SerialUnit $u) => $u->batch_id !== null)->countBy('batch_id');

        $workOrders = $byBatch
            ->map(function (Collection $group, int $batchId) use ($candidates, $boundPerBatch) {
                $batch = $group->first()->batchStep->batch;

                return [
                    'work_order' => $this->workOrderRow($batch->workOrder),
                    'batch' => [
                        'id' => $batch->id,
                        'batch_number' => $batch->batch_number,
                        'lot_number' => $batch->lot_number,
                        'quantity_consumed' => round((float) $group->sum('quantity_consumed'), 4),
                        'candidate_units' => $candidates[$batchId] ?? 0,
                        'bound_units' => (int) ($boundPerBatch[$batchId] ?? 0),
                    ],
                ];
            })
            ->groupBy(fn (array $r) => $r['work_order']['id'] ?? 0)
            ->map(fn (Collection $group) => [
                'work_order' => $group->first()['work_order'],
                'quantity_consumed' => round($group->sum('batch.quantity_consumed'), 4),
                'candidate_units' => $group->sum('batch.candidate_units'),
                'batches' => $group->pluck('batch')->sortBy('id')->values()->all(),
            ])
            ->sortBy(fn (array $s) => $s['work_order']['order_no'] ?? "\u{10FFFF}")
            ->values()
            ->all();

        return [
            'work_orders' => $workOrders,
            'totals' => [
                'work_orders' => count($workOrders),
                'batches' => count($batchIds),
                'candidate_units' => array_sum($candidates),
                'quantity_consumed' => round((float) $consumptions->sum('quantity_consumed'), 4),
            ],
        ];
    }
}
