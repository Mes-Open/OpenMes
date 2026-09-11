<?php

namespace App\Http\Requests;

use App\Models\SerialSequence;
use App\Rules\ValidLotPattern;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared store/update validation for Serial sequences (web admin) — parallel
 * to LotSequenceRequest. Two modes: token pattern (e.g. "SN-[date]-[seq]") or
 * legacy prefix/suffix — prefix is only required when no pattern is given.
 */
class SerialSequenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route is behind auth + admin middleware
    }

    public function rules(): array
    {
        $sequenceId = $this->route('serial_sequence')?->id;

        return [
            'name' => ['required', 'string', 'max:50'],
            'product_type_id' => [
                'nullable',
                'exists:product_types,id',
                Rule::unique('serial_sequences', 'product_type_id')->ignore($sequenceId),
            ],
            'pattern' => ['nullable', 'string', 'max:100', new ValidLotPattern],
            'prefix' => ['required_without:pattern', 'nullable', 'string', 'max:20'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'pad_size' => ['nullable', 'integer', 'min:1', 'max:10'],
            'year_prefix' => ['nullable', 'boolean'],
            'reset_period' => ['nullable', Rule::in(SerialSequence::RESET_PERIODS)],
        ];
    }

    /**
     * Normalized payload with defaults applied.
     */
    public function payload(): array
    {
        $validated = $this->validated();

        $validated['year_prefix'] = $this->boolean('year_prefix');
        $validated['pad_size'] = $validated['pad_size'] ?? 4;
        $validated['reset_period'] = $validated['reset_period'] ?? 'none';
        // Legacy column is NOT NULL; pattern mode doesn't use it.
        $validated['prefix'] = $validated['prefix'] ?? '';

        return $validated;
    }
}
