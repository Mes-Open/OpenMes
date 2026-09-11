<?php

namespace App\Http\Requests\Operator;

use Illuminate\Foundation\Http\FormRequest;

class CompleteUnitStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route is behind role middleware; controller checks line ownership.
    }

    public function rules(): array
    {
        return [];
    }
}
