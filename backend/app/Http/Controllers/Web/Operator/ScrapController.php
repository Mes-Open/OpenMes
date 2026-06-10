<?php

namespace App\Http\Controllers\Web\Operator;

use App\Http\Controllers\Controller;
use App\Models\ScrapEntry;
use App\Models\Shift;
use App\Models\WorkOrder;
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
            'work_order_id'   => 'required|exists:work_orders,id',
            'scrap_reason_id' => ['required', Rule::exists('scrap_reasons', 'id')->where('is_active', true)],
            'quantity'        => 'required|numeric|min:0.01|max:99999999',
            'notes'           => 'nullable|string|max:2000',
        ]);

        $workOrder = WorkOrder::findOrFail($validated['work_order_id']);

        // Verify work order belongs to selected line
        if ($workOrder->line_id != $request->session()->get('selected_line_id')) {
            return back()->with('error', __('This work order does not belong to the selected line.'));
        }

        ScrapEntry::create([
            'work_order_id'   => $workOrder->id,
            'scrap_reason_id' => $validated['scrap_reason_id'],
            'quantity'        => $validated['quantity'],
            // Attribute to the line's current shift automatically when one is running.
            'shift_id'        => Shift::current($workOrder->line_id)?->id,
            'notes'           => $validated['notes'] ?? null,
            'reported_by'     => auth()->id(),
            'reported_at'     => now(),
        ]);

        return redirect()->back()->with('success', __('Scrap recorded successfully.'));
    }
}
