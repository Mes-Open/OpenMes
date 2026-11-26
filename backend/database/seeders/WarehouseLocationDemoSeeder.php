<?php

namespace Database\Seeders;

use App\Models\MaterialLot;
use App\Models\ProductType;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WarehouseLocationMove;
use App\Models\WarehouseStock;
use Illuminate\Database\Seeder;

/**
 * Two-level locations for the demo warehouses, plus the bins the existing
 * material lots physically sit in. The page reads this as a location map; it
 * never moves stock, so assigning a lot to a bin cannot change quantities.
 */
class WarehouseLocationDemoSeeder extends Seeder
{
    public function run(): void
    {
        $warehouses = Warehouse::whereIn('code', ['NT-WH-RM', 'NT-WH-FG'])->get()->keyBy('code');

        if ($warehouses->count() < 2) {
            $this->command?->warn('Demo warehouses missing; skip locations.');

            return;
        }

        $zones = [
            [
                'warehouse' => 'NT-WH-RM',
                'code' => 'NT-LOC-RM-A',
                'name' => '原料 A 区',
                'description' => '叶类茶、果泥与乳品收货后的暂存区。',
                'sort_order' => 10,
                'bins' => [
                    ['code' => 'A-01', 'name' => '叶类茶位', 'description' => '绿茶、茉莉花茶胚、单丛与焙火乌龙。', 'sort_order' => 1],
                    ['code' => 'A-02', 'name' => '果泥乳品位', 'description' => '芒果果泥与鲜牛奶，按批次先进先出。', 'sort_order' => 2],
                    ['code' => 'A-03', 'name' => '辅料位', 'description' => '糖浆、果肉、柠檬原浆与淡奶油。', 'sort_order' => 3],
                    ['code' => 'A-04', 'name' => '待处理位', 'description' => '待检、拒收或等待退货的物料批。', 'sort_order' => 4],
                ],
            ],
            [
                'warehouse' => 'NT-WH-RM',
                'code' => 'NT-LOC-RM-B',
                'name' => '包材 B 区',
                'description' => '杯、盖、吸管、标签与外箱。',
                'sort_order' => 20,
                'bins' => [
                    ['code' => 'B-01', 'name' => '杯盖位', 'description' => '注塑杯、杯盖与吸管。', 'sort_order' => 1],
                    ['code' => 'B-02', 'name' => '标签纸箱位', 'description' => '杯身标签、外箱与装箱耗材。', 'sort_order' => 2],
                ],
            ],
            [
                'warehouse' => 'NT-WH-FG',
                'code' => 'NT-LOC-FG-A',
                'name' => '成品待发区',
                'description' => '已完成批次等待发货的成品暂存区。',
                'sort_order' => 10,
                'bins' => [
                    ['code' => 'FG-01', 'name' => '冷藏成品位', 'description' => '冷萃茶底与需冷藏成品。', 'sort_order' => 1],
                    ['code' => 'FG-02', 'name' => '常温成品位', 'description' => '柠檬茶、奶盖与果茶成品。', 'sort_order' => 2],
                ],
            ],
        ];

        $bins = collect();

        foreach ($zones as $zone) {
            $warehouse = $warehouses->get($zone['warehouse']);

            $zoneModel = WarehouseLocation::updateOrCreate(
                ['warehouse_id' => $warehouse->id, 'code' => $zone['code']],
                [
                    'parent_id' => null,
                    'name' => $zone['name'],
                    'kind' => WarehouseLocation::KIND_ZONE,
                    'description' => $zone['description'],
                    'sort_order' => $zone['sort_order'],
                    'is_active' => true,
                ],
            );

            foreach ($zone['bins'] as $bin) {
                $bins->put($bin['code'], WarehouseLocation::updateOrCreate(
                    ['warehouse_id' => $warehouse->id, 'code' => $bin['code']],
                    [
                        'parent_id' => $zoneModel->id,
                        'name' => $bin['name'],
                        'kind' => WarehouseLocation::KIND_BIN,
                        'description' => $bin['description'],
                        'sort_order' => $bin['sort_order'],
                        'is_active' => true,
                    ],
                ));
            }
        }

        $assignments = [
            'NT-RM-GREEN-20261124' => 'A-01',
            'NT-RM-JASMINE-20261124' => 'A-01',
            'NT-RM-OOLONG-20261124' => 'A-01',
            'NT-RM-DONGFANG-20261124' => 'A-01',
            'NT-RM-MANGO-20261124-A' => 'A-02',
            'NT-RM-MANGO-20261124-B' => 'A-04',
            'NT-RM-MILK-20261124' => 'A-02',
        ];

        $assigned = 0;

        foreach ($assignments as $lotNumber => $binCode) {
            $bin = $bins->get($binCode);

            if (! $bin) {
                continue;
            }

            $assigned += MaterialLot::where('lot_number', $lotNumber)
                ->where('warehouse_id', $bin->warehouse_id)
                ->update(['warehouse_location_id' => $bin->id]);
        }

        $productAssignments = [
            'NT-FG-JASMINE-COLD' => 'FG-01',
            'NT-FG-DONGFANG-LEMON' => 'FG-02',
            'NT-FG-OOLONG-CREAM' => 'FG-02',
            'NT-FG-MANGO-SAGO' => 'FG-02',
        ];

        $products = ProductType::whereIn('code', array_keys($productAssignments))->get()->keyBy('code');
        $locatedProducts = 0;

        foreach ($productAssignments as $productCode => $binCode) {
            $bin = $bins->get($binCode);
            $product = $products->get($productCode);

            if (! $bin || ! $product) {
                continue;
            }

            $locatedProducts += WarehouseStock::where('warehouse_id', $bin->warehouse_id)
                ->where('product_type_id', $product->id)
                ->update(['warehouse_location_id' => $bin->id]);
        }

        $stockRowAssignments = [
            'NT-RM-SUGAR' => 'A-03',
            'NT-RM-FRUIT-GRAPE' => 'A-03',
            'NT-RM-FRUIT-LEMON' => 'A-03',
            'NT-RM-DAIRY-CREAM' => 'A-03',
            'NT-PKG-CUP-700' => 'B-01',
            'NT-PKG-LID-700' => 'B-01',
            'NT-PKG-STRAW' => 'B-01',
            'NT-PKG-LABEL' => 'B-02',
            'NT-PKG-CARTON' => 'B-02',
        ];

        $stockRowNotes = [
            'NT-RM-SUGAR' => '糖浆整桶上架到辅料 A 区',
            'NT-RM-FRUIT-GRAPE' => '果肉整箱上架到辅料 A 区',
            'NT-RM-FRUIT-LEMON' => '原浆整桶上架到辅料 A 区',
            'NT-RM-DAIRY-CREAM' => '淡奶油冷藏暂存辅料 A 区',
            'NT-PKG-CUP-700' => '注塑杯整箱上架到包材 B 区',
            'NT-PKG-LID-700' => '杯盖整箱上架到包材 B 区',
            'NT-PKG-STRAW' => '纸吸管整箱上架到包材 B 区',
            'NT-PKG-LABEL' => '杯身标签上架到标签纸箱位',
            'NT-PKG-CARTON' => '成品外箱上架到标签纸箱位',
        ];

        $locatedStockRows = 0;
        $locatedStockRowModels = collect();

        foreach ($stockRowAssignments as $materialCode => $binCode) {
            $bin = $bins->get($binCode);

            if (! $bin) {
                continue;
            }

            $stock = WarehouseStock::where('warehouse_id', $bin->warehouse_id)
                ->whereNull('product_type_id')
                ->whereHas('material', fn ($query) => $query->where('code', $materialCode))
                ->first();

            if ($stock) {
                $stock->update(['warehouse_location_id' => $bin->id]);
                $stock->loadMissing('material');
                $locatedStockRowModels->put($materialCode, $stock);
                $locatedStockRows++;
            }
        }

        if (! WarehouseLocationMove::where('warehouse_id', $warehouses->get('NT-WH-RM')->id)->exists()) {
            $lot = MaterialLot::where('lot_number', 'NT-RM-DONGFANG-20261124')->first();
            $mango = WarehouseStock::where('warehouse_id', $warehouses->get('NT-WH-FG')->id)
                ->whereNotNull('product_type_id')
                ->with('productType')
                ->get()
                ->first(fn (WarehouseStock $stock) => $stock->productType?->code === 'NT-FG-MANGO-SAGO');


            $recordMove = function (string $type, $item, ?string $fromCode, string $toCode, int $minutesAgo, string $note) use ($bins) {
                $toBin = $bins->get($toCode);
                $fromBin = $fromCode ? $bins->get($fromCode) : null;

                if (! $toBin || ! $item) {
                    return;
                }

                $snapshot = match ($type) {
                    WarehouseLocationMove::ITEM_MATERIAL_LOT => [
                        'item_code' => trim(($item->material?->code ?? '').' · '.$item->lot_number, ' ·'),
                        'item_name' => $item->material?->name ?? $item->lot_number,
                        'quantity' => (float) $item->quantity_available,
                        'unit_of_measure' => $item->unit_of_measure,
                    ],
                    WarehouseLocationMove::ITEM_MATERIAL_STOCK => [
                        'item_code' => $item->material?->code,
                        'item_name' => $item->material?->name ?? '物料',
                        'quantity' => (float) $item->quantity,
                        'unit_of_measure' => $item->unit_of_measure,
                    ],
                    default => [
                        'item_code' => $item->productType?->code,
                        'item_name' => $item->productType?->name ?? '成品',
                        'quantity' => (float) $item->quantity,
                        'unit_of_measure' => $item->unit_of_measure,
                    ],
                };

                WarehouseLocationMove::create([
                    'warehouse_id' => $toBin->warehouse_id,
                    'from_location_id' => $fromBin?->id,
                    'to_location_id' => $toBin->id,
                    'item_type' => $type,
                    'item_id' => $item->id,
                    ...$snapshot,
                    'moved_at' => now()->subMinutes($minutesAgo),
                    'note' => $note,
                ]);
            };

            $recordMove(WarehouseLocationMove::ITEM_MATERIAL_LOT, $lot, 'A-01', 'A-03', 60, '叶类茶集中到辅料位，等待盘点复核');
            $recordMove(WarehouseLocationMove::ITEM_MATERIAL_LOT, $lot, 'A-03', 'A-01', 50, '盘点结束，凤凰单丛回到叶类茶位');
            $recordMove(WarehouseLocationMove::ITEM_PRODUCT_STOCK, $mango, 'FG-02', 'FG-01', 20, '杨枝甘露临时移入冷藏位，等待发货复核');
            $recordMove(WarehouseLocationMove::ITEM_PRODUCT_STOCK, $mango, 'FG-01', 'FG-02', 10, '复核完成，杨枝甘露回到常温成品位');

            foreach ($stockRowAssignments as $materialCode => $binCode) {
                $recordMove(WarehouseLocationMove::ITEM_MATERIAL_STOCK, $locatedStockRowModels->get($materialCode), null, $binCode, 5, $stockRowNotes[$materialCode] ?? '账面库存上架到库位');
            }
        }

        $this->command?->info('Warehouse locations seeded: '.count($zones).' zones / '.$bins->count().' bins / '.$assigned.' lots + '.$locatedProducts.' products + '.$locatedStockRows.' stock rows located');
    }
}
