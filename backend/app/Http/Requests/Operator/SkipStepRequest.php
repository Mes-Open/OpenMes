<?php

namespace App\Http\Requests\Operator;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates skipping an optional (or variant) batch step from the operator
 * screens. The reason is optional and kept on the step for audit. Whether the
 * step may be skipped at all (optional, not finished, no quantities recorded
 * downstream) is BatchService::skipStep's call; the route is behind role
 * middleware and the controller checks the operator serves the step, so
 * authorize() is open here.
 */
class SkipStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'skip_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
