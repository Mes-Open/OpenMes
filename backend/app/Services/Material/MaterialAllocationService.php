<?php

namespace App\Services\Material;

use App\Exceptions\InsufficientStockException;
use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\BatchStepLotConsumption;
use App\Models\Material;
use App\Models\MaterialAllocation;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MaterialAllocationService
{
    public function __construct(
        protected StockMovementService $stockMovements,
        protected LotPickingService $lotPicking,
    ) {}

    /**
     * @param  array<int, array<int, array{material_lot_id: int|string, picked_qty: int|float|string}>>  $picksByMaterial
     *                                                                                                                     Operator-chosen lot picks keyed by material id (WO-time override).
     * @param  int|null  $attributeStepId  Step to attribute these allocations to for
     *                                     genealogy (sets batch_step_id without changing the stock-movement source).
     */
    public function allocateForBatch(Batch $batch, User $user, array $picksByMaterial = [], ?int $attributeStepId = null): Collection
    {
        return $this->allocateMatching(
            $batch,
            $user,
            fn ($bom) => $this->isStartItem($bom),
            picksByMaterial: $picksByMaterial,
            attributeStepId: $attributeStepId,
        );
    }

    /**
     * @param  array<int, array<int, array{material_lot_id: int|string, picked_qty: int|float|string}>>  $picksByMaterial
     */
    public function allocateForStep(BatchStep $step, User $user, array $picksByMaterial = []): Collection
    {
        $batch = $step->batch;
        $stepNumber = $step->step_number;

        return $this->allocateMatching(
            $batch,
            $user,
            fn ($bom) => $this->isDuringItem($bom) && (int) ($bom['step_number'] ?? 0) === $stepNumber,
            stepId: $step->id,
            picksByMaterial: $picksByMaterial,
        );
    }

    /**
     * @param  array<int, array<int, array{material_lot_id: int|string, picked_qty: int|float|string}>>  $picksByMaterial
     */
    public function allocateForBatchEnd(Batch $batch, User $user, array $picksByMaterial = [], ?int $attributeStepId = null): Collection
    {
        return $this->allocateMatching(
            $batch,
            $user,
            fn ($bom) => $this->isEndItem($bom),
            picksByMaterial: $picksByMaterial,
            attributeStepId: $attributeStepId,
        );
    }

    public function previewForBatch(Batch $batch): array
    {
        $bom = $batch->workOrder->process_snapshot['bom'] ?? [];
        $preview = [];

        // Bulk load materials referenced by the BOM to avoid N+1. The
        // preview is read-only, so we deliberately skip lockForUpdate
        // (also: locking outside a transaction is a no-op on Postgres
        // and only adds noise/contention on SQLite).
        $materialIds = [];
        $materialCodes = [];
        foreach ($bom as $bomItem) {
            if (! empty($bomItem['material_id'])) {
                $materialIds[] = $bomItem['material_id'];
            } elseif (! empty($bomItem['material_code'])) {
                $materialCodes[] = $bomItem['material_code'];
            }
        }

        $materialsById = ! empty($materialIds)
            ? Material::whereIn('id', array_unique($materialIds))->get()->keyBy('id')
            : collect();
        $materialsByCode = ! empty($materialCodes)
            ? Material::whereIn('code', array_unique($materialCodes))->get()->keyBy('code')
            : collect();

        foreach ($bom as $bomItem) {
            $material = null;
            if (! empty($bomItem['material_id'])) {
                $material = $materialsById->get($bomItem['material_id']);
            }
            if (! $material && ! empty($bomItem['material_code'])) {
                $material = $materialsByCode->get($bomItem['material_code']);
            }
            $requiredQty = $this->calculateRequiredQty($bomItem, (float) $batch->target_qty);
            $available = $material?->available_quantity ?? 0;

            $preview[] = [
                'material_name' => $bomItem['material_name'] ?? $material?->name,
                'material_code' => $bomItem['material_code'] ?? $material?->code,
                'unit_of_measure' => $bomItem['unit_of_measure'] ?? $material?->unit_of_measure,
                'required_qty' => $requiredQty,
                // Available = on-hand minus what is already reserved by other batches.
                'available_qty' => $available,
                'on_hand_qty' => (float) ($material?->stock_quantity ?? 0),
                'reserved_qty' => (float) ($material?->reserved_quantity ?? 0),
                'sufficient' => $material ? $available >= $requiredQty : false,
                'material_exists' => $material !== null,
                'consumed_at' => $bomItem['consumed_at'] ?? 'start',
                'step_number' => $bomItem['step_number'] ?? null,
                'estimated_cost' => $material?->unit_price
                    ? round((float) $material->unit_price * $requiredQty, 2)
                    : null,
                'currency' => $material?->price_currency,
            ];
        }

        return $preview;
    }

    /**
     * Build the WO-time lot-picking proposal for starting a given step: per
     * lot-tracked material that this step start would allocate, the required
     * quantity, the system's proposed lot split, and the candidate lots. Returns
     * an empty array when lot tracking is off or nothing needs picking — the UI
     * then skips the modal and starts the step directly. Read-only.
     *
     * @return array<int, array{material_id: int, material_name: ?string, material_code: ?string, unit_of_measure: ?string, required_qty: float, strategy: string, proposed: array, candidates: array}>
     */
    public function pickPreviewForStep(BatchStep $step): array
    {
        if (! $this->lotPicking->isLotTrackingEnabled()) {
            return [];
        }

        $batch = $step->batch;
        $bom = $batch->workOrder->process_snapshot['bom'] ?? [];
        if (empty($bom)) {
            return [];
        }

        // Start-items only allocate on the first start (batch still PENDING),
        // mirroring BatchService::startStep's $wasPending gate.
        $batchPending = $batch->status === Batch::STATUS_PENDING;
        $stepNumber = $step->step_number;

        $out = [];
        foreach ($bom as $bomItem) {
            $isStart = $this->isStartItem($bomItem) && $batchPending;
            $isDuringThisStep = $this->isDuringItem($bomItem)
                && (int) ($bomItem['step_number'] ?? 0) === $stepNumber;

            if (! $isStart && ! $isDuringThisStep) {
                continue;
            }

            $material = $this->resolveMaterialReadonly($bomItem);
            if (! $material) {
                continue;
            }

            // Phase 1: lots/batches only — serial-tracked materials are handled elsewhere.
            if ($material->tracking_type === 'serial') {
                continue;
            }

            // Skip materials already allocated for this batch (mirror allocateMatching's guard).
            $alreadyAllocated = MaterialAllocation::where('batch_id', $batch->id)
                ->where('material_id', $material->id)
                ->exists();
            if ($alreadyAllocated) {
                continue;
            }

            $requiredQty = $this->calculateRequiredQty($bomItem, (float) $batch->target_qty);
            $proposal = $this->lotPicking->proposePicks($material, $requiredQty);

            $out[] = [
                'material_id' => $material->id,
                'material_name' => $bomItem['material_name'] ?? $material->name,
                'material_code' => $bomItem['material_code'] ?? $material->code,
                'unit_of_measure' => $bomItem['unit_of_measure'] ?? $material->unit_of_measure,
                'required_qty' => $requiredQty,
                'strategy' => $proposal['strategy'],
                'proposed' => $proposal['proposed'],
                'candidates' => $proposal['candidates'],
            ];
        }

        return $out;
    }

    /**
     * Mark allocations as consumed when batch is completed. Reads each
     * allocation's consumed_qty (set by recordConsumption) and falls back
     * to allocated_qty for any rows where the operator did not record an
     * explicit number. Also releases the reservation and applies any
     * leftover difference (returned + scrap) back to stock.
     */
    public function consumeForBatch(Batch $batch): void
    {
        DB::transaction(function () use ($batch) {
            $allocations = MaterialAllocation::where('batch_id', $batch->id)
                ->where('status', MaterialAllocation::STATUS_ALLOCATED)
                ->lockForUpdate()
                ->with(['material', 'lotPicks'])
                ->get();

            foreach ($allocations as $allocation) {
                // Bridge the picked lots into the ISA-95 genealogy table so
                // forward/backward traceability reflects what was actually
                // consumed. consumeForBatch runs once per batch (allocations
                // flip to CONSUMED), so this never double-writes.
                $this->writeGenealogy($allocation);

                // Default: operator did not record per-step consumption → assume planned.
                $actualConsumed = (float) $allocation->consumed_qty > 0
                    ? (float) $allocation->consumed_qty
                    : (float) $allocation->allocated_qty;

                $leftoverToReturn = max(0, (float) $allocation->allocated_qty - $actualConsumed - (float) $allocation->scrap_qty);

                $this->releaseReservation($allocation->material, (float) $allocation->allocated_qty);

                if ($leftoverToReturn > 0 && $allocation->material) {
                    $this->stockMovements->record(
                        $allocation->material,
                        StockMovement::TYPE_RETURN,
                        $leftoverToReturn,
                        sourceType: StockMovement::SOURCE_BATCH,
                        sourceId: $batch->id,
                        reason: 'Batch #'.$batch->id.' completed — leftover returned to stock',
                    );
                }

                if ((float) $allocation->scrap_qty > 0 && $allocation->material) {
                    $this->stockMovements->record(
                        $allocation->material,
                        StockMovement::TYPE_SCRAP,
                        0, // scrap is a status change, not a stock delta — already left stock at allocation time
                        sourceType: StockMovement::SOURCE_BATCH,
                        sourceId: $batch->id,
                        reason: 'Batch #'.$batch->id.' scrap qty recorded',
                    );
                }

                $allocation->update([
                    'status' => MaterialAllocation::STATUS_CONSUMED,
                    'consumed_qty' => $actualConsumed,
                    'consumed_at' => now(),
                    // Snapshot the price so historical cost reports stay stable.
                    'unit_price_snapshot' => $actualConsumed > 0 ? $allocation->material?->unit_price : null,
                    'price_currency_snapshot' => $actualConsumed > 0 ? $allocation->material?->price_currency : null,
                ]);
            }
        });
    }

    public function returnForBatch(Batch $batch): void
    {
        DB::transaction(function () use ($batch) {
            $allocations = MaterialAllocation::where('batch_id', $batch->id)
                ->where('status', MaterialAllocation::STATUS_ALLOCATED)
                ->lockForUpdate()
                ->with('material')
                ->get();

            foreach ($allocations as $allocation) {
                if ($allocation->material) {
                    $this->stockMovements->record(
                        $allocation->material,
                        StockMovement::TYPE_RETURN,
                        (float) $allocation->allocated_qty,
                        sourceType: StockMovement::SOURCE_BATCH,
                        sourceId: $allocation->batch_id,
                        reason: 'Batch #'.$allocation->batch_id.' cancelled — return to stock',
                    );
                    $this->releaseReservation($allocation->material, (float) $allocation->allocated_qty);
                }

                // Lot tracking: return picked qty back to each lot.
                $this->lotPicking->returnPicksForAllocation($allocation);

                $allocation->update([
                    'status' => MaterialAllocation::STATUS_RETURNED,
                    'returned_qty' => $allocation->allocated_qty,
                ]);
            }
        });
    }

    /**
     * Record actual consumed quantity for a single allocation. Operator
     * calls this from the post-step UI. Optionally records scrap.
     */
    public function recordConsumption(
        MaterialAllocation $allocation,
        float $actualConsumed,
        float $scrap = 0,
        ?string $notes = null,
    ): MaterialAllocation {
        if ($allocation->status !== MaterialAllocation::STATUS_ALLOCATED) {
            throw new \DomainException('Allocation must be in `allocated` status to record consumption.');
        }
        if ($actualConsumed < 0 || $scrap < 0) {
            throw new \InvalidArgumentException('Consumed and scrap quantities must be non-negative.');
        }

        $allocation->update([
            'consumed_qty' => $actualConsumed,
            'scrap_qty' => $scrap,
            // Snapshot the price so historical cost reports stay stable.
            'unit_price_snapshot' => $actualConsumed > 0 ? $allocation->material?->unit_price : null,
            'price_currency_snapshot' => $actualConsumed > 0 ? $allocation->material?->price_currency : null,
        ]);

        return $allocation->fresh();
    }

    /**
     * Mid-batch material adjustment (e.g. "operator added 5kg extra").
     * Decrements stock + reserved by the delta and bumps allocated_qty.
     */
    public function adjustAllocation(
        MaterialAllocation $allocation,
        float $deltaQty,
        User $user,
        ?string $reason = null,
    ): MaterialAllocation {
        if ($allocation->status !== MaterialAllocation::STATUS_ALLOCATED) {
            throw new \DomainException('Can only adjust allocations in `allocated` status.');
        }
        if ($deltaQty === 0.0) {
            return $allocation;
        }

        $newAllocated = (float) $allocation->allocated_qty + $deltaQty;
        if ($newAllocated < 0) {
            throw new \InvalidArgumentException('Adjustment would make allocated_qty negative.');
        }

        return DB::transaction(function () use ($allocation, $deltaQty, $user, $reason) {
            $material = $allocation->material;

            if (! $material) {
                throw new \DomainException('Allocation has no associated material.');
            }

            $this->stockMovements->record(
                $material,
                StockMovement::TYPE_ADJUSTMENT,
                -$deltaQty,
                user: $user,
                sourceType: StockMovement::SOURCE_BATCH,
                sourceId: $allocation->batch_id,
                reason: $reason ?? 'Adjustment on batch #'.$allocation->batch_id,
            );

            if ($deltaQty > 0) {
                $material->increment('reserved_quantity', $deltaQty);
            } else {
                $material->decrement('reserved_quantity', abs($deltaQty));
            }
            \App\Sync\CollectionBroadcaster::flush($material); // increment/decrement bypass model events

            $allocation->update([
                'allocated_qty' => (float) $allocation->allocated_qty + $deltaQty,
                'adjustment_qty' => (float) $allocation->adjustment_qty + $deltaQty,
            ]);

            return $allocation->fresh();
        });
    }

    // ── internals ─────────────────────────────────────────────────────────────

    /**
     * @param  array<int, array<int, array{material_lot_id: int|string, picked_qty: int|float|string}>>  $picksByMaterial
     * @param  int|null  $stepId  Drives the stock-movement source (during-items) and batch_step_id.
     * @param  int|null  $attributeStepId  Sets batch_step_id only (start/end-items), leaving the
     *                                     stock-movement source as the batch — keeps genealogy attributable without changing accounting.
     */
    private function allocateMatching(
        Batch $batch,
        User $user,
        \Closure $filter,
        ?int $stepId = null,
        array $picksByMaterial = [],
        ?int $attributeStepId = null,
    ): Collection {
        $bom = $batch->workOrder->process_snapshot['bom'] ?? [];

        if (empty($bom)) {
            return collect();
        }

        $blockNegative = $this->blockNegativeStockEnabled();
        $genealogyStepId = $stepId ?? $attributeStepId;

        return DB::transaction(function () use ($batch, $user, $bom, $filter, $stepId, $genealogyStepId, $picksByMaterial, $blockNegative) {
            $allocations = collect();

            foreach ($bom as $bomItem) {
                if (! $filter($bomItem)) {
                    continue;
                }

                $existing = MaterialAllocation::where('batch_id', $batch->id)
                    ->where('material_id', $bomItem['material_id'] ?? null)
                    ->first();
                if ($existing) {
                    $allocations->push($existing);

                    continue;
                }

                $material = $this->resolveMaterial($bomItem);
                if (! $material) {
                    continue;
                }

                $requiredQty = $this->calculateRequiredQty($bomItem, (float) $batch->target_qty);

                if ($blockNegative && $material->available_quantity < $requiredQty) {
                    throw new InsufficientStockException(
                        $material,
                        $requiredQty,
                        $material->available_quantity,
                    );
                }

                // Stock leaves the warehouse + reservation increases.
                $this->stockMovements->record(
                    $material,
                    StockMovement::TYPE_ALLOCATION,
                    -$requiredQty,
                    user: $user,
                    sourceType: $stepId ? StockMovement::SOURCE_BATCH_STEP : StockMovement::SOURCE_BATCH,
                    sourceId: $stepId ?: $batch->id,
                    reason: 'Allocated to batch #'.$batch->id.($stepId ? ' (step '.$stepId.')' : ''),
                );
                $material->increment('reserved_quantity', $requiredQty);
                \App\Sync\CollectionBroadcaster::flush($material); // increment bypasses model events

                $newAllocation = MaterialAllocation::create([
                    'batch_id' => $batch->id,
                    'batch_step_id' => $genealogyStepId,
                    'material_id' => $material->id,
                    'work_order_id' => $batch->work_order_id,
                    'allocated_qty' => $requiredQty,
                    'expected_qty' => $requiredQty,
                    'status' => MaterialAllocation::STATUS_ALLOCATED,
                    'allocated_by' => $user->id,
                    'allocated_at' => now(),
                ]);

                // Lot picking (opt-in via setting). Errors here roll back
                // the surrounding transaction so stock/reserved stay consistent.
                // When the operator supplied an explicit pick for this material
                // (WO-time "suggest + override"), honour it; otherwise auto-pick.
                if ($this->lotPicking->isLotTrackingEnabled()) {
                    $chosen = $picksByMaterial[$material->id] ?? null;
                    if (! empty($chosen)) {
                        $this->lotPicking->pickManualForAllocation($newAllocation, $material, $requiredQty, $chosen);
                    } else {
                        $this->lotPicking->pickForAllocation($newAllocation, $material, $requiredQty);
                    }
                }

                $allocations->push($newAllocation);
            }

            return $allocations;
        });
    }

    /**
     * Record one BatchStepLotConsumption row per picked lot for this allocation.
     * Requires a step to attribute to (batch_step_id is NOT NULL); allocations
     * without a step or without picks are skipped — genealogy stays optional.
     */
    private function writeGenealogy(MaterialAllocation $allocation): void
    {
        if (! $allocation->batch_step_id || $allocation->lotPicks->isEmpty()) {
            return;
        }

        foreach ($allocation->lotPicks as $pick) {
            BatchStepLotConsumption::create([
                'batch_step_id' => $allocation->batch_step_id,
                'material_lot_id' => $pick->material_lot_id,
                'sublot_id' => null, // sublots are phase 2
                'quantity_consumed' => $pick->picked_qty,
                'consumed_at' => now(),
                'recorded_by_id' => null, // system-recorded at batch completion
            ]);
        }
    }

    private function releaseReservation(?Material $material, float $qty): void
    {
        if (! $material || $qty <= 0) {
            return;
        }
        $material->decrement('reserved_quantity', $qty);
        \App\Sync\CollectionBroadcaster::flush($material); // decrement bypasses model events
    }

    private function isStartItem(array $bomItem): bool
    {
        $at = $bomItem['consumed_at'] ?? 'start';

        return $at === 'start';
    }

    private function isDuringItem(array $bomItem): bool
    {
        return ($bomItem['consumed_at'] ?? null) === 'during';
    }

    private function isEndItem(array $bomItem): bool
    {
        return ($bomItem['consumed_at'] ?? null) === 'end';
    }

    private function resolveMaterial(array $bomItem): ?Material
    {
        $query = Material::query()->lockForUpdate();

        if (! empty($bomItem['material_id'])) {
            return $query->find($bomItem['material_id']);
        }

        if (! empty($bomItem['material_code'])) {
            return $query->where('code', $bomItem['material_code'])->first();
        }

        return null;
    }

    /** Resolve a BOM item's material without locking (for read-only previews). */
    private function resolveMaterialReadonly(array $bomItem): ?Material
    {
        if (! empty($bomItem['material_id'])) {
            return Material::find($bomItem['material_id']);
        }
        if (! empty($bomItem['material_code'])) {
            return Material::where('code', $bomItem['material_code'])->first();
        }

        return null;
    }

    private function calculateRequiredQty(array $bomItem, float $targetQty): float
    {
        $baseQty = ($bomItem['quantity_per_unit'] ?? 0) * $targetQty;
        $scrapQty = $baseQty * (($bomItem['scrap_percentage'] ?? 0) / 100);

        return round($baseQty + $scrapQty, 4);
    }

    private function blockNegativeStockEnabled(): bool
    {
        try {
            $row = DB::table('system_settings')->where('key', 'block_negative_stock')->value('value');

            return (bool) json_decode($row ?? 'false', true);
        } catch (\Throwable) {
            return false;
        }
    }
}
