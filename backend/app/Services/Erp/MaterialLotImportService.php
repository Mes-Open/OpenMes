<?php

namespace App\Services\Erp;

use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\Erp\Concerns\ReportsImportRows;
use App\Services\Warehouse\MaterialStockReconciler;
use Illuminate\Support\Facades\DB;

/**
 * ERP → OpenMES material lot + available-quantity import (#212).
 *
 * An ERP that tracks lots (Pantheon's free-serial-number view is the motivating
 * case) is authoritative about what physically exists, so an imported quantity
 * REPLACES the lot's available quantity rather than adding to it — re-running the
 * sync converges instead of inflating stock.
 *
 * When rows name a warehouse, the per-warehouse lot balance is written too, the
 * material's warehouse total is recomputed from its lots, and the global
 * materials.stock_quantity is re-derived from those totals (MaterialStockReconciler)
 * — otherwise the two views of the same stock drift apart and a later issue drives
 * the global figure negative while the warehouse still shows stock. That makes the
 * ERP lot list authoritative for the (warehouse, material) pairs it touches; bulk
 * stock for materials that are not lot-tracked belongs to the stock import
 * endpoint instead (StockImportService).
 */
class MaterialLotImportService
{
    use ReportsImportRows;

    public function __construct(private MaterialStockReconciler $reconciler) {}

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{imported: int, updated: int, skipped: int, errors: array<int, array<string, mixed>>}
     */
    public function import(array $rows, string $strategy = 'update_or_create', ?string $defaultWarehouseCode = null): array
    {
        /** @var array<string, array{0: int, 1: int}> touched (warehouse, material) pairs to reconcile */
        $touched = [];

        $report = $this->processRows($rows, function (array $row) use ($strategy, $defaultWarehouseCode, &$touched) {
            $lotNumber = trim((string) ($row['lot_number'] ?? ''));

            if ($lotNumber === '') {
                return $this->error('lot_number', __('Lot number is required'));
            }

            $material = Material::where('code', trim((string) ($row['material_code'] ?? '')))->first();

            if (! $material) {
                return $this->error('material_code', __("Material ':code' not found", [
                    'code' => (string) ($row['material_code'] ?? ''),
                ]));
            }

            $warehouseCode = $row['warehouse_code'] ?? $defaultWarehouseCode;
            $warehouse = null;

            if ($warehouseCode !== null && trim((string) $warehouseCode) !== '') {
                $warehouse = $this->resolveWarehouse((string) $warehouseCode);

                if (! $warehouse) {
                    return $this->error('warehouse_code', __("Warehouse ':code' not found", ['code' => $warehouseCode]));
                }
            }

            $quantity = round((float) ($row['quantity_available'] ?? 0), 4);

            if ($quantity < 0) {
                return $this->error('quantity_available', __('Available quantity cannot be negative'));
            }

            $status = $row['status'] ?? null;

            if ($status !== null && ! in_array($status, MaterialLot::STATUSES, true)) {
                return $this->error('status', __('Unknown lot status :status', ['status' => $status]));
            }

            $existing = MaterialLot::where('lot_number', $lotNumber)->first();

            // material_lots is unique on (lot_number, tenant), so the same number
            // cannot be reused across materials. Say that plainly rather than
            // letting the insert fail with a database constraint message.
            if ($existing && $existing->material_id !== $material->id) {
                return $this->error('lot_number', __("Lot ':lot' already belongs to material ':code'", [
                    'lot' => $lotNumber,
                    'code' => $existing->material?->code ?? (string) $existing->material_id,
                ]));
            }

            if ($existing && $strategy === 'skip_existing') {
                return $this->skipped();
            }

            if ($existing && $strategy === 'error_on_duplicate') {
                return $this->error('lot_number', __("Lot ':lot' already exists", ['lot' => $lotNumber]));
            }

            $attributes = [
                'quantity_available' => $quantity,
                'unit_of_measure' => $row['unit_of_measure'] ?? $material->unit_of_measure,
                'supplier_lot_no' => $row['supplier_lot_no'] ?? null,
                'supplier_reference' => $row['supplier_reference'] ?? null,
                'manufacturing_date' => $row['manufacturing_date'] ?? null,
                'expiry_date' => $row['expiry_date'] ?? null,
                'warehouse_id' => $warehouse?->id,
            ];

            if ($status !== null) {
                $attributes['status'] = $status;
            }

            if ($warehouse) {
                $touched[$warehouse->id.':'.$material->id] = [$warehouse->id, $material->id];
            }

            // The lot row and its warehouse balance are one fact — writing them in
            // separate statements is how the balance drift this class exists to
            // prevent would creep back in.
            if ($existing) {
                DB::transaction(function () use ($existing, $attributes, $quantity, $warehouse) {
                    // Never shrink quantity_received below what is available — the ERP
                    // may only report the free remainder of a larger receipt.
                    $attributes['quantity_received'] = max((float) $existing->quantity_received, $quantity);
                    $existing->update($attributes);
                    $this->syncLotBalance($existing, $warehouse, $quantity);
                });

                return $this->updated();
            }

            DB::transaction(function () use ($row, $material, $lotNumber, $status, $attributes, $quantity, $warehouse) {
                $lot = MaterialLot::create([
                    'lot_number' => $lotNumber,
                    'material_id' => $material->id,
                    'quantity_received' => (float) ($row['quantity_received'] ?? $quantity),
                    'received_at' => $row['received_at'] ?? now(),
                    // An ERP-reported free quantity is stock the ERP already cleared
                    // for use, so it lands released rather than awaiting inspection.
                    'status' => $status ?? MaterialLot::STATUS_RELEASED,
                    ...$attributes,
                ]);

                $this->syncLotBalance($lot, $warehouse, $quantity);
            });

            return $this->created();
        });

        $this->reconcileWarehouseTotals($touched);

        // Warehouse totals moved, so the global per-material quantity must follow.
        $this->reconciler->reconcileMany(array_unique(array_map(fn (array $pair) => $pair[1], $touched)));

        return $report;
    }

