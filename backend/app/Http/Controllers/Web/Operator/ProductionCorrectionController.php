<?php

namespace App\Http\Controllers\Web\Operator;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\WorkOrder;
use App\Models\WorkOrderShiftEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ProductionCorrectionController extends Controller
{
    /**
     * Show correction form for a shift entry.
     */
    public function edit(WorkOrderShiftEntry $shiftEntry)
    {
        $this->authorizeCorrection($shiftEntry);

        $shiftEntry->load(['workOrder.productType', 'workOrder.line', 'shift']);

        return Inertia::render('operator/CorrectQuantity', [
            'shiftEntry' => [
                'id' => $shiftEntry->id,
                'quantity' => (float) $shiftEntry->quantity,
                'production_date' => $shiftEntry->production_date->format('Y-m-d'),
                'shift' => ['name' => $shiftEntry->shift->name ?? null, 'code' => $shiftEntry->shift->code ?? null],
            ],
            'workOrder' => [
                'order_no' => $shiftEntry->workOrder->order_no,
                'product_name' => $shiftEntry->workOrder->productType?->name,
            ],
            'line' => $shiftEntry->workOrder->line
                ? ['id' => $shiftEntry->workOrder->line->id, 'name' => $shiftEntry->workOrder->line->name]
                : null,
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

        $newQty = (float) $validated['quantity'];

        try {
            // Under the work-order lock production reporting takes too
            // (WorkstationController), with the entry re-read and re-checked
            // inside it: two corrections at once must not both fit the plan.
            $outcome = DB::transaction(function () use ($shiftEntry, $newQty) {
                $workOrder = WorkOrder::whereKey($shiftEntry->work_order_id)->lockForUpdate()->firstOrFail();
                $shiftEntry = WorkOrderShiftEntry::whereKey($shiftEntry->getKey())->lockForUpdate()->firstOrFail();
                $this->authorizeCorrection($shiftEntry);

                $oldQty = (float) $shiftEntry->quantity;
                if ($oldQty === $newQty) {
                    return 'unchanged';
                }

                // The stored total moves by the difference: it also holds
                // quantities added directly, which the shift entries do not.
                $produced = (float) $workOrder->produced_qty;
                $newProduced = max(0.0, $produced - $oldQty + $newQty);

                // Only a raise can go over the plan; lowering a mistaken entry always works.
                if ($newQty > $oldQty && \App\Support\ProductionRules::exceedsPlan($workOrder, $newProduced)) {
                    return (float) $workOrder->planned_qty - $produced;
                }

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

                // Update the shift entry without touching updated_at: the timed
                // correction window runs from the last reported quantity, and a
                // correction must not reopen it (the audit log records when).
                $shiftEntry->timestamps = false;
                $shiftEntry->update([
                    'quantity' => $newQty,
                    'user_id' => auth()->id(),
                ]);
                $shiftEntry->timestamps = true;

                $workOrder->update(['produced_qty' => $newProduced]);

                return 'saved';
            });
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            throw $e; // a 403 from the re-check, not a failed save
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', __('Failed to save correction. Please try again.'));
        }

        if ($outcome === 'unchanged') {
            return redirect()->route('operator.workstation')
                ->with('info', __('No changes made.'));
        }

        if (is_float($outcome)) {
            return back()->withErrors(['quantity' => \App\Support\ProductionRules::exceedsPlanMessage($outcome)])->withInput();
        }

        return redirect()->route('operator.workstation')
            ->with('success', __('Quantity corrected successfully.'));
    }

    /**
     * Check whether the current policy allows correction.
     */
    private function authorizeCorrection(WorkOrderShiftEntry $shiftEntry): void
    {
        abort_if($shiftEntry->workOrder->usesStepLedger(), 403, __('Record production on the work order steps.'));

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
