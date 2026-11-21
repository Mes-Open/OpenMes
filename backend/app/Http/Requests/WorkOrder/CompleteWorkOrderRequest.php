<?php

namespace App\Http\Requests\WorkOrder;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A supervisor or admin closes a work order by hand, stating what was produced.
 * Route middleware gates the supervisor/admin area; whether the order may be
 * closed at all (its status, unfinished batches) is a domain rule the
 * controller checks, not a validation one.
 */
class CompleteWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'produced_qty' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
        ];
    }
}
