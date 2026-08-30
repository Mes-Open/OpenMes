<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Raising a maintenance issue from a stop on the shift monitor. The note is the
 * only thing the caller supplies — which stop, and which work order it belongs
 * to, are resolved server-side.
 */
class EscalateDowntimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Admin', 'Supervisor']) ?? false;
    }

    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
