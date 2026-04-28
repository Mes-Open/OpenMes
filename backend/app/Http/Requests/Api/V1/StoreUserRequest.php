<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // policy enforced in controller
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults()],
            'account_type' => ['required', 'in:user,workstation'],
            'role' => ['required_if:account_type,user', 'nullable', Rule::in(['Operator', 'Supervisor', 'Admin'])],
            'workstation_id' => ['nullable', 'integer', 'exists:workstations,id', 'required_if:account_type,workstation'],
            'worker_id' => ['nullable', 'integer', 'exists:workers,id'],
            'force_password_change' => ['nullable', 'boolean'],
            'line_ids' => ['nullable', 'array'],
            'line_ids.*' => ['integer', 'exists:lines,id'],
        ];
    }
}
