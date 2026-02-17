<?php

namespace App\Services\ProcessTemplate;

use App\Models\ProcessTemplate;

class SnapshotService
{
    /**
     * Create a JSONB snapshot of a process template.
     *
     * This snapshot is immutable and stored with the work order,
     * so changes to the template don't affect existing work orders.
     *
     * @param ProcessTemplate $template
     * @return array
     */
    public function createSnapshot(ProcessTemplate $template): array
    {
        $template->load(['steps' => function ($query) {
            $query->orderBy('step_number');
        }]);

        return [
            'template_id' => $template->id,
            'template_name' => $template->name,
            'template_version' => $template->version,
            'product_type_id' => $template->product_type_id,
            'steps' => $template->steps->map(function ($step) {
                return [
                    'step_number' => $step->step_number,
                    'name' => $step->name,
                    'instruction' => $step->instruction,
                    'estimated_duration_minutes' => $step->estimated_duration_minutes,
                    'workstation_id' => $step->workstation_id,
                ];
            })->toArray(),
            'snapshot_created_at' => now()->toIso8601String(),
        ];
    }
}
