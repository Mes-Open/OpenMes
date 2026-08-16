<?php

namespace App\Http\Requests\WorkOrder;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Resuming a stopped work order (#182).
 *
 * Everything is optional: an order paused the simple way resumes on an empty body,
 * which is what keeps the existing pause/resume behaviour working. Whether an applied
 * change request is actually required is a domain rule, not a validation one — the
 * service decides it from the stop, and answers 422 with the reason.
 */
class ResumeWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is handled by the controller/policy.
        return true;
    }

    public function rules(): array
    {
        return [
            'change_request_id' => [
                'nullable', 'integer',
                Rule::exists('work_order_change_requests', 'id')
                    ->where(fn ($q) => $q->where('work_order_id', $this->route('workOrder')?->id)),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'change_request_id.exists' => 'The selected change request does not belong to this work order.',
        ];
    }
}
