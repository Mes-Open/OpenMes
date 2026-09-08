<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Drag-to-reorder on the global status list: the ids in their new order.
 *
 * Every id must be an existing **global** status. A line-scoped id would
 * otherwise be renumbered into the global sequence, quietly moving a column
 * that belongs to one line only.
 */
class ReorderLineStatusesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route sits behind auth + the admin tab-access matrix.
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array'],
            'ids.*' => [
                'integer',
                Rule::exists('line_statuses', 'id')->whereNull('line_id')->whereNull('deleted_at'),
            ],
        ];
    }
}
