<?php

namespace App\Services\Traceability;

use App\Models\BatchStep;
use App\Models\SerialUnit;
use App\Models\UnitStepHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Per-unit (serial) genealogy. Registers serialised units and records a
 * "birth certificate" entry each time a unit is processed at a workstation,
 * with a parameter (sensor/measurement) snapshot.
 */
class SerialTraceService
{
    /**
     * Register a new serialised unit (or return the existing one for the serial).
     */
    public function registerUnit(string $serialNo, array $attributes = []): SerialUnit
    {
        return SerialUnit::firstOrCreate(
            ['serial_no' => $serialNo, 'tenant_id' => $attributes['tenant_id'] ?? null],
            [
                'work_order_id' => $attributes['work_order_id'] ?? null,
                'batch_id' => $attributes['batch_id'] ?? null,
                'material_id' => $attributes['material_id'] ?? null,
                'status' => $attributes['status'] ?? SerialUnit::STATUS_IN_PRODUCTION,
                'produced_at' => $attributes['produced_at'] ?? null,
                'extra_data' => $attributes['extra_data'] ?? null,
            ]
        );
    }

    /**
     * Record a processing event for a unit at a workstation. The high-precision
     * timestamp guarantees ordering even for sub-second consecutive steps.
     */
    public function recordStep(SerialUnit $unit, User $operator, ?BatchStep $step, array $data = []): UnitStepHistory
    {
        return DB::transaction(function () use ($unit, $operator, $step, $data) {
            $entry = $unit->history()->create([
                'batch_step_id' => $step?->id,
                'workstation_id' => $data['workstation_id'] ?? $step?->workstation_id ?? $operator->workstation_id,
                'operator_id' => $operator->id,
                'parameters' => $data['parameters'] ?? null,
                'result' => $data['result'] ?? null,
                'notes' => $data['notes'] ?? null,
                'processed_at' => now()->format('Y-m-d H:i:s.u'),
            ]);

            // Mark a failed unit as scrapped so it drops out of in-production counts.
            if (($data['result'] ?? null) === 'fail') {
                $unit->update(['status' => SerialUnit::STATUS_SCRAPPED]);
            }

            return $entry;
        });
    }

    /**
     * Full chronological process history for a unit (the birth certificate).
     */
    public function getHistory(SerialUnit $unit): SerialUnit
    {
        return $unit->load([
            'workOrder:id,order_no,product_type_id',
            'workOrder.productType:id,name,code',
            'batch:id,batch_number,lot_number',
            'material:id,name,code',
            'history.workstation:id,name,code',
            'history.operator:id,name',
            'history.batchStep:id,name,step_number',
        ]);
    }
}
