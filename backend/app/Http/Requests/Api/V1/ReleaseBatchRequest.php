<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Batch;
use Illuminate\Foundation\Http\FormRequest;

class ReleaseBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'release_type' => ['required', 'string', 'in:'.Batch::RELEASE_FOR_PRODUCTION.','.Batch::RELEASE_FOR_SALE],
        ];
    }
}
