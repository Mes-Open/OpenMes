<?php

namespace App\Http\Requests\Operator;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Operator moves a work order to another board column (line status), with the
 * produced quantity when the column is a done one. Route middleware gates the
 * operator area; the controller checks the order belongs to the selected line
 * and may be closed.
 */
class UpdateLineStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'line_status_id' => ['nullable', 'exists:line_statuses,id'],
            'produced_qty' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
