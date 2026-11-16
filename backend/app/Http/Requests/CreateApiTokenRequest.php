<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ApiTokenScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateApiTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route sits behind role:Admin.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'scope' => ['sometimes', 'string', Rule::enum(ApiTokenScope::class)],
        ];
    }

    public function scope(): ApiTokenScope
    {
        return ApiTokenScope::from($this->validated('scope', ApiTokenScope::Full->value));
    }
}
