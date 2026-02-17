<?php

namespace App\Events\Batch;

use App\Models\Batch;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BatchCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Batch $batch
    ) {}
}
