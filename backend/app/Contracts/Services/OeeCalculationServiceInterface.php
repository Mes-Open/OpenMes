<?php

namespace App\Contracts\Services;

interface OeeCalculationServiceInterface
{
    public function calculateOee(\App\Models\Workstation $workstation, $startDate, $endDate): array;
    public function invalidateOeeCache(\App\Models\Workstation $workstation): void;
}
