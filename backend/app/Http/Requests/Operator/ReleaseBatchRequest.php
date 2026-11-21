<?php

namespace App\Http\Requests\Operator;

use App\Http\Requests\Api\V1\ReleaseBatchRequest as ApiReleaseBatchRequest;

/**
 * Operator-screen batch release. Same authorization as the API (releasing for
 * sale needs `release batch for sale`), plus the optional scrap quantity the
 * release form posts.
 */
class ReleaseBatchRequest extends ApiReleaseBatchRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'scrap_qty' => ['nullable', 'numeric', 'min:0'],
        ]);
    }
}
