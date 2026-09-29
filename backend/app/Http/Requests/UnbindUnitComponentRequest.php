<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Taking a component out of a unit at the station - with the reason kept on the unit's history. */
class UnbindUnitComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route is behind auth + role:Operator|Supervisor|Admin middleware.
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
