<?php

namespace App\Services\Material;

use App\Exceptions\InsufficientStockException;
use App\Models\Batch;
use App\Models\Material;
use App\Models\MaterialAllocation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MaterialAllocationService
{
    /**
     * Allocate materials for a batch based on work order BOM snapshot.
     * Wraps the whole loop in a transaction with per-material row locks so
     * concurrent batches cannot double-allocate the same stock. Idempotent:
     * the unique index on (batch_id, material_id) means re-running on a
     * partially-allocated batch is a no-op for already-allocated rows.
     */
    public function allocateForBatch(Batch $batch, User $user): Collection
    {
        $bom = $batch->workOrder->process_snapshot['bom'] ?? [];

        if (empty($bom)) {
            return collect();
        }

        $blockNegative = $this->blockNegativeStockEnabled();

        return DB::transaction(function () use ($batch, $user, $bom, $blockNegative) {
            $allocations = collect();

            foreach ($bom as $bomItem) {
                // Idempotency: skip if already allocated.
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

                $material->decrement('stock_quantity', $requiredQty);

                $allocations->push(MaterialAllocation::create([
                    'batch_id' => $batch->id,
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
     * Wraps in a transaction with row locks so a concurrent allocate cannot
     * race with the restore.
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
                $allocation->material?->increment('stock_quantity', (float) $allocation->allocated_qty);

                $allocation->update([
                    'status' => MaterialAllocation::STATUS_RETURNED,
                    'returned_qty' => $allocation->allocated_qty,
                ]);
            }
        });
    }

    /**
     * Resolve a Material from a BOM snapshot item. Prefers material_id
     * (immutable PK) and falls back to material_code for older snapshots
     * created before id was carried through.
     *
     * Uses lockForUpdate so concurrent allocations are serialized per material.
     */
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
