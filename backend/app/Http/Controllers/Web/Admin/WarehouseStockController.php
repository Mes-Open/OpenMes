<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\ProductType;
use App\Models\Warehouse;
use Inertia\Inertia;

/**
 * Read-only stock overview (#212): what is on hand per warehouse. Balances
 * live-sync via the `warehouse_stocks` shape, so a posted document or an ERP sync
 * shows up without a reload; the id → code maps the table needs to render come as
 * props.
 */
class WarehouseStockController extends Controller
{
    public function index()
    {
        return Inertia::render('admin/warehouse-stock/Index', [
            'warehouses' => Warehouse::orderBy('code')->get(['id', 'code', 'name', 'kind']),
            // Only items that carry a balance somewhere — the overview never renders
            // the rest, and a full material catalogue is a large prop for nothing.
            'materials' => Material::whereHas('warehouseStocks')->orderBy('code')->get(['id', 'code', 'name', 'unit_of_measure']),
            'productTypes' => ProductType::whereHas('warehouseStocks')->orderBy('code')->get(['id', 'code', 'name', 'unit_of_measure']),
            // Only lots that actually carry a balance somewhere — the full lot
            // table can be large and the overview never shows the rest.
            'lots' => MaterialLot::whereHas('warehouseStocks')->get(['id', 'lot_number'])->keyBy('id')->map->lot_number,
        ]);
    }
}
