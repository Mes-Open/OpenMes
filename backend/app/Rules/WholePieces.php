<?php

namespace App\Rules;

use App\Models\ProductType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A quantity of a product counted in pieces (pcs, szt, …) must be a whole
 * number — 1.5 cameras cannot pass a station. Products measured in kg, m, l
 * and the like keep their fractions. No product known = no opinion.
 */
class WholePieces implements ValidationRule
{
    public function __construct(private ?ProductType $productType) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->productType === null || ! is_numeric($value) || ! $this->productType->isCountedInPieces()) {
            return;
        }

        if (abs((float) $value - round((float) $value)) > 0.000001) {
            $fail(__('Enter whole pieces — :product is counted in :unit.', [
                'product' => $this->productType->name,
                'unit' => $this->productType->unit_of_measure ?: 'pcs',
            ]));
        }
    }
}
