<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Batch;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Release a completed batch. Releasing for sale needs the
 * `release batch for sale` permission (Supervisor and Admin by default);
 * releasing for production is open to anyone who reaches the endpoint.
 * The operator screen uses the same rule (Operator\ReleaseBatchRequest).
 */
class ReleaseBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->input('release_type') !== Batch::RELEASE_FOR_SALE) {
            return true;
        }

        return (bool) $this->user()?->can(Batch::PERMISSION_RELEASE_FOR_SALE);
    }

    public function rules(): array
    {
        return [
            'release_type' => ['required', 'string', 'in:'.Batch::RELEASE_FOR_PRODUCTION.','.Batch::RELEASE_FOR_SALE],
        ];
    }
}
