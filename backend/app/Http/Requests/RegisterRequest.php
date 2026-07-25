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
            'name' => ['required', 'string', 'max:100', 'regex:/^[\p{L}\s\-\']+$/u'],
            'username' => ['required', 'string', 'min:3', 'max:30', 'alpha_num', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => __('Name may only contain letters, spaces, hyphens and apostrophes.'),
            'username.alpha_num' => __('Username may only contain letters and numbers.'),
            'username.unique' => __('This username is already taken.'),
            'email.unique' => __('An account with this email already exists.'),
            'password.min' => __('Password must be at least 8 characters.'),
            'password.confirmed' => __('Passwords do not match.'),
        ];
    }
}
