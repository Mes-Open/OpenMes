<?php

namespace App\Contracts\Services;

use App\Models\ProcessTemplate;

interface SnapshotServiceInterface
{
    public function createSnapshot(ProcessTemplate $template): array;
}
