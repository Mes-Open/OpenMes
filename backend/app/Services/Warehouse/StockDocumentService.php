<?php

namespace App\Services\Warehouse;

use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\StockDocument;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Material\StockMovementService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns the lifecycle of a stock document (#212): create as draft, post, cancel.
 *
 * Posting is the only thing that moves stock. It is deliberately concentrated
 * here so every path — manual UI entry, ERP import, automatic generation from a
 * work order — produces the same three effects:
 *
 *   1. the per-warehouse balance in warehouse_stocks moves,
 *   2. for material lines, materials.stock_quantity and the stock_movements
 *      ledger move through StockMovementService (the single entry point for
 *      material stock), and the material lot's available quantity follows,
 *   3. the document becomes immutable (posted).
 *
 * Cancelling a posted document reverses all of it, so a mis-posted release can
 * be undone without editing balances by hand.
 */
class StockDocumentService
{
    /** How many times a generated document number is retried on a collision. */
    private const NUMBER_ATTEMPTS = 5;

    public function __construct(
        private StockMovementService $stockMovements,
        private WarehouseStockService $warehouseStock,
    ) {}

    /**
     * Create a draft document with its lines.
     *
     * @param  array<string, mixed>  $attributes  document columns; `lines` holds the line rows
     */
    public function createDraft(array $attributes, ?User $user = null): StockDocument
    {
        $lines = $attributes['lines'] ?? [];
        unset($attributes['lines']);

        if ($lines === []) {
            throw ValidationException::withMessages([
                'lines' => __('A stock document needs at least one line.'),
            ]);
        }

        $type = $attributes['type'];
        $warehouse = $this->resolveWarehouse($attributes['warehouse_id'] ?? null, $type);

        // A generated number can collide with a concurrent create; the partial
        // unique index catches it and the next attempt reads the new high-water
        // mark. An explicitly supplied number is the caller's to own, so a clash
        // there is a real error and is not retried.
        $attempts = isset($attributes['document_no']) ? 1 : self::NUMBER_ATTEMPTS;

        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($attributes, $lines, $type, $warehouse, $user) {
                    $document = StockDocument::create([
                        ...$attributes,
                        'warehouse_id' => $warehouse->id,
                        'document_no' => $attributes['document_no'] ?? $this->nextDocumentNumber($type),
                        'status' => StockDocument::STATUS_DRAFT,
                        'created_by_id' => $attributes['created_by_id'] ?? $user?->id,
                    ]);

                    foreach (array_values($lines) as $index => $line) {
                        $document->lines()->create([
                            ...$this->normaliseLine($line, $document),
                            'sort_order' => $line['sort_order'] ?? $index,
                        ]);
                    }

                    return $document->load('lines');
                });
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= $attempts) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Apply a draft document to stock. Idempotent by status: a document that is
     * already posted is returned untouched rather than double-counted.
     */
    public function post(StockDocument $document, ?User $user = null): StockDocument
    {
        return DB::transaction(function () use ($document, $user) {
            // Re-read under a row lock and only then judge the status: checking it
            // outside the transaction lets two concurrent posts both pass the guard
            // and apply the same lines twice.
            $locked = StockDocument::where('id', $document->getKey())->lockForUpdate()->first();

            if (! $locked) {
                throw ValidationException::withMessages([
                    'status' => __('That item was already removed.'),
                ]);
            }

            if ($locked->isPosted()) {
                return $locked;
            }

            if (! $locked->isDraft()) {
                throw ValidationException::withMessages([
                    'status' => __('Only a draft document can be posted.'),
                ]);
            }

            $document = $locked;
            $document->load('lines');

            if ($document->lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => __('A stock document needs at least one line.'),
                ]);
            }

            foreach ($document->lines as $line) {
                $this->applyLine($document, $line, reverse: false, user: $user);
            }

            $document->update([
                'status' => StockDocument::STATUS_POSTED,
                'posted_at' => now(),
                'posted_by_id' => $user?->id,
            ]);

            return $document->refresh();
        });
    }

    /**
     * Reverse a posted document. A draft is cancelled without touching stock
     * (nothing was applied yet).
     */
    public function cancel(StockDocument $document, ?User $user = null, ?string $reason = null): StockDocument
    {
        return DB::transaction(function () use ($document, $user, $reason) {
            // Same reason as post(): the status decides whether stock is reversed,
            // so it must be read under the lock that guards the reversal.
            $locked = StockDocument::where('id', $document->getKey())->lockForUpdate()->first();

            if (! $locked) {
                throw ValidationException::withMessages([
                    'status' => __('That item was already removed.'),
                ]);
            }

            if ($locked->status === StockDocument::STATUS_CANCELLED) {
                return $locked;
            }

            $document = $locked;

            if ($document->isPosted()) {
                $document->load('lines');

                foreach ($document->lines as $line) {
                    $this->applyLine($document, $line, reverse: true, user: $user);
                }
            }

            $document->update([
                'status' => StockDocument::STATUS_CANCELLED,
                'notes' => $reason
                    ? trim(($document->notes ? $document->notes."\n" : '').__('Cancelled: ').$reason)
                    : $document->notes,
            ]);

            return $document->refresh();
        });
    }

    /**
     * Record that the ERP has booked its own counterpart of this document, so
     * the export endpoint stops offering it.
     */
    public function acknowledge(StockDocument $document, ?string $erpReference = null): StockDocument
    {
        $document->update([
            'erp_reference' => $erpReference ?? $document->erp_reference,
            'erp_synced_at' => now(),
        ]);

        return $document->refresh();
    }

    /**
     * Next free document number for a type: TYPE-PREFIX/YYYY/NNNN, sequential
     * per year. Two concurrent creates can compute the same number; the partial
     * unique index on (document_no, tenant) rejects the loser and createDraft()
     * retries it (see NUMBER_ATTEMPTS).
     */
    public function nextDocumentNumber(string $type): string
    {
        $prefix = match ($type) {
            StockDocument::TYPE_MATERIAL_ISSUE => 'MI',
            StockDocument::TYPE_MATERIAL_RECEIPT => 'MR',
            StockDocument::TYPE_PRODUCT_RECEIPT => 'PR',
            StockDocument::TYPE_PRODUCT_ISSUE => 'PI',
            default => 'SD',
        };

        $year = now()->year;
        $pattern = "{$prefix}/{$year}/%";

        $last = StockDocument::withTrashed()
            ->where('type', $type)
            ->where('document_no', 'like', $pattern)
            ->orderByDesc('id')
            ->value('document_no');

        $sequence = $last ? ((int) substr($last, strrpos($last, '/') + 1)) + 1 : 1;

        return sprintf('%s/%d/%04d', $prefix, $year, $sequence);
    }

    /**
     * Move one line's quantity in every place stock is tracked.
     *
     * `reverse` flips the sign, which is all a cancellation needs.
     */
    private function applyLine(StockDocument $document, $line, bool $reverse, ?User $user): void
    {
        $signed = $document->direction() * (float) $line->quantity * ($reverse ? -1 : 1);

        $this->adjustWarehouseStock($document, $line, $signed);

        if (! $document->isMaterialDocument() || $line->material_id === null) {
            return;
        }

        $material = Material::find($line->material_id);

        if (! $material) {
            return;
        }

        $this->guardNegativeStock($material, $signed);

        // materials.stock_quantity + the stock_movements ledger. The movement
        // type mirrors the direction so the ledger reads the same as a shop-floor
        // consumption or an inbound receipt.
        $this->stockMovements->record(
            material: $material,
            movementType: $signed < 0 ? StockMovement::TYPE_CONSUME : StockMovement::TYPE_RECEIPT,
            signedQuantity: $signed,
            user: $user,
            sourceType: StockMovement::SOURCE_STOCK_DOCUMENT,
            sourceId: $document->id,
            reason: $document->document_no,
            warehouseId: $document->warehouse_id,
        );

        if ($line->material_lot_id !== null) {
            $this->adjustLot($line->material_lot_id, $signed, (int) $line->material_id);
        }
    }

    /**
     * Move the (warehouse, item[, lot]) balance, creating the row on first use.
     * Lot-tracked material lines keep both a lot row and the warehouse total for
     * that material, so a per-material view does not have to sum lots.
     */
    private function adjustWarehouseStock(StockDocument $document, $line, float $signed): void
    {
        $isMaterial = $document->isMaterialDocument();

        $keys = [
            'warehouse_id' => $document->warehouse_id,
            'material_id' => $isMaterial ? $line->material_id : null,
            'product_type_id' => $isMaterial ? null : $line->product_type_id,
        ];

        try {
            if ($isMaterial && $line->material_lot_id !== null) {
                $this->warehouseStock->adjust(
                    [...$keys, 'material_lot_id' => $line->material_lot_id],
                    $signed,
                    $line->unit_of_measure,
                );
            }

            $this->warehouseStock->adjust([...$keys, 'material_lot_id' => null], $signed, $line->unit_of_measure);
        } catch (\RuntimeException) {
            // The shared service is used by non-HTTP callers too, so it reports a
            // plain runtime failure; posting turns it back into a form error.
            throw ValidationException::withMessages([
                'lines' => __('Could not read the stock balance to update. Try again.'),
            ]);
        }
    }

    /** Keep the lot's remaining quantity in step with what was issued/returned. */
    private function adjustLot(int $lotId, float $signed, int $materialId): void
    {
        $lot = MaterialLot::where('id', $lotId)->lockForUpdate()->first();

        // A lot belonging to another material is not this line's stock to move —
        // the form request rejects it, and a payload assembled elsewhere must not
        // slip past that.
        if (! $lot || (int) $lot->material_id !== $materialId) {
            return;
        }

        $lot->quantity_available = max(0, round((float) $lot->quantity_available + $signed, 4));

        // A lot drained by an issue is consumed; returning stock to an emptied
        // lot makes it usable again.
        if ($lot->quantity_available <= 0 && $lot->status === MaterialLot::STATUS_RELEASED) {
            $lot->status = MaterialLot::STATUS_CONSUMED;
        } elseif ($lot->quantity_available > 0 && $lot->status === MaterialLot::STATUS_CONSUMED) {
            $lot->status = MaterialLot::STATUS_RELEASED;
        }

        $lot->save();
    }

    /**
     * Honour the system-wide "block negative stock" setting, the same switch the
     * material allocation path respects.
     */
    private function guardNegativeStock(Material $material, float $signed): void
    {
        if ($signed >= 0 || ! $this->warehouseStock->blocksNegativeStock()) {
            return;
        }

        if ((float) $material->stock_quantity + $signed < 0) {
            throw ValidationException::withMessages([
                'lines' => __('Posting would drive :material below zero stock (:available available).', [
                    'material' => $material->code,
                    'available' => (float) $material->stock_quantity,
                ]),
            ]);
        }
    }

    /**
     * Coerce a submitted line into the columns its document type uses, so a
     * product line can never smuggle in a material id (and vice versa).
     *
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function normaliseLine(array $line, StockDocument $document): array
    {
        $isMaterial = $document->isMaterialDocument();

        return [
            'material_id' => $isMaterial ? ($line['material_id'] ?? null) : null,
            'product_type_id' => $isMaterial ? null : ($line['product_type_id'] ?? null),
            'material_lot_id' => $isMaterial ? ($line['material_lot_id'] ?? null) : null,
            'lot_number' => $line['lot_number'] ?? null,
            'quantity' => abs((float) ($line['quantity'] ?? 0)),
            'unit_of_measure' => $line['unit_of_measure'] ?? null,
            'notes' => $line['notes'] ?? null,
        ];
    }

    /**
     * Resolve the warehouse to post against, falling back to the default for the
     * document's kind. Fails loudly rather than silently posting to the wrong
     * kind of warehouse.
     */
    private function resolveWarehouse(?int $warehouseId, string $type): Warehouse
    {
        $kind = StockDocument::warehouseKindFor($type);

        $warehouse = $warehouseId
            ? Warehouse::find($warehouseId)
            : Warehouse::resolveDefault($kind);

        if (! $warehouse) {
            throw ValidationException::withMessages([
                'warehouse_id' => __('No warehouse is configured for this document type.'),
            ]);
        }

        $accepts = $kind === Warehouse::KIND_FINISHED_GOODS
            ? $warehouse->acceptsProducts()
            : $warehouse->acceptsMaterials();

        if (! $accepts) {
            throw ValidationException::withMessages([
                'warehouse_id' => __('Warehouse :code cannot hold this kind of item.', ['code' => $warehouse->code]),
            ]);
        }

        return $warehouse;
    }
}
