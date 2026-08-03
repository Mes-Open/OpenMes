<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\StoreWarehouseRequest;
use App\Http\Requests\Web\Admin\UpdateWarehouseRequest;
use App\Models\StockDocument;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Inertia\Inertia;

/**
 * Admin CRUD for warehouses (#212). Rows live-sync via the `warehouses` shape;
 * the usage counts a warehouse cannot be deleted with come as props.
 */
class WarehouseController extends Controller
{
    public function index()
    {
        return Inertia::render('admin/warehouses/Index', [
            // Non-zero balances only, so this agrees with the delete guard below
            // (an emptied slot keeps a zero row and must not read as "holds stock").
            'stockCounts' => WarehouseStock::query()
                ->where('quantity', '!=', 0)
                ->selectRaw('warehouse_id, count(*) as total')
                ->groupBy('warehouse_id')
                ->pluck('total', 'warehouse_id'),
            'documentCounts' => StockDocument::query()
                ->selectRaw('warehouse_id, count(*) as total')
                ->groupBy('warehouse_id')
                ->pluck('total', 'warehouse_id'),
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/warehouses/Create');
    }

    public function store(StoreWarehouseRequest $request)
    {
        $data = $request->validated();
        $makeDefault = (bool) ($data['is_default'] ?? false);
        $data['is_default'] = false;

        $warehouse = Warehouse::create($data);

        // Setting the flag directly could leave two defaults for one kind;
        // makeDefault() clears the previous holder first.
        if ($makeDefault) {
            $warehouse->makeDefault();
        }

        return redirect()->route('admin.warehouses.index')
            ->with('success', __('Warehouse created successfully.'));
    }

    public function edit(Warehouse $warehouse)
    {
        return Inertia::render('admin/warehouses/Edit', [
            'warehouse' => $warehouse->only(
                'id', 'code', 'name', 'description', 'kind', 'erp_code', 'is_default', 'is_active'
            ),
        ]);
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse)
    {
        $data = $request->validated();
        $makeDefault = (bool) ($data['is_default'] ?? false);
        unset($data['is_default']);

        $warehouse->update($data);

        if ($makeDefault) {
            $warehouse->makeDefault();
        } elseif ($warehouse->is_default) {
            $warehouse->update(['is_default' => false]);
        }

        return redirect()->route('admin.warehouses.index')
            ->with('success', __('Warehouse updated successfully.'));
    }

    /**
     * Soft-delete a warehouse. Refused while it still holds stock or carries
     * documents — those rows reference it, and losing the reference would make
     * the ledger unreadable. Deactivating keeps it out of the way instead.
     */
    public function destroy(Warehouse $warehouse)
    {
        if ($warehouse->stocks()->where('quantity', '!=', 0)->exists()) {
            return redirect()->route('admin.warehouses.index')
                ->with('error', __('Cannot delete a warehouse that still holds stock. Deactivate it instead.'));
        }

        if ($warehouse->stockDocuments()->exists()) {
            return redirect()->route('admin.warehouses.index')
                ->with('error', __('Cannot delete a warehouse with stock documents. Deactivate it instead.'));
        }

        $warehouse->delete();

        return redirect()->route('admin.warehouses.index')
            ->with('success', __('Warehouse deleted successfully.'));
    }

    public function toggleActive(Warehouse $warehouse)
    {
        $warehouse->update(['is_active' => ! $warehouse->is_active]);

        return redirect()->route('admin.warehouses.index')
            ->with('success', $warehouse->is_active
                ? __('Warehouse activated successfully.')
                : __('Warehouse deactivated successfully.'));
    }

    /** Make this the warehouse used when an import or generated document names none. */
    public function setDefault(Warehouse $warehouse)
    {
        $warehouse->makeDefault();

        return redirect()->route('admin.warehouses.index')
            ->with('success', __('Default warehouse updated successfully.'));
    }
}
