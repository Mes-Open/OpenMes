<?php

namespace App\Http\Controllers\Web\Operator;

use App\Http\Controllers\Controller;
use App\Models\ScrapEntry;
use App\Models\Shift;
use App\Models\WorkOrder;
use App\Services\Production\OperatorWorkstationSelection;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ScrapController extends Controller
{
    /**
     * Record a scrap entry against a work order from the operator detail page.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'work_order_id' => 'required|exists:work_orders,id',
            'scrap_reason_id' => ['required', Rule::exists('scrap_reasons', 'id')->where('is_active', true)],
            'quantity' => 'required|numeric|min:0.01|max:99999999',
            'notes' => 'nullable|string|max:2000',
        ]);

        $workOrder = WorkOrder::findOrFail($validated['work_order_id']);

        // On the selected line, or routed through the operator's workstation.
        if (! app(OperatorWorkstationSelection::class)->servesWorkOrder($request, $workOrder)) {
            return back()->with('error', __('This work order does not belong to the selected line.'));
        }

        if ($workOrder->usesStepLedger()) {
            return back()->withErrors(['quantity' => __('Record scrap on the work order steps.')]);
        }

        ScrapEntry::create([
            'work_order_id' => $workOrder->id,
            'scrap_reason_id' => $validated['scrap_reason_id'],
            'quantity' => $validated['quantity'],
            // Attribute to the line's current shift automatically when one is running.
            'shift_id' => Shift::current($workOrder->line_id)?->id,
            'notes' => $validated['notes'] ?? null,
            'reported_by' => auth()->id(),
            'reported_at' => now(),
        ]);

        return redirect()->back()->with('success', __('Scrap recorded successfully.'));
    }
}
