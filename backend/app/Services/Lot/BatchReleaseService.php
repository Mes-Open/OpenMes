<?php

namespace App\Services\Lot;

use App\Models\Batch;
use App\Models\BatchStepLotConsumption;
use App\Models\MaterialLot;
use App\Models\QualityControlTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BatchReleaseService
{
    public function __construct(private LotService $lotService) {}

    /** Did this batch consume any material lot that is currently on hold? */
    private function hasHeldConsumedLot(Batch $batch): bool
    {
        $stepIds = $batch->steps()->pluck('id');

        if ($stepIds->isEmpty()) {
            return false;
        }

        $lotIds = BatchStepLotConsumption::whereIn('batch_step_id', $stepIds)
            ->pluck('material_lot_id')
            ->unique();

        return MaterialLot::whereIn('id', $lotIds)
            ->whereIn('status', [MaterialLot::STATUS_QUARANTINE, MaterialLot::STATUS_REJECTED])
            ->exists();
    }

    /**
     * Release a completed batch for production (semi-finished) or sale (finished goods).
     *
     * @throws \RuntimeException
     */
    public function release(Batch $batch, User $user, string $releaseType): Batch
    {
        if (! $batch->canRelease()) {
            if ($batch->isReleased()) {
                throw new \RuntimeException('Batch is already released.');
            }
            throw new \RuntimeException('Batch must be completed (DONE) before release.');
        }

        if (! in_array($releaseType, [Batch::RELEASE_FOR_PRODUCTION, Batch::RELEASE_FOR_SALE])) {
            throw new \RuntimeException("Invalid release type: {$releaseType}");
        }

        // Quality gate: don't release a batch whose work order is blocked by an
        // open non-conformance, or that consumed a held (quarantined/rejected) lot.
        if ($batch->workOrder?->isBlocked()) {
            throw new \RuntimeException('Cannot release: the work order is blocked by an open non-conformance.');
        }
        if ($this->hasHeldConsumedLot($batch)) {
            throw new \RuntimeException('Cannot release: a material lot consumed by this batch is on quality hold.');
        }
        if (QualityControlTask::hasOpenBlockingForBatch($batch->id)) {
            throw new \RuntimeException('Cannot release: a required quality control is still outstanding for this batch.');
        }

        return DB::transaction(function () use ($batch, $user, $releaseType) {
            // Assign LOT if not yet assigned (semi-finished products get LOT at release)
            if (! $batch->lot_number) {
                $productType = $batch->workOrder->productType;
                $this->lotService->assignLotOnRelease($batch, $productType);
                $batch->refresh();
            }

            $updateData = [
                'released_at' => now(),
                'released_by' => $user->id,
                'release_type' => $releaseType,
            ];

            // Auto-calculate expiry date for finished goods (3 years from production)
            if ($releaseType === Batch::RELEASE_FOR_SALE) {
                $productionDate = $batch->completed_at ?? $batch->started_at ?? now();
                $updateData['expiry_date'] = $productionDate->copy()->addYears(3)->toDateString();
            }

            $batch->update($updateData);

            // Future: dispatch BatchReleased event for webhook/Subiekt integration

            return $batch->fresh(['workOrder.productType', 'workstation', 'releasedBy']);
        });
    }

    /**
     * Check if a workstation has any active (non-completed) batches.
     * Returns the conflicting batches if any.
     */
    public function checkWorkstationConflicts(int $workstationId, ?int $excludeBatchId = null): array
    {
        $query = Batch::active()
            ->forWorkstation($workstationId)
            ->with('workOrder');

        if ($excludeBatchId) {
            $query->where('id', '!=', $excludeBatchId);
        }

        return $query->get()->map(function (Batch $batch) {
            return [
                'batch_id' => $batch->id,
                'work_order' => $batch->workOrder->order_no ?? null,
                'status' => $batch->status,
            ];
        })->toArray();
    }
}
