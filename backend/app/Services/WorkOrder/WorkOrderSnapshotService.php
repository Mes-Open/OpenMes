<?php

namespace App\Services\WorkOrder;

use App\Enums\ChangeEffectivePoint;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderChangeRequest;
use App\Models\WorkOrderSnapshot;
use Illuminate\Support\Facades\DB;

/**
 * Versioning of work-order configurations (#182).
 *
 * `work_orders.process_snapshot` stays the ACTIVE configuration — batches read it
 * when their steps are generated. This service keeps the history around it: version 1
 * is what the order was released with, and every applied change appends the next
 * version together with the point from which it takes effect.
 *
 * Snapshots are append-only. Nothing here ever edits or deletes a row, because a
 * snapshot is the record of what the shop floor was told to build.
 */
class WorkOrderSnapshotService
{
    /**
     * Make sure the order has a version 1 recording the configuration it was
     * released with. Idempotent, and safe to call on orders created before change
     * control existed — that is exactly what it is for.
     */
    public function ensureBaseline(WorkOrder $workOrder, ?User $user = null): WorkOrderSnapshot
    {
        $existing = $workOrder->snapshots()->where('version', 1)->first();

        if ($existing) {
            return $existing;
        }

        return WorkOrderSnapshot::create([
            'work_order_id' => $workOrder->id,
            'version' => 1,
            'snapshot' => $workOrder->process_snapshot ?? [],
            // Version 1 has always applied: there is no earlier configuration for it
            // to start after.
            'effective_from' => ChangeEffectivePoint::Immediate,
            'created_by_id' => $user?->id,
            'tenant_id' => $workOrder->tenant_id,
        ]);
    }

    /**
     * Append the next configuration version and make it the order's active one.
     *
     * Must be called inside the caller's transaction: the version number is derived
     * from the current maximum, so the work-order row lock taken here is what stops
     * two concurrent applies from both writing version 3.
     *
     * @param  array<string, mixed>  $snapshot  The new frozen configuration.
     */
    public function createVersion(
        WorkOrder $workOrder,
        array $snapshot,
        ChangeEffectivePoint $effectiveFrom,
        ?WorkOrderChangeRequest $changeRequest = null,
        ?User $user = null,
        ?int $effectiveFromBatchId = null,
    ): WorkOrderSnapshot {
        return DB::transaction(function () use (
            $workOrder, $snapshot, $effectiveFrom, $changeRequest, $user, $effectiveFromBatchId
        ) {
            WorkOrder::whereKey($workOrder->getKey())->lockForUpdate()->first();
            $workOrder->refresh();

            $this->ensureBaseline($workOrder, $user);

            $version = (int) $workOrder->snapshots()->max('version') + 1;

            $created = WorkOrderSnapshot::create([
                'work_order_id' => $workOrder->id,
                'version' => $version,
                'snapshot' => $snapshot,
                'effective_from' => $effectiveFrom,
                // Only meaningful for REMAINING_QUANTITY, and it is the whole point of
                // it: "units 1–35 ran under v1, 36–100 under v2" stays answerable.
                'effective_from_qty' => $effectiveFrom === ChangeEffectivePoint::RemainingQuantity
                    ? (float) $workOrder->produced_qty
                    : null,
                'effective_from_batch_id' => $effectiveFromBatchId,
                'change_request_id' => $changeRequest?->id,
                'created_by_id' => $user?->id,
                'tenant_id' => $workOrder->tenant_id,
            ]);

            // The active configuration moves with the new version; the previous one
            // stays readable as its own snapshot row.
            $workOrder->update([
                'process_snapshot' => $snapshot,
                'snapshot_version' => $version,
            ]);

            return $created;
        });
    }

    /**
     * The configuration a given produced unit was made under — the query behind
     * "unit 12 was built to revision B, unit 80 to revision C".
     *
     * Only REMAINING_QUANTITY versions carry a unit boundary; NEXT_BATCH versions are
     * attributed through `batches.snapshot_version` instead, so they are skipped here.
     */
    public function versionForUnit(WorkOrder $workOrder, float $unitNumber): ?WorkOrderSnapshot
    {
        return $workOrder->snapshots()
            ->where('effective_from', ChangeEffectivePoint::RemainingQuantity->value)
            ->where('effective_from_qty', '<', $unitNumber)
            ->reorder('version', 'desc')
            ->first()
            ?? $workOrder->snapshots()->reorder('version', 'asc')->first();
    }

    /**
     * Material requirements for the quantity still to be produced, from a given
     * configuration. This is the "remaining material requirements recalculated" part
     * of an applied change — reported, never written over existing allocations, so
     * material already consumed keeps its own history.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<int, array<string, mixed>>
     */
    public function remainingRequirements(WorkOrder $workOrder, array $snapshot): array
    {
        $remaining = max(0.0, (float) $workOrder->planned_qty - (float) $workOrder->produced_qty);

        return collect($snapshot['bom'] ?? [])
            ->filter(fn (array $row) => isset($row['material_id']))
            ->map(function (array $row) use ($remaining) {
                $perUnit = (float) ($row['quantity_per_unit'] ?? 0);
                $scrap = (float) ($row['scrap_percentage'] ?? 0);

                return [
                    'material_id' => (int) $row['material_id'],
                    'material_code' => $row['material_code'] ?? null,
                    'quantity_per_unit' => $perUnit,
                    'remaining_qty' => round($remaining * $perUnit * (1 + $scrap / 100), 4),
                    'consumed_at' => $row['consumed_at'] ?? 'start',
                    'step_number' => $row['step_number'] ?? null,
                ];
            })
            ->values()
            ->all();
    }
}
