<?php

namespace App\Http\Requests\Api\V1\Erp;

use App\Http\Requests\Api\V1\Erp\Concerns\LooksUpWorkOrder;
use App\Http\Requests\Api\V1\Erp\Concerns\ParsesErpIncludes;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Reading one work order back: by id, or by order number with `?by=order_no`.
 * Authorization is handled by the auth.apikey + scope middleware.
 */
class ShowErpWorkOrderRequest extends FormRequest
{
    use LooksUpWorkOrder;
    use ParsesErpIncludes;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [...$this->lookupRules(), ...$this->includeRules()];
    }
}
