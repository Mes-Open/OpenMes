<?php

namespace App\Http\Requests\WorkOrder;

use App\Enums\ChangeRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filter for the change-request list: one status, or OPEN for everything still
 * waiting on somebody (draft, submitted, approved).
 */
class IndexChangeRequestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is handled by the controller/policy.
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in([...ChangeRequestStatus::values(), 'OPEN'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
