<?php

namespace App\Services\Lot;

use App\Models\Batch;
use App\Models\LotSequence;
use App\Models\ProductType;

class LotService
{
    /**
     * Generate a new LOT number for the given product type.
     * Uses the product-type-specific sequence, or falls back to the default (null product_type_id).
     */
    public function generateLot(?ProductType $productType = null): string
    {
        $sequence = $this->findSequence($productType);

        if (! $sequence) {
            throw new \RuntimeException(
                'No LOT sequence configured'.($productType ? " for product type: {$productType->name}" : '')
            );
        }

        return $sequence->generateNext();
    }

    /**
     * Preview the next LOT number without incrementing.
     */
    public function previewNext(?ProductType $productType = null): ?string
    {
        $sequence = $this->findSequence($productType);

        return $sequence?->previewNext();
    }

    /**
     * Assign LOT at batch start (for finished goods that need LOT on packaging).
     */
    public function assignLotOnStart(Batch $batch, ?ProductType $productType = null): Batch
    {
        $lot = $this->generateLot($productType);

        $batch->update([
            'lot_number' => $lot,
            'lot_assigned_at' => Batch::LOT_ON_START,
        ]);

        return $batch;
    }

    /**
     * Assign LOT at release (for semi-finished products that get LOT after production).
     */
    public function assignLotOnRelease(Batch $batch, ?ProductType $productType = null): Batch
    {
        if ($batch->lot_number) {
            return $batch; // Already has LOT
        }

        $lot = $this->generateLot($productType);

        $batch->update([
            'lot_number' => $lot,
            'lot_assigned_at' => Batch::LOT_ON_RELEASE,
        ]);

        return $batch;
    }

    /**
     * Find the LOT sequence for a product type, falling back to default.
     */
    private function findSequence(?ProductType $productType): ?LotSequence
    {
        if ($productType) {
            $seq = LotSequence::where('product_type_id', $productType->id)->first();
            if ($seq) {
                return $seq;
            }
        }

        // Fall back to default sequence (null product_type_id)
        return LotSequence::whereNull('product_type_id')->first();
    }
}
