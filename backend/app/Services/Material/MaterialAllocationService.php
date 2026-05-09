<?php

namespace App\Services\Material;

use App\Models\Batch;
use App\Models\Material;
use App\Models\MaterialAllocation;
use App\Models\User;
use Illuminate\Support\Collection;

class MaterialAllocationService
{
    /**
     * Allocate materials for a batch based on work order BOM snapshot.
     * Deducts stock_quantity from materials.
     *
     * @return Collection<MaterialAllocation>
     */
    public function allocateForBatch(Batch $batch, User $user): Collection
    {
        $workOrder = $batch->workOrder;
        $bom = $workOrder->process_snapshot['bom'] ?? [];

        if (empty($bom)) {
            return collect();
        }

        $allocations = collect();

        foreach ($bom as $bomItem) {
            $material = Material::where('code', $bomItem['material_code'])->first();

            if (! $material) {
                continue;
            }

            // Calculate required quantity for this batch
            $baseQty = $bomItem['quantity_per_unit'] * $batch->target_qty;
            $scrapQty = $baseQty * ($bomItem['scrap_percentage'] / 100);
            $requiredQty = round($baseQty + $scrapQty, 4);

            // Deduct from stock
            $material->decrement('stock_quantity', $requiredQty);

            // Create allocation record
            $allocation = MaterialAllocation::create([
                'batch_id' => $batch->id,
                'material_id' => $material->id,
                'work_order_id' => $workOrder->id,
                'allocated_qty' => $requiredQty,
                'status' => MaterialAllocation::STATUS_ALLOCATED,
                'allocated_by' => $user->id,
                'allocated_at' => now(),
            ]);

            $allocations->push($allocation);
        }

        return $allocations;
    }

    /**
     * Get allocation preview for a batch (what will be allocated).
     * Used for operator confirmation dialog.
     */
    public function previewForBatch(Batch $batch): array
    {
        $workOrder = $batch->workOrder;
        $bom = $workOrder->process_snapshot['bom'] ?? [];

        if (empty($bom)) {
            return [];
        }

        $preview = [];

        foreach ($bom as $bomItem) {
            $material = Material::where('code', $bomItem['material_code'])->first();

            $baseQty = $bomItem['quantity_per_unit'] * $batch->target_qty;
            $scrapQty = $baseQty * ($bomItem['scrap_percentage'] / 100);
            $requiredQty = round($baseQty + $scrapQty, 4);

            $preview[] = [
                'material_name' => $bomItem['material_name'],
                'material_code' => $bomItem['material_code'],
                'unit_of_measure' => $bomItem['unit_of_measure'],
                'required_qty' => $requiredQty,
                'available_qty' => $material?->stock_quantity ?? 0,
                'sufficient' => $material ? $material->stock_quantity >= $requiredQty : false,
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
     * Restores stock_quantity.
     */
    public function returnForBatch(Batch $batch): void
    {
        $allocations = MaterialAllocation::where('batch_id', $batch->id)
            ->where('status', MaterialAllocation::STATUS_ALLOCATED)
            ->get();

        foreach ($allocations as $allocation) {
            $allocation->material->increment('stock_quantity', $allocation->allocated_qty);

            $allocation->update([
                'status' => MaterialAllocation::STATUS_RETURNED,
                'returned_qty' => $allocation->allocated_qty,
            ]);
        }
    }
}
