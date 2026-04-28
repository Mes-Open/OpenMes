<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => 'required|string',
            'pin' => 'required|digits_between:4,6|confirmed',
        ];
    }

    public function messages(): array
    {
        return [
            'pin.digits_between' => 'PIN must be 4–6 digits.',
            'pin.confirmed' => 'PIN confirmation does not match.',
        ];
    }
}
