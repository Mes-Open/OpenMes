<?php

namespace App\Services\Material;

use App\Exceptions\InsufficientStockException;
use App\Models\Batch;
use App\Models\BatchStep;
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
    ) {}

    /**
     * Allocate materials whose BOM rows have consumed_at='start' (or
     * unspecified, treated as start for backward compat).
     *
     * Called once per batch when the first step transitions PENDING→IN_PROGRESS.
     * Materials tied to a specific step (consumed_at='during') wait for
     * that step to start; consumed_at='end' waits for the last step.
     */
    public function allocateForBatch(Batch $batch, User $user): Collection
    {
        return $this->allocateMatching($batch, $user, fn ($bom) => $this->isStartItem($bom));
    }

    /**
     * Allocate materials whose BOM rows target this specific step.
     * Called when the given step transitions PENDING→IN_PROGRESS.
     */
    public function allocateForStep(BatchStep $step, User $user): Collection
    {
        $batch = $step->batch;
        $stepNumber = $step->step_number;

        return $this->allocateMatching(
            $batch,
            $user,
            fn ($bom) => $this->isDuringItem($bom) && (int) ($bom['step_number'] ?? 0) === $stepNumber,
            stepId: $step->id,
        );
    }

    /**
     * Allocate end-of-batch materials when the final step completes.
     */
    public function allocateForBatchEnd(Batch $batch, User $user): Collection
    {
        return $this->allocateMatching($batch, $user, fn ($bom) => $this->isEndItem($bom));
    }

    /**
     * Get allocation preview for a batch (what will be allocated).
     */
    public function previewForBatch(Batch $batch): array
    {
        $bom = $batch->workOrder->process_snapshot['bom'] ?? [];
        $preview = [];

        foreach ($bom as $bomItem) {
            $material = $this->resolveMaterial($bomItem);
            $requiredQty = $this->calculateRequiredQty($bomItem, (float) $batch->target_qty);

            $preview[] = [
                'material_name' => $bomItem['material_name'] ?? $material?->name,
                'material_code' => $bomItem['material_code'] ?? $material?->code,
                'unit_of_measure' => $bomItem['unit_of_measure'] ?? $material?->unit_of_measure,
                'required_qty' => $requiredQty,
                'available_qty' => $material?->stock_quantity ?? 0,
                'sufficient' => $material ? (float) $material->stock_quantity >= $requiredQty : false,
                'material_exists' => $material !== null,
                'consumed_at' => $bomItem['consumed_at'] ?? 'start',
                'step_number' => $bomItem['step_number'] ?? null,
            ];
        }

        return $preview;
    }

    /**
     * Mark allocations as consumed when batch is completed.
     */
    public function consumeForBatch(Batch $batch): void
    {
        MaterialAllocation::where('batch_id', $batch->id)
            ->where('status', MaterialAllocation::STATUS_ALLOCATED)
            ->update([
                'status' => MaterialAllocation::STATUS_CONSUMED,
                'consumed_at' => now(),
            ]);
    }

    /**
     * Return allocated materials when batch is cancelled.
     */
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
                }

                $allocation->update([
                    'status' => MaterialAllocation::STATUS_RETURNED,
                    'returned_qty' => $allocation->allocated_qty,
                ]);
            }
        });
    }

    // ── internals ─────────────────────────────────────────────────────────────

    private function allocateMatching(Batch $batch, User $user, \Closure $filter, ?int $stepId = null): Collection
    {
        $bom = $batch->workOrder->process_snapshot['bom'] ?? [];

        if (empty($bom)) {
            return collect();
        }

        $blockNegative = $this->blockNegativeStockEnabled();

        return DB::transaction(function () use ($batch, $user, $bom, $filter, $stepId, $blockNegative) {
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

                if ($blockNegative && (float) $material->stock_quantity < $requiredQty) {
                    throw new InsufficientStockException(
                        $material,
                        $requiredQty,
                        (float) $material->stock_quantity,
                    );
                }

                // Route through StockMovementService so the ledger captures
                // the change with proper balance_after + audit fields.
                $this->stockMovements->record(
                    $material,
                    StockMovement::TYPE_ALLOCATION,
                    -$requiredQty,
                    user: $user,
                    sourceType: $stepId ? StockMovement::SOURCE_BATCH_STEP : StockMovement::SOURCE_BATCH,
                    sourceId: $stepId ?: $batch->id,
                    reason: 'Allocated to batch #'.$batch->id.($stepId ? ' (step '.$stepId.')' : ''),
                );

                $allocations->push(MaterialAllocation::create([
                    'batch_id' => $batch->id,
                    'batch_step_id' => $stepId,
                    'material_id' => $material->id,
                    'work_order_id' => $batch->work_order_id,
                    'allocated_qty' => $requiredQty,
                    'status' => MaterialAllocation::STATUS_ALLOCATED,
                    'allocated_by' => $user->id,
                    'allocated_at' => now(),
                ]));
            }

            return $allocations;
        });
    }

    private function isStartItem(array $bomItem): bool
    {
        $at = $bomItem['consumed_at'] ?? 'start';
        // 'start' or undefined → allocate at batch start
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
