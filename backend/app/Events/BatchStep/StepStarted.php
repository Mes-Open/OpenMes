<?php

namespace App\Events\BatchStep;

use App\Models\BatchStep;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StepStarted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public BatchStep $batchStep
    ) {}
}
