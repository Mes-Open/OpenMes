<?php

namespace App\Events\BatchStep;

use App\Models\BatchStep;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StepCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public BatchStep $batchStep
    ) {}
}
