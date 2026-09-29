<?php

namespace App\Http\Requests;

use App\Models\LotSequence;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Asking the station for the next process or unit serial from the product's sequence. */
class IssueUnitIdentifierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route is behind auth + role:Operator|Supervisor|Admin middleware.
    }

    public function rules(): array
    {
        return [
            'purpose' => ['required', Rule::in([LotSequence::PURPOSE_PROCESS_SERIAL, LotSequence::PURPOSE_UNIT_SERIAL])],
            'work_order_id' => ['nullable', 'integer', Rule::exists('work_orders', 'id')->whereNull('deleted_at')],
        ];
    }
}
