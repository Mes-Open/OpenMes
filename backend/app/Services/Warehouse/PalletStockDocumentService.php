<?php

namespace App\Services\Warehouse;

use App\Enums\PalletStatus;
use App\Models\Pallet;
use App\Models\SerialUnit;
use App\Models\StockDocument;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\ModuleRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Finished goods booked pallet by pallet (system setting
 * `pallet_stock_documents`): a closed pallet is received into the
 * finished-goods warehouse, a shipped one leaves it - the RAF transaction and
 * the inventory reduction of a make-to-ship line.
 *
 *   off   - nothing here; the work order's completion receipt stands (#212)
 *   draft - the documents are created as drafts for the warehouse to post
 *   post  - they are posted at once, so stock follows the pallets
 *
 * One document of each kind per pallet, whatever screen changes its status.
 * A failure is logged and never undoes the pallet's own transition.
 */
class PalletStockDocumentService
{
    public const SETTING_KEY = 'pallet_stock_documents';

    public const OFF = 'off';

    public const DRAFT = 'draft';

    public const POST = 'post';

    public const MODES = [self::OFF, self::DRAFT, self::POST];

    public function __construct(private StockDocumentService $documents) {}

    public static function mode(): string
    {
        try {
            $raw = DB::table('system_settings')->where('key', self::SETTING_KEY)->value('value');
        } catch (\Throwable) {
            return self::OFF;
        }
        $mode = $raw === null ? null : json_decode((string) $raw, true);

        return in_array($mode, self::MODES, true) ? $mode : self::OFF;
    }

    /** On, and there is a warehouse module to book into. */
    public function enabled(): bool
    {
        return self::mode() !== self::OFF
            && config('openmmes.warehouse.auto_documents', true)
            && ModuleRegistry::isModuleEnabled('warehouse');
    }

    /** The pallet's status just changed: book what that transition means. */
    public function statusChanged(Pallet $pallet, ?User $user = null): void
    {
        if (! $this->enabled()) {
            return;
        }
        try {
            if (in_array($pallet->status, [PalletStatus::Closed, PalletStatus::Shipped], true)) {
                // Shipped straight from open still arrives in the warehouse first.
                $this->book($pallet, StockDocument::TYPE_PRODUCT_RECEIPT, $user);
            }
            if ($pallet->status === PalletStatus::Shipped) {
                $this->book($pallet, StockDocument::TYPE_PRODUCT_ISSUE, $user);
            }
        } catch (\Throwable $e) {
            Log::warning('Could not book the pallet in the finished-goods warehouse', ['pallet_id' => $pallet->id, 'error' => $e->getMessage()]);
        }
    }

    private function book(Pallet $pallet, string $type, ?User $user): ?StockDocument
    {
        if (StockDocument::where('pallet_id', $pallet->id)->where('type', $type)->exists()) {
            return null;
        }
        $workOrder = $pallet->workOrder;
        $quantity = $this->quantity($pallet);
        if (! $workOrder?->product_type_id || $quantity <= 0 || ! Warehouse::resolveDefault(Warehouse::KIND_FINISHED_GOODS)) {
            return null;
        }

        $document = $this->documents->createDraft([
            'type' => $type,
            'work_order_id' => $workOrder->id,
            'batch_id' => $pallet->batch_id,
            'pallet_id' => $pallet->id,
            'notes' => $type === StockDocument::TYPE_PRODUCT_RECEIPT
                ? __('Pallet :pallet received from work order :order', ['pallet' => $pallet->pallet_no, 'order' => $workOrder->order_no])
                : __('Pallet :pallet shipped', ['pallet' => $pallet->pallet_no]),
            'lines' => [[
                'product_type_id' => $workOrder->product_type_id,
                'quantity' => $quantity,
                'unit_of_measure' => $workOrder->productType?->unit_of_measure,
            ]],
        ], $user);

        if (self::mode() === self::POST) {
            try {
                $document = $this->documents->post($document, $user);
            } catch (\Throwable $e) {
                // Left as a draft for the warehouse to sort out (e.g. stock it cannot issue).
                Log::warning('Pallet stock document left as a draft', ['document_id' => $document->id, 'error' => $e->getMessage()]);
            }
        }

        return $document;
    }

    /** Serialised goods count their good units (scrap and held ones excepted); anything else the pallet's pieces. */
    private function quantity(Pallet $pallet): float
    {
        $units = SerialUnit::where('pallet_id', $pallet->id)->whereNotIn('status', [SerialUnit::STATUS_SCRAPPED, SerialUnit::STATUS_BLOCKED])->count();

        return $units > 0 ? (float) $units : (float) $pallet->qty;
    }
}
