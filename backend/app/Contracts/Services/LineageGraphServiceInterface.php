<?php

namespace App\Contracts\Services;

use App\Models\Workstation;
use App\Models\User;
use App\Models\Batch;
use App\Models\BatchStep;
use App\Models\MaterialLineage;

interface LineageGraphServiceInterface
{
    public function recordLineage(Workstation $workstation, User $operator, Batch $batch, BatchStep $step, array $data): MaterialLineage;
    public function getGraphBySerial(string $serial): array;
    public function forwardTrace(string $lotNo): array;
}
