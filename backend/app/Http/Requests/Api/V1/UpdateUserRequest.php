<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // policy enforced in controller
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'username' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($userId)],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'account_type' => ['sometimes', 'required', 'in:user,workstation'],
            'role' => ['sometimes', 'nullable', 'exists:roles,name'],
            'workstation_id' => ['sometimes', 'nullable', 'integer', 'exists:workstations,id'],
            'worker_id' => ['sometimes', 'nullable', 'integer', 'exists:workers,id'],
            'force_password_change' => ['sometimes', 'boolean'],
            'line_ids' => ['sometimes', 'nullable', 'array'],
            'line_ids.*' => ['integer', 'exists:lines,id'],
        ];
    }
}
