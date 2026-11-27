<?php

namespace App\Http\Requests\Rework;

use App\Models\ReworkOperation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Cancel a rework (#400): every held piece goes back to its step unchanged.
 * Route-gated to Supervisor|Admin.
 */
class CancelReworkOperationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $rework = $this->route('rework');

        return $rework instanceof ReworkOperation
            && (bool) $this->user()?->can('update', $rework->workOrder);
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
