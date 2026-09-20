<?php

namespace App\Services\Material;

use App\Models\Material;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManualMaterialReceiptService
{
    public function receive(Material $material, float $quantity, string $reference, User $user): StockMovement
    {
        return DB::transaction(function () use ($material, $quantity, $reference, $user) {
            $material = Material::whereKey($material->id)->lockForUpdate()->firstOrFail();
            // Serialize receipts for this material before checking the document
            // reference: retrying a submitted form must not receive it twice.
            $existing = StockMovement::where('material_id', $material->id)
                ->where('source_type', 'manual_receipt')->where('reason', $reference)->first();
            if ($existing) {
                if (abs((float) $existing->quantity - $quantity) > 0.00001) {
                    throw ValidationException::withMessages([
                        'reference' => __('This receipt reference has already been used with a different quantity.'),
                    ]);
                }

                return $existing;
            }

            return app(StockMovementService::class)->record(
                $material, StockMovement::TYPE_RECEIPT, $quantity,
                user: $user, sourceType: 'manual_receipt', reason: $reference,
            );
        });
    }
}
