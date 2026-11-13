<?php

namespace App\Http\Requests\Api\V1;

use App\Services\Traceability\LotWhereUsedService;
use Illuminate\Foundation\Http\FormRequest;

/** Query parameters of GET /api/v1/material-lots/{lot}/where-used. */
class MaterialLotWhereUsedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Same access as the genealogy endpoints: any authenticated user (auth:sanctum).
    }

    public function rules(): array
    {
        return [
            'max_depth' => ['sometimes', 'integer', 'min:0', 'max:'.LotWhereUsedService::MAX_DEPTH],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.LotWhereUsedService::MAX_LIMIT],
            'include_removed' => ['sometimes', 'boolean'],
        ];
    }
}
