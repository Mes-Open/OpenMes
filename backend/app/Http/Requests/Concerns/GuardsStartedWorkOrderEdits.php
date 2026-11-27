<?php

namespace App\Http\Requests\Concerns;

use App\Models\Batch;
use App\Models\WorkOrder;
use Illuminate\Validation\Validator;

/**
 * Edits that would strand an order already in production, shared by the admin
 * and supervisor edit forms:
 *
 * - planned below what was already produced (the order would read as over-
 *   produced and could never balance again);
 * - another line while a batch is still open — its steps are assigned to
 *   stations of the current line, so the order would drop out of every queue
 *   and the batch could never be finished;
 * - another product once batches exist — they were built from the old
 *   product's routing.
 */
trait GuardsStartedWorkOrderEdits
{
    /** @param  bool  $product  false where the controller already refuses the product change itself (admin form) */
    protected function guardStartedOrder(Validator $validator, ?WorkOrder $workOrder, bool $product = true): void
    {
        if ($workOrder === null) {
            return;
        }

        $validator->after(function (Validator $v) use ($workOrder, $product) {
            $produced = (float) $workOrder->produced_qty;
            if ($this->filled('planned_qty') && (float) $this->input('planned_qty') + 0.0001 < $produced) {
                $v->errors()->add('planned_qty', __('Planned quantity cannot be below what was already produced (:produced).', [
                    'produced' => rtrim(rtrim(number_format($produced, 4, '.', ''), '0'), '.'),
                ]));
            }

            if ($this->filled('line_id') && (int) $this->input('line_id') !== (int) $workOrder->line_id) {
                $open = $workOrder->batches()
                    ->whereIn('status', [Batch::STATUS_PENDING, Batch::STATUS_IN_PROGRESS])
                    ->orderBy('batch_number')->value('batch_number');
                if ($open !== null) {
                    $v->errors()->add('line_id', __('Batch #:batch is still open on this line. Finish or cancel it before moving the order to another line.', ['batch' => $open]));
                }
            }

            if ($product && $this->filled('product_type_id') && (int) $this->input('product_type_id') !== (int) $workOrder->product_type_id
                && $workOrder->batches()->exists()) {
                $v->errors()->add('product_type_id', __('The product cannot be changed once production has started.'));
            }
        });
    }
}
