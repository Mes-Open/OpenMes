<?php

namespace Modules\Packaging\Controllers;

use App\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Packaging\Models\WorkOrderEan;

class PackagingEanController extends Controller
{
    public function index(Request $request)
    {
        $workOrders = WorkOrder::with('productType', 'eans')
            ->when($request->search, fn($q) => $q->where('order_no', 'like', '%' . $request->search . '%'))
            ->orderBy('order_no')
            ->paginate(30)
            ->withQueryString();

        return view('packaging::eans.index', compact('workOrders'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'work_order_id' => 'required|exists:work_orders,id',
            'ean'           => 'required|string|max:100|unique:work_order_eans,ean',
        ]);

        WorkOrderEan::create([
            'work_order_id' => $request->work_order_id,
            'ean'           => $request->ean,
        ]);

        return back()->with('success', 'Kod EAN został dodany.');
    }

    public function destroy(WorkOrderEan $ean)
    {
        $ean->delete();
        return back()->with('success', 'Kod EAN został usunięty.');
    }
}
