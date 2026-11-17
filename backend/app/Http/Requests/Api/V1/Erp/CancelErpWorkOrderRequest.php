<?php

namespace App\Http\Requests\Api\V1\Erp;

use App\Http\Requests\Api\V1\Erp\Concerns\LooksUpWorkOrder;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The ERP cancelling a work order. `reason` is optional and is what the manager
 * reads when the cancellation becomes a change request. Authorization is handled
 * by the auth.apikey + scope middleware.
 */
class CancelErpWorkOrderRequest extends FormRequest
{
    use LooksUpWorkOrder;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...$this->lookupRules(),
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function reason(): ?string
    {
        $reason = $this->input('reason');

        return is_string($reason) && trim($reason) !== '' ? trim($reason) : null;
    }
}
