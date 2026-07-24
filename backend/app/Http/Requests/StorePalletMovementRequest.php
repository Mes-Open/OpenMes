<?php

namespace App\Http\Requests;

use App\Enums\PalletStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePalletMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route is gated by the Operator|Supervisor|Admin role middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            // Must be a live, still-movable pallet — the same set the terminal
            // offers. Shipped pallets are dispatched (their location and ledger
            // are frozen); soft-deleted pallets are gone. Excluding them here
            // keeps validation and the controller's findOrFail in agreement,
            // so a stale id yields a 422 rather than a 404.
            'pallet_id' => [
                'required',
                'integer',
                Rule::exists('pallets', 'id')->where(fn ($q) => $q
                    ->whereNull('deleted_at')
                    ->where('status', '!=', PalletStatus::Shipped->value)),
            ],
            // The operator must be an active, non-deleted logistics worker.
            'worker_id' => [
                'required',
                'integer',
                Rule::exists('workers', 'id')->where(fn ($q) => $q
                    ->whereNull('deleted_at')
                    ->where('is_active', true)
                    ->where('is_logistics', true)),
            ],
            'to_location' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'pallet_id.exists' => __('Select a movable (not shipped) pallet.'),
            'worker_id.exists' => __('Select an active logistics operator.'),
        ];
    }
}
