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
        parent::__construct(__(
            'Insufficient stock for material ":name" (:code): required :required :unit, available :available :unit.',
            [
                'name'      => $material->name,
                'code'      => $material->code,
                'required'  => number_format($required, 4),
                'unit'      => $material->unit_of_measure,
                'available' => number_format($available, 4),
            ]
        ));
    }
}
