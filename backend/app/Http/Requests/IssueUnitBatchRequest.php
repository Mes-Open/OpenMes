<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A batch of numbers issued ahead of production, so the labels can be printed
 * before the units reach the line. The order is required: the numbers come
 * from its product's sequences and the units are registered on it.
 */
class IssueUnitBatchRequest extends FormRequest
{
    public const MAX = 500;

    public function authorize(): bool
    {
        return true; // Route is behind auth + role:Operator|Supervisor|Admin middleware.
    }

    public function rules(): array
    {
        return [
            'work_order_id' => ['required', 'integer', Rule::exists('work_orders', 'id')->whereNull('deleted_at')],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.self::MAX],
            // Number the units by their process serial only; the SN comes later.
            'psn_only' => ['nullable', 'boolean'],
        ];
    }
}
