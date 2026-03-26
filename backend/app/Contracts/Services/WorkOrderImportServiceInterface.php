<?php

namespace App\Contracts\Services;

interface WorkOrderImportServiceInterface
{
    public function import(array $mappedData, string $strategy): array;
}
