<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HoldMaterialLotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route sits behind auth + the admin tab-access middleware.
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
            'issue_id' => ['nullable', 'integer', 'exists:issues,id'],
        ];
    }
}
