<?php

namespace Database\Seeders;

use App\Models\MaterialLot;
use App\Models\ProductType;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
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

        $this->command?->info('Warehouse locations seeded: '.count($zones).' zones / '.$bins->count().' bins / '.$assigned.' lots + '.$locatedProducts.' products located');
    }
}
