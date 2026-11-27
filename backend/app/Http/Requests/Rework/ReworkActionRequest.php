<?php

namespace App\Http\Requests\Rework;

use App\Models\ReworkOperation;
use App\Rules\VisibleToCaller;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Start a rework, or record operator / machine time on it (#400). Anyone who
 * may see the work order may do so.
 */
class ReworkActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $rework = $this->route('rework');

        return $rework instanceof ReworkOperation
            && (bool) $this->user()?->can('view', $rework->workOrder);
    }

    public function rules(): array
    {
        return [
            // Machine time only: the machine that runs the rework, when it is not the step's own.
            'workstation_id' => ['nullable', 'bail', 'integer', VisibleToCaller::workstation()],
        ];
    }
}
