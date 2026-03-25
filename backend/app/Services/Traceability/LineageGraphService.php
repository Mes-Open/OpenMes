<?php

namespace App\Services\Traceability;

use App\Models\MaterialLineage;
use App\Models\Workstation;
use App\Models\User;
use App\Models\Batch;
use App\Models\BatchStep;

class LineageGraphService
{
    /**
     * Record the birth certificate for a single unit at a workstation.
     */
    public function recordLineage(Workstation $workstation, User $operator, Batch $batch, BatchStep $step, array $data): MaterialLineage
    {
        return MaterialLineage::create([
            'material_lot_no' => $data['material_lot_no'],
            'final_unit_no' => $data['final_unit_no'] ?? null,
            'workstation_id' => $workstation->id,
            'user_id' => $operator->id,
            'batch_id' => $batch->id,
            'batch_step_id' => $step->id,
            'process_id' => $data['process_id'],
            'parameters' => $data['parameters'], // Sensor snapshots at processing time
            'processed_at' => now()->format('Y-m-d H:i:s.u'),
        ]);
    }

    /**
     * Get the full lineage graph for a final product serial number.
     */
    public function getGraphBySerial(string $serial): array
    {
        return MaterialLineage::with(['workstation', 'operator', 'batch', 'step'])
            ->where('final_unit_no', $serial)
            ->orderBy('processed_at', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * Perform forward tracing (from material lot to final products).
     */
    public function forwardTrace(string $lotNo): array
    {
        return MaterialLineage::where('material_lot_no', $lotNo)
            ->pluck('final_unit_no')
            ->unique()
            ->toArray();
    }
}
