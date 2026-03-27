<?php

namespace App\Events\Traceability;

use App\Models\MaterialLineage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LineageRecorded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public MaterialLineage $lineage
    ) {}
}
