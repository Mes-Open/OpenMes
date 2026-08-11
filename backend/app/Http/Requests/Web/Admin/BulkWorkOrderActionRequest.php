<?php

namespace App\Http\Requests\Web\Admin;

use App\Models\WorkOrder;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a bulk status transition submitted from the work-order list's
 * selection toolbar.
 *
 * Note what is deliberately *not* validated here: whether each order is actually
 * in a status the action is legal from. A selection routinely spans mixed
 * statuses, and rejecting the whole request for that would make the feature
 * unusable — the controller skips the ineligible ones and reports the count.
 */
class BulkWorkOrderActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // both routes using this are behind Admin/Supervisor role middleware
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['accept', 'reject', 'pause', 'resume', 'cancel', 'reopen'])],
            // Existence is checked once for the whole set (see withValidator) —
            // `Rule::exists` on `ids.*` would issue one SELECT per id, up to 500
            // round trips before the action does any work.
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
        ];
    }

    /** One query for the whole selection instead of one per id. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $ids = array_filter((array) $this->input('ids', []), 'is_numeric');
            if ($ids === []) {
                return;
            }

            // The model's SoftDeletes scope applies, so a trashed order counts as
            // missing — same outcome the per-id `exists` rule gave.
            $found = WorkOrder::whereIn('id', $ids)->pluck('id')->all();
            $found = array_flip(array_map('intval', $found));

            // Reported per index, keeping the `ids.0` error keys callers rely on.
            foreach ((array) $this->input('ids', []) as $index => $id) {
                if (! isset($found[(int) $id])) {
                    $validator->errors()->add("ids.{$index}", __('Some of the selected work orders no longer exist.'));
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Select at least one work order.',
            'ids.max' => 'Too many work orders selected at once (max 500).',
        ];
    }
}
