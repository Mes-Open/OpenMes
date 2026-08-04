<?php

namespace Modules\Pantheon\Sync;

use App\Models\StockDocument;
use App\Services\Warehouse\StockDocumentService;
use Illuminate\Support\Facades\Log;
use Modules\Pantheon\Services\PantheonSettings;
use Modules\Pantheon\Services\PawsClient;

/**
 * OpenMES → Pantheon: book our posted warehouse documents as Pantheon Move
 * documents.
 *
 * This is the second half of the customer's request — "documents for the release
 * of materials from the raw materials warehouse, and documents for the receipt of
 * finished products" — seen from the ERP's side. Production concludes a work
 * order, OpenMES posts the release and the receipt, and this pushes them into
 * Pantheon so the ERP's stock matches the floor.
 *
 * Only POSTED documents are pushed: a draft is not a real movement yet. Once
 * Pantheon returns its document key we call acknowledge(), which stamps
 * erp_reference / erp_synced_at and takes the document off the backlog — so a
 * re-run never books the same movement twice.
 */
class PushStockDocuments extends Sync
{
    public function __construct(
        PawsClient $paws,
        PantheonSettings $settings,
        private StockDocumentService $documents,
    ) {
        parent::__construct($paws, $settings);
    }

    public function name(): string
    {
        return 'stock-documents';
    }

    public function run(): array
    {
        $report = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        $pending = StockDocument::query()
            ->posted()
            ->notSynced()
            ->with(['lines.material', 'lines.productType', 'lines.materialLot', 'warehouse', 'workOrder'])
            ->orderBy('id')
            ->get();

        foreach ($pending as $document) {
            $docType = $this->settings->documentType($document->type);

            if ($docType === null) {
                // Without the customer's document-type code we would have to guess
                // which Pantheon document this is. Report it and move on rather
                // than booking a movement under the wrong type.
                $report['errors'][] = [
                    'document' => $document->document_no,
                    'message' => "No Pantheon document type configured for '{$document->type}'",
                ];
                $report['skipped']++;

                continue;
            }

            try {
                $acKey = $this->paws->insertMove($this->buildMove($document, $docType));
                $this->paws->changeDocumentStatus($acKey, (string) ($this->settings->documentType('posted_status') ?? 'P'));
                $this->documents->acknowledge($document, $acKey);
                $report['imported']++;
            } catch (\Throwable $e) {
                // One document failing must not stop the batch: the rest are
                // independent movements, and the backlog retries this one.
                $report['errors'][] = [
                    'document' => $document->document_no,
                    'message' => $e->getMessage(),
                ];

                Log::error('Pantheon: booking a stock document failed', [
                    'document_no' => $document->document_no,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $report;
    }

    /**
     * Build the PAWS Move payload for one of our documents.
     *
     * @return array<string, mixed>
     */
    private function buildMove(StockDocument $document, string $docType): array
    {
        $warehouse = $document->warehouse?->code ?? '';

        return [
            'docType' => $docType,
            'date' => $document->posted_at?->toDateString(),
            // Our number travels as the ERP-side note so a warehouse keeper can
            // find the OpenMES document from Pantheon and back.
            'notes' => trim($document->document_no.' '.($document->workOrder?->order_no ?? '')),
            'warehouse' => $this->settings->warehouseCode($warehouse)
                ?? $document->warehouse?->erp_code
                ?? $warehouse,
            'items' => $document->lines->map(fn ($line) => [
                'ident' => $line->material?->code ?? $line->productType?->code,
                'quantity' => (float) $line->quantity,
                'unit' => $line->unit_of_measure,
                'serialNo' => $line->effectiveLotNumber(),
            ])->values()->all(),
        ];
    }

    /** Not used: this sync pushes rows out rather than mapping rows in. */
    protected function map(array $row): array
    {
        return $row;
    }
}
