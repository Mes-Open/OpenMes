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
            // Exactly what the PIN login form accepts (PinLoginRequest): a shorter
            // PIN used to be saved here and then refused at every login.
            'pin' => 'required|digits:6|confirmed',
        ];
    }

    public function messages(): array
    {
        return [
            'pin.digits' => __('PIN must be exactly 6 digits.'),
            'pin.confirmed' => __('PIN confirmation does not match.'),
        ];
    }
}
