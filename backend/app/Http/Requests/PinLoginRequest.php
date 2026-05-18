<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PinLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => 'required|string',
            'pin' => 'required|string|digits_between:6,6',
        ];
    }

    public function messages(): array
    {
        return [
            'pin.digits_between' => 'PIN must be exactly 6 digits.',
        ];
    }
}
