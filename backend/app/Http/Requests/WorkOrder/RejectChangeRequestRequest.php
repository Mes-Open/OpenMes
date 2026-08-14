<?php

namespace App\Http\Requests\WorkOrder;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Rejecting a submitted change request (#182).
 *
 * The reason is mandatory: a rejection with no explanation is the audit gap this
 * workflow is meant to close.
 */
class RejectChangeRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is handled by the controller/policy.
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A reason for rejecting the change is required.',
        ];
    }
}
