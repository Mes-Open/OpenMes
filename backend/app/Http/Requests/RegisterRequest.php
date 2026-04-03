<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                  => ['required', 'string', 'max:100', 'regex:/^[\p{L}\s\-\']+$/u'],
            'username'              => ['required', 'string', 'min:3', 'max:30', 'alpha_num', 'unique:users,username'],
            'email'                 => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex'         => 'Name may only contain letters, spaces, hyphens and apostrophes.',
            'username.alpha_num' => 'Username may only contain letters and numbers.',
            'username.unique'    => 'This username is already taken.',
            'email.unique'       => 'An account with this email already exists.',
            'password.min'       => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Passwords do not match.',
        ];
    }
}