    /** Mirror the lot's available quantity into its per-warehouse balance. */
    private function syncLotBalance(MaterialLot $lot, ?Warehouse $warehouse, float $quantity): void
    {
        if (! $warehouse) {
            return;
        }

        WarehouseStock::updateOrCreate(
            [
                'warehouse_id' => $warehouse->id,
                'material_id' => $lot->material_id,
                'material_lot_id' => $lot->id,
                'product_type_id' => null,
            ],
            [
                'quantity' => $quantity,
                'unit_of_measure' => $lot->unit_of_measure,
                'erp_synced_at' => now(),
            ],
        );
    }

    /**
     * Re-derive each touched material's warehouse total from its lot rows, so the
     * bulk row keeps meaning "everything of this material in this warehouse".
     *
     * One grouped SUM for the whole batch instead of a query and a transaction per
     * pair: a 5000-row lot sync can touch hundreds of pairs, and paying a round
     * trip plus a transaction for each is what turns an import into a timeout.
     *
     * @param  array<string, array{0: int, 1: int}>  $pairs
     */
    private function reconcileWarehouseTotals(array $pairs): void
    {
        if ($pairs === []) {
            return;
        }

        $warehouseIds = array_unique(array_map(fn (array $pair) => $pair[0], $pairs));
        $materialIds = array_unique(array_map(fn (array $pair) => $pair[1], $pairs));

        $totals = WarehouseStock::query()
            ->selectRaw('warehouse_id, material_id, sum(quantity) as total')
            ->whereIn('warehouse_id', $warehouseIds)
            ->whereIn('material_id', $materialIds)
            ->whereNotNull('material_lot_id')
            ->groupBy('warehouse_id', 'material_id')
            ->get()
            ->keyBy(fn ($row) => $row->warehouse_id.':'.$row->material_id);

        DB::transaction(function () use ($pairs, $totals) {
            foreach ($pairs as $key => [$warehouseId, $materialId]) {
                WarehouseStock::updateOrCreate(
                    [
                        'warehouse_id' => $warehouseId,
                        'material_id' => $materialId,
                        'material_lot_id' => null,
                        'product_type_id' => null,
                    ],
                    [
                        'quantity' => round((float) ($totals[$key]->total ?? 0), 3),
                        'erp_synced_at' => now(),
                    ],
                );
            }
        });
    }

    private function resolveWarehouse(string $code): ?Warehouse
    {
        return Warehouse::where('code', $code)->orWhere('erp_code', $code)->first();
    }
}
