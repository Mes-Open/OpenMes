<?php

namespace App\Contracts\Services;

use App\Models\WorkOrder;
use App\Models\Batch;

interface BatchServiceInterface
{
    public function startStep(\App\Models\BatchStep $step, \App\Models\User $user): \App\Models\BatchStep;
    public function completeStep(\App\Models\BatchStep $step, \App\Models\User $user, array $data = []): \App\Models\BatchStep;
}
