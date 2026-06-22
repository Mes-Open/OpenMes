<?php

namespace App\Http\Requests;

use App\Models\WorkstationState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Manually set a workstation's machine state (#87) — used by operators on their
 * workstation panel and by supervisors/admins on the machine monitor. The state
 * must be one of the known WorkstationState values.
 */
class SetWorkstationStateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route sits behind auth + role middleware.
    }

    public function rules(): array
    {
        return [
            'state' => ['required', 'string', Rule::in(WorkstationState::STATES)],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
