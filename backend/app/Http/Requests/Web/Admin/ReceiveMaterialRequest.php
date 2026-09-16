<?php

namespace App\Http\Requests\Web\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('Admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:999999999', 'decimal:0,3'],
            'reference' => ['required', 'string', 'max:120'],
        ];
    }
}
