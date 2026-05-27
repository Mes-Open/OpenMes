<?php

namespace App\Http\Controllers\Web\Operator;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\WorkOrder;
use App\Models\WorkOrderShiftEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductionCorrectionController extends Controller
{
    /**
     * Show correction form for a shift entry.
     */
    public function edit(WorkOrderShiftEntry $shiftEntry)
    {
        $this->authorizeCorrection($shiftEntry);

        $shiftEntry->load(['workOrder.productType', 'shift']);

        return view('operator.correct-quantity', [
            'shiftEntry' => $shiftEntry,
            'workOrder' => $shiftEntry->workOrder,
        ]);
    }

    /**
     * Update the shift entry quantity.
     */
    public function update(Request $request, WorkOrderShiftEntry $shiftEntry)
    {
        $this->authorizeCorrection($shiftEntry);

        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0|max:99999999',
        ]);

        $oldQty = (float) $shiftEntry->quantity;
        $newQty = (float) $validated['quantity'];

        if ($oldQty === $newQty) {
            return redirect()->route('operator.workstation')
                ->with('info', __('No changes made.'));
        }

        try {
            DB::transaction(function () use ($shiftEntry, $oldQty, $newQty) {
                // Log the correction in audit
                AuditLog::create([
                    'user_id' => auth()->id(),
                    'entity_type' => WorkOrderShiftEntry::class,
                    'entity_id' => $shiftEntry->id,
                    'action' => 'quantity_corrected',
                    'before_state' => ['quantity' => $oldQty],
                    'after_state' => ['quantity' => $newQty],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                // Update the shift entry
                $shiftEntry->update([
                    'quantity' => $newQty,
                    'user_id' => auth()->id(),
                ]);

                // Recalculate work order produced_qty from all shift entries
                $workOrder = $shiftEntry->workOrder;
                $totalProduced = WorkOrderShiftEntry::where('work_order_id', $workOrder->id)
                    ->sum('quantity');

                $workOrder->update(['produced_qty' => $totalProduced]);
            });
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', __('Failed to save correction. Please try again.'));
        }

        return redirect()->route('operator.workstation')
            ->with('success', __('Quantity corrected successfully.'));
    }

    /**
     * Check whether the current policy allows correction.
     */
    private function authorizeCorrection(WorkOrderShiftEntry $shiftEntry): void
    {
        // Ownership check — only entry creator or Supervisor/Admin can correct
        $user = auth()->user();
        if (
            $shiftEntry->user_id !== $user->id
            && ! $user->hasRole(['Supervisor', 'Admin'])
        ) {
            abort(403, __('You can only correct your own entries.'));
        }

        $settings = DB::table('system_settings')
            ->whereIn('key', ['production_qty_edit_policy', 'production_qty_edit_window_minutes'])
            ->pluck('value', 'key');

        $policy = json_decode($settings['production_qty_edit_policy'] ?? '"none"', true) ?? 'none';

        if ($policy === 'none') {
            abort(403, __('Quantity corrections are not allowed.'));
        }

        if ($policy === 'timed') {
            $windowMinutes = json_decode($settings['production_qty_edit_window_minutes'] ?? '1', true) ?? 1;
            $deadline = $shiftEntry->updated_at->addMinutes($windowMinutes);

            if (now()->greaterThan($deadline)) {
                abort(403, __('The correction time window has expired.'));
            }
        }

        // policy === 'full' → always allowed
    }
}
