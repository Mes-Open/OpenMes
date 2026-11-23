<?php

namespace App\Http\Requests\Operator;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Operator terminal: clock on / off a step, start / stop its machine time
 * (#400). The route is role-gated; the controller checks the step is on the
 * operator's selected line. Nothing else is sent: the operator is the caller
 * and the machine is the step's workstation or the terminal's own.
 */
class RecordStepTimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [];
    }
}
