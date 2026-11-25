<?php

namespace App\Http\Requests\Web\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (
            $user->hasRole('Admin')
            || $user->can('tab:materials')
            || $user->can('tab:warehouse')
        );
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:999999999', 'decimal:0,3'],
            'reference' => ['required', 'string', 'max:120'],
        ];
    }
}
