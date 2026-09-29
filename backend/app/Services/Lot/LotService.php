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
        return $this->generate($productType, LotSequence::PURPOSE_LOT);
    }

    /**
     * The next number from the sequence serving this purpose: the product's
     * own, else the global one for that purpose.
     *
     * @throws \RuntimeException when no sequence serves the purpose
     */
    public function generate(?ProductType $productType, string $purpose): string
    {
        $sequence = $this->findSequence($productType, $purpose);

        if (! $sequence) {
            throw new \RuntimeException(
                'No '.self::purposeLabel($purpose).' sequence configured'.($productType ? " for product type: {$productType->name}" : '')
            );
        }

        return $sequence->generateNext();
    }

    /**
     * Preview the next number without incrementing.
     */
    public function previewNext(?ProductType $productType = null, string $purpose = LotSequence::PURPOSE_LOT): ?string
    {
        return $this->findSequence($productType, $purpose)?->previewNext();
    }

    public static function purposeLabel(string $purpose): string
    {
        return match ($purpose) {
            LotSequence::PURPOSE_PROCESS_SERIAL => 'process serial',
            LotSequence::PURPOSE_UNIT_SERIAL => 'unit serial',
            default => 'LOT',
        };
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
    private function findSequence(?ProductType $productType, string $purpose = LotSequence::PURPOSE_LOT): ?LotSequence
    {
        if ($productType) {
            $seq = LotSequence::where('product_type_id', $productType->id)->where('purpose', $purpose)->first();
            if ($seq) {
                return $seq;
            }
        }

        // Fall back to the global sequence for this purpose (null product_type_id)
        return LotSequence::whereNull('product_type_id')->where('purpose', $purpose)->first();
    }
}
