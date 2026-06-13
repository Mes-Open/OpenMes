<?php

namespace App\Http\Requests;

use App\Support\TabRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleTabAccessRequest extends FormRequest
{
    /** Route middleware (role:Admin) gates access. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // access = { "<roleName>": ["orders", "hr", ...] }
            'access' => 'present|array',
            'access.*' => 'array',
            'access.*.*' => ['string', Rule::in(TabRegistry::keys())],
        ];
    }
}
