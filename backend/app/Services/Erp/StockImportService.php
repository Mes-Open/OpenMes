<?php

namespace App\Services\Erp;

use App\Models\Material;
use App\Models\ProductType;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\Erp\Concerns\ReportsImportRows;
use App\Services\Warehouse\MaterialStockReconciler;

/**
 * ERP → OpenMES available-quantity sync (#212).
 *
 * The ERP owns the warehouse, so an imported quantity is a snapshot that REPLACES
 * the OpenMES balance — repeated syncs converge. For materials the global
 * materials.stock_quantity is then re-derived as the sum of that material's
 * warehouse balances, and the delta is booked as an `adjustment` in the
 * stock_movements ledger: allocation, MRP and shortage reports all read
 * stock_quantity, and a silent overwrite would leave them with an unexplained
 * jump and no audit trail.
 */
class StockImportService
{
    use ReportsImportRows;

    public function __construct(private MaterialStockReconciler $reconciler) {}

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{imported: int, updated: int, skipped: int, errors: array<int, array<string, mixed>>}
     */
    public function import(array $rows, ?string $defaultWarehouseCode = null): array
    {
        /** @var array<int, int> materials whose global quantity must be re-derived */
        $touchedMaterials = [];

        $report = $this->processRows($rows, function (array $row) use ($defaultWarehouseCode, &$touchedMaterials) {
            $warehouseCode = trim((string) ($row['warehouse_code'] ?? $defaultWarehouseCode ?? ''));

            if ($warehouseCode === '') {
                return $this->error('warehouse_code', __('Warehouse code is required'));
            }

            $warehouse = Warehouse::where('code', $warehouseCode)->orWhere('erp_code', $warehouseCode)->first();

            if (! $warehouse) {
                return $this->error('warehouse_code', __("Warehouse ':code' not found", ['code' => $warehouseCode]));
            }

            $materialCode = trim((string) ($row['material_code'] ?? ''));
            $productCode = trim((string) ($row['product_type_code'] ?? ''));

            if (($materialCode === '') === ($productCode === '')) {
                return $this->error('material_code', __('Give exactly one of material_code or product_type_code'));
            }

            $quantity = round((float) ($row['quantity'] ?? 0), 3);

            if ($quantity < 0) {
                return $this->error('quantity', __('Quantity cannot be negative'));
            }

            if ($materialCode !== '') {
                $material = Material::where('code', $materialCode)->first();

                if (! $material) {
                    return $this->error('material_code', __("Material ':code' not found", ['code' => $materialCode]));
                }

                if (! $warehouse->acceptsMaterials()) {
                    return $this->error('warehouse_code', __("Warehouse ':code' cannot hold materials", ['code' => $warehouseCode]));
                }

                $existed = $this->writeBalance($warehouse->id, [
                    'material_id' => $material->id,
                    'product_type_id' => null,
                    'material_lot_id' => null,
                ], $quantity, $row['unit_of_measure'] ?? $material->unit_of_measure);

                $touchedMaterials[$material->id] = $material->id;

                return $existed ? $this->updated() : $this->created();
            }

            $product = ProductType::where('code', $productCode)->first();

            if (! $product) {
                return $this->error('product_type_code', __("Product ':code' not found", ['code' => $productCode]));
            }

            if (! $warehouse->acceptsProducts()) {
                return $this->error('warehouse_code', __("Warehouse ':code' cannot hold finished product", ['code' => $warehouseCode]));
            }

            $existed = $this->writeBalance($warehouse->id, [
                'material_id' => null,
                'product_type_id' => $product->id,
                'material_lot_id' => null,
            ], $quantity, $row['unit_of_measure'] ?? $product->unit_of_measure);

            return $existed ? $this->updated() : $this->created();
        });

        $this->reconciler->reconcileMany($touchedMaterials);

        return $report;
    }

    /**
     * Overwrite one balance. Returns whether the row already existed, so the
     * report can tell a first sync from a refresh.
     *
     * @param  array<string, int|null>  $keys
     */
    private function writeBalance(int $warehouseId, array $keys, float $quantity, ?string $unit): bool
    {
        $keys = ['warehouse_id' => $warehouseId, ...$keys];

        $existing = WarehouseStock::where($keys)->first();

        WarehouseStock::updateOrCreate($keys, [
            'quantity' => $quantity,
            'unit_of_measure' => $unit,
            'erp_synced_at' => now(),
        ]);

        return $existing !== null;
    }
}
