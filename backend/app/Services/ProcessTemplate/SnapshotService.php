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
     */
    public function createSnapshot(ProcessTemplate $template): array
    {
        $template->load([
            'steps' => function ($query) {
                $query->orderBy('step_number');
            },
            'bomItems.material.materialType',
            'bomItems.productType',
            'bomItems.templateStep',
        ]);

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
                    'requires_confirmation' => (bool) $step->requires_confirmation,
                    'estimated_duration_minutes' => $step->estimated_duration_minutes,
                    'setup_time_minutes' => $step->setup_time_minutes,
                    'run_time_per_unit_minutes' => $step->run_time_per_unit_minutes,
                    'required_operators' => $step->effectiveRequiredOperators(),
                    'workstation_id' => $step->workstation_id,
                    'workstation_type_id' => $step->effectiveWorkstationType(),
                    'parameters' => $step->effectiveParameters(),
                ];
            })->toArray(),
            'bom' => $template->bomItems->map(function ($item) {
                // Product-type lines are sub-assembly references — captured here with
                // their name so the order shows them, but with no material fields
                // (material_id null, so the stock/consumption engine skips them).
                if ($item->component_kind === 'product_type') {
                    return [
                        'component_kind' => 'product_type',
                        'material_id' => null,
                        'product_type_id' => $item->product_type_id,
                        'material_code' => $item->productType?->code,
                        'material_name' => $item->productType?->name,
                        'material_type' => 'product_type',
                        'tracking_type' => null,
                        'unit_of_measure' => $item->productType?->unit_of_measure,
                        'quantity_per_unit' => (float) $item->quantity_per_unit,
                        'scrap_percentage' => (float) $item->scrap_percentage,
                        'consumed_at' => $item->consumed_at,
                        'step_number' => $item->templateStep?->step_number,
                        'external_code' => null,
                        'external_system' => null,
                    ];
                }

                return [
                    'component_kind' => 'material',
                    'material_id' => $item->material_id,
                    'product_type_id' => null,
                    'material_code' => $item->material->code,
                    'material_name' => $item->material->name,
                    'material_type' => $item->material->materialType->code,
                    'tracking_type' => $item->material->tracking_type,
                    'unit_of_measure' => $item->material->unit_of_measure,
                    'quantity_per_unit' => (float) $item->quantity_per_unit,
                    'scrap_percentage' => (float) $item->scrap_percentage,
                    'consumed_at' => $item->consumed_at,
                    'step_number' => $item->templateStep?->step_number,
                    'external_code' => $item->material->external_code,
                    'external_system' => $item->material->external_system,
                ];
            })->toArray(),
            'snapshot_created_at' => now()->toIso8601String(),
        ];
    }
}
