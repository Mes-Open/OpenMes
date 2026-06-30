<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Manually raising a roaming quality control. The `exists` rules already
 * exclude soft-deleted rows via SoftDeleteAwarePresenceVerifier.
 */
class StoreRoamingQualityControlTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is gated by the supervisor/admin middleware
    }

    public function rules(): array
    {
        return [
            'quality_control_trigger_id' => ['required', 'integer', 'exists:quality_control_triggers,id'],
            'line_id' => ['nullable', 'integer', 'exists:lines,id'],
            'workstation_id' => ['nullable', 'integer', 'exists:workstations,id'],
            'work_order_id' => ['nullable', 'integer', 'exists:work_orders,id'],
            'batch_id' => ['nullable', 'integer', 'exists:batches,id'],
        ];
    }
}
