<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Releasing a held unit is a supervisor's call, recorded with why. */
class UnblockUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasAnyRole(['Supervisor', 'Admin']);
    }

    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'max:500'],
        ];
    }
}
