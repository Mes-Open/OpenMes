<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StartUnitStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route is behind auth:sanctum.
    }

    public function rules(): array
    {
        return [];
    }
}
