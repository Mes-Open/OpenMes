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
        $template->load(['steps', 'bomItems.material.materialType', 'bomItems.productType', 'bomItems.templateStep']);

        return array_merge($template->toSnapshot(), [
            'product_type_id' => $template->product_type_id,
            'snapshot_created_at' => now()->toIso8601String(),
        ]);
    }
}
