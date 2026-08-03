<?php

namespace App\Http\Requests\Web\Admin;

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
        return true; // route is already behind the admin role middleware
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['accept', 'reject', 'pause', 'resume', 'cancel', 'reopen'])],
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', Rule::exists('work_orders', 'id')->whereNull('deleted_at')],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Select at least one work order.',
            'ids.max' => 'Too many work orders selected at once (max 500).',
        ];
    }
}
