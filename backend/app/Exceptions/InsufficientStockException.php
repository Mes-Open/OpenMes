<?php

namespace App\Exceptions;

use App\Models\Material;
use Exception;

class InsufficientStockException extends Exception
{
    public function __construct(
        public readonly Material $material,
        public readonly float $required,
        public readonly float $available,
    ) {
        parent::__construct(sprintf(
            'Insufficient stock for material "%s" (%s): required %.4f %s, available %.4f %s.',
            $material->name,
            $material->code,
            $required,
            $material->unit_of_measure,
            $available,
            $material->unit_of_measure,
        ));
    }
}
