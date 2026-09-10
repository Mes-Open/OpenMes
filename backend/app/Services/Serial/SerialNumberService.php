<?php

namespace App\Services\Serial;

use App\Models\ProductType;
use App\Models\SerialSequence;

/**
 * Generate serial numbers from a configured SerialSequence — parallel to
 * LotService, kept separate rather than folded in: a lot number and a serial
 * number are different numbering domains that may reset/pattern independently
 * per product type (#290).
 */
class SerialNumberService
{
    /**
     * Generate a new serial number for the given product type.
     * Uses the product-type-specific sequence, or falls back to the default
     * (null product_type_id) sequence.
     */
    public function generateSerial(?ProductType $productType = null): string
    {
        $sequence = $this->findSequence($productType);

        if (! $sequence) {
            throw new \RuntimeException(
                'No serial sequence configured'.($productType ? " for product type: {$productType->name}" : '')
            );
        }

        return $sequence->generateNext();
    }

    /**
     * Preview the next serial number without incrementing.
     */
    public function previewNext(?ProductType $productType = null): ?string
    {
        $sequence = $this->findSequence($productType);

        return $sequence?->previewNext();
    }

    private function findSequence(?ProductType $productType): ?SerialSequence
    {
        if ($productType) {
            $seq = SerialSequence::where('product_type_id', $productType->id)->first();
            if ($seq) {
                return $seq;
            }
        }

        return SerialSequence::whereNull('product_type_id')->first();
    }
}
