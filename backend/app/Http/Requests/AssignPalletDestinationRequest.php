<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPalletLogistics;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Re-route a pallet from the logistics view (#101) without moving it.
 */
class AssignPalletDestinationRequest extends FormRequest
{
    use ValidatesPalletLogistics;

    public function authorize(): bool
    {
        // Route is gated by the Operator|Supervisor|Admin role middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            'pallet_id' => ['required', 'integer', $this->movablePalletExists()],
            // Null/blank clears the destination — the pallet is no longer
            // expected anywhere.
            'destination' => ['nullable', 'string', 'max:100'],
            // Attribution is optional here: re-routing is a planning act that a
            // dispatcher may do without a forklift operator involved.
            'worker_id' => ['nullable', 'integer', $this->activeLogisticsOperatorExists()],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return $this->palletLogisticsMessages();
    }

    /** The new destination, or null to clear it. */
    public function destination(): ?string
    {
        return $this->optionalText('destination');
    }
}
