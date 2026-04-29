<?php

namespace App\Services\Production;

use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\ProcessConfirmation;
use App\Models\User;

class ProcessConfirmationService
{
    /**
     * Confirm process parameters for a batch or step.
     */
    public function confirm(Batch $batch, User $user, string $type = ProcessConfirmation::TYPE_PARAMETERS, ?BatchStep $step = null, ?string $notes = null, ?string $value = null): ProcessConfirmation
    {
        return ProcessConfirmation::create([
            'batch_id' => $batch->id,
            'batch_step_id' => $step?->id,
            'confirmed_by' => $user->id,
            'confirmed_at' => now(),
            'confirmation_type' => $type,
            'notes' => $notes ?? 'Process parameters confirmed as per formula.',
            'value' => $value,
        ]);
    }

    /**
     * Confirm drying time for a batch step (mouthpiece production).
     */
    public function confirmDrying(Batch $batch, User $user, int $hoursOfDrying, ?BatchStep $step = null): ProcessConfirmation
    {
        if ($hoursOfDrying < 12) {
            throw new \RuntimeException("Drying time must be at least 12 hours. Provided: {$hoursOfDrying}h.");
        }

        return $this->confirm(
            $batch,
            $user,
            ProcessConfirmation::TYPE_DRYING,
            $step,
            "Drying confirmed: {$hoursOfDrying} hours.",
            (string) $hoursOfDrying,
        );
    }

    /**
     * Check if parameters have been confirmed today for a batch.
     */
    public function isConfirmedToday(Batch $batch, string $type = ProcessConfirmation::TYPE_PARAMETERS): bool
    {
        return $batch->processConfirmations()
            ->where('confirmation_type', $type)
            ->whereDate('confirmed_at', today())
            ->exists();
    }

    /**
     * Get confirmations count for a batch.
     */
    public function getConfirmationsCount(Batch $batch, string $type = ProcessConfirmation::TYPE_PARAMETERS): int
    {
        return $batch->processConfirmations()
            ->where('confirmation_type', $type)
            ->count();
    }
}
