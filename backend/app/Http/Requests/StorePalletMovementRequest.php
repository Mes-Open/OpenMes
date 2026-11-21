<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPalletLogistics;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePalletMovementRequest extends FormRequest
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
            'worker_id' => ['required', 'integer', $this->activeLogisticsOperatorExists()],
            'to_location' => ['required', 'string', 'max:100'],
            // Optional re-route booked with the move (#101). Omitted/blank keeps
            // whatever destination the pallet already had — clearing one is a
            // deliberate act that goes through the destination endpoint.
            'to_destination' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            // Physical damage found on this move (receipt on the first one).
            // What was found is part of the record, so the note is required.
            'damaged' => ['nullable', 'boolean'],
            'condition_note' => [Rule::requiredIf(fn () => $this->boolean('damaged')), 'nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return $this->palletLogisticsMessages() + [
            'condition_note.required' => __('Describe the damage.'),
        ];
    }

    public function attributes(): array
    {
        return [
            'condition_note' => __('Damage note'),
        ];
    }

    /** The damage note when the pallet was marked damaged on this move, otherwise null. */
    public function damageNote(): ?string
    {
        return $this->boolean('damaged') ? $this->string('condition_note')->trim()->toString() : null;
    }

    /** The re-route booked with this move, or null to keep the standing one. */
    public function toDestination(): ?string
    {
        return $this->optionalText('to_destination');
    }
}
