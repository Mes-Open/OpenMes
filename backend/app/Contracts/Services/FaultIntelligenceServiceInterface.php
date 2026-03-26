<?php

namespace App\Contracts\Services;

interface FaultIntelligenceServiceInterface
{
    public function calculateMtbf(\App\Models\Workstation $workstation, $startDate, $endDate): float;
    public function calculateMttr(\App\Models\Workstation $workstation, $startDate, $endDate): float;
    public function getFaultClassification(\App\Models\Workstation $workstation): array;
}
