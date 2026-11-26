<?php

namespace App\Services\Quality;

use App\Models\Inspection;
use App\Models\MaterialLot;
use App\Models\MaterialLotDisposal;
use App\Models\StockDocument;
use App\Models\User;
use App\Services\Warehouse\StockDocumentService;
use Illuminate\Support\Facades\DB;

/**
 * Physically remove quantity from a quality-held lot, with a stock document.
 *
 * A disposition like scrap/return/reject changes more than the lot's status:
 * the material leaves the warehouse. This service is the single path for that:
 * it posts a material issue first, then records the lot-level disposal that
 * keeps the frozen balance correct. A partially disposed lot stays quarantined;
 * once nothing is left it is rejected and the linked inspection gets the final
 * disposition.
 */
class MaterialLotDisposalService
{
    public function __construct(
        private readonly StockDocumentService $stockDocuments,
        private readonly DispositionService $dispositions,
    ) {}

    public function remaining(MaterialLot $lot): float
    {
        return $lot->remainingPhysicalQuantity();
    }

    public function dispose(
        MaterialLot $lot,
        string $disposition,
        float $quantity,
        ?string $notes,
        User $by,
    ): MaterialLotDisposal {
        if (! in_array($lot->status, [MaterialLot::STATUS_QUARANTINE, MaterialLot::STATUS_REJECTED], true)) {
            throw new \DomainException(__('Only a quality-held lot can be disposed.'));
        }

        if (! in_array($disposition, MaterialLotDisposal::DISPOSITIONS, true)) {
            throw new \InvalidArgumentException("invalid lot disposal: {$disposition}");
        }

        $quantity = round($quantity, 4);
        $remaining = round($this->remaining($lot), 4);

        if ($quantity <= 0) {
            throw new \DomainException(__('Disposal quantity must be greater than zero.'));
        }
        if ($quantity > $remaining + 0.00005) {
            throw new \DomainException(__('Disposal quantity exceeds the remaining frozen quantity.'));
        }

        return DB::transaction(function () use ($lot, $disposition, $quantity, $notes, $by) {
            $label = $this->dispositionLabel($disposition);
            $document = $this->stockDocuments->createDraft([
                'type' => StockDocument::TYPE_MATERIAL_ISSUE,
                'warehouse_id' => $lot->warehouse_id,
                'notes' => "质量处置：{$label}；物料批 {$lot->lot_number}",
                'lines' => [[
                    'material_id' => $lot->material_id,
                    'quantity' => $quantity,
                    'unit_of_measure' => $lot->unit_of_measure,
                    'lot_number' => $lot->lot_number,
                    'notes' => $notes ?: "质量处置：{$label}",
                ]],
            ], $by);
            $this->stockDocuments->post($document, $by);

            $disposal = MaterialLotDisposal::create([
                'material_lot_id' => $lot->id,
                'inspection_id' => $lot->inspection_id,
                'disposition' => $disposition,
                'quantity' => $quantity,
                'stock_document_id' => $document->id,
                'notes' => $notes,
                'disposed_by_id' => $by->id,
                'disposed_at' => now(),
            ]);

            // Re-read the sums after the issue/row are written. A partial return
            // leaves the rest physically frozen; a full one closes the lot.
            $remainingAfter = round($this->remaining($lot->fresh()), 4);
            if ($remainingAfter <= 0.00005) {
                $lot->update([
                    'status' => MaterialLot::STATUS_REJECTED,
                    'quantity_available' => 0,
                ]);

                if ($lot->inspection) {
                    $this->dispositions->apply(
                        $lot->inspection,
                        $this->inspectionDisposition($disposition),
                        $notes,
                        $by,
                    );
                }
            }

            return $disposal;
        });
    }

    private function dispositionLabel(string $disposition): string
    {
        return match ($disposition) {
            MaterialLotDisposal::DISPOSITION_SCRAP => '报废',
            MaterialLotDisposal::DISPOSITION_RETURN => '退货',
            default => '拒收',
        };
    }

    private function inspectionDisposition(string $disposition): string
    {
        return match ($disposition) {
            MaterialLotDisposal::DISPOSITION_SCRAP => Inspection::DISPOSITION_SCRAP,
            MaterialLotDisposal::DISPOSITION_RETURN => Inspection::DISPOSITION_RETURN_TO_SUPPLIER,
            default => Inspection::DISPOSITION_REJECT,
        };
    }
}
