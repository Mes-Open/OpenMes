<?php

namespace App\Http\Requests\Api\V1\Erp;

use App\Http\Requests\Api\V1\Erp\Concerns\ParsesErpIncludes;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Query string of the single work-order export: only the optional `include`
 * list. Authorization is handled by the auth.apikey + scope middleware.
 */
class ErpWorkOrderShowRequest extends FormRequest
{
    use ParsesErpIncludes;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->includeRules();
    }
}
