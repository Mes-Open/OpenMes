<?php

namespace App\Http\Controllers\Web\Packaging;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use App\Models\WorkOrderEan;
use Illuminate\Http\Request;

class PackagingEanController extends Controller
{
    public function index(Request $request)
    {
        $search = (string) $request->query('search', '');

        $workOrders = WorkOrder::with('productType', 'eans')
            ->when($search !== '', fn ($q) => $q->where('order_no', 'ilike', '%'.$search.'%'))
            ->orderBy('order_no')
            ->paginate(30)
            ->withQueryString();

        return view('packaging.eans.index', compact('workOrders'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'work_order_id' => 'required|exists:work_orders,id',
            'ean' => 'required|string|max:100|unique:work_order_eans,ean',
        ]);

        WorkOrderEan::create($validated);

        return back()->with('success', __('EAN code added.'));
    }

    public function destroy(WorkOrderEan $ean)
    {
        $ean->delete();

        return back()->with('success', __('EAN code removed.'));
    }
}
