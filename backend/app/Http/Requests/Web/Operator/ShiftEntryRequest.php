<?php

namespace App\Http\Requests\Web\Operator;

use App\Models\Shift;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A quantity an operator records against one shift of a work order's line.
 * The station only offers the shifts that run on that line; this refuses any
 * other, so a crafted request cannot book output under a shift (or another
 * plant's shift) that never runs there.
 */
class ShiftEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shift_id' => ['required', 'integer', Rule::exists('shifts', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'quantity' => 'required|numeric|min:1',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $lineId = $this->route('workOrder')?->line_id;
            $shift = Shift::find($this->integer('shift_id'));

            if ($lineId === null || $shift === null || ! $shift->runsOnLine((int) $lineId)) {
                $validator->errors()->add('shift_id', __('This shift does not run on the work order\'s line.'));
            }
        });
    }
}
