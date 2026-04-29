<?php

namespace App\Services\Production;

use App\Models\Batch;
use App\Models\PackagingChecklist;
use App\Models\User;

class PackagingChecklistService
{
    /**
     * Submit packaging checklist for a batch.
     */
    public function submit(Batch $batch, User $user, array $data): PackagingChecklist
    {
        if ($batch->packagingChecklist) {
            throw new \RuntimeException('Packaging checklist already submitted for this batch.');
        }

        return PackagingChecklist::create([
            'batch_id' => $batch->id,
            'checked_by' => $user->id,
            'checked_at' => now(),
            'udi_readable' => $data['udi_readable'] ?? false,
            'packaging_condition' => $data['packaging_condition'] ?? false,
            'labels_readable' => $data['labels_readable'] ?? false,
            'label_matches_product' => $data['label_matches_product'] ?? false,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * Check if packaging checklist is complete and all passed.
     */
    public function isComplete(Batch $batch): bool
    {
        $checklist = $batch->packagingChecklist;

        return $checklist && $checklist->all_passed;
    }
}
